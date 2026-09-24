<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace block_dimensions\external;

use advanced_testcase;
use core_competency\plan;
use core_external\external_api;
use local_dimensions\helper;
use moodle_url;

/**
 * PHPUnit tests for set_return_context external endpoint.
 *
 * Covers the guard order (guest, plan ownership, feature flag) and both storage shapes: a single
 * course when courseid is given, and every course the plan's template reaches through its
 * competencies when it is not. The template/competency seeding mirrors
 * template_course_cache::fetch_courses_for_template() (competency_templatecomp joined to
 * competency_coursecomp), which is what {@see \local_dimensions\template_course_cache::get_courses_for_plan()}
 * resolves for the courseid 0 branch.
 *
 * @package    block_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \block_dimensions\external\set_return_context
 */
final class set_return_context_test extends advanced_testcase {
    /** @var string Web service function name registered in db/services.php. */
    private const WSFUNCTION = 'block_dimensions_set_return_context';

    /**
     * Call the web service the way a client would.
     *
     * Routes through call_external_function() rather than a bare execute(): that is what maps a
     * thrown guard into the response array's 'exception' entry instead of letting it propagate,
     * and it is what clean_returnvalue() actually strips the response down to.
     *
     * @param int $planid The planid argument.
     * @param int $courseid The courseid argument.
     * @return array The call_external_function() response: error, data|exception.
     */
    private function call(int $planid, int $courseid = 0): array {
        $_POST['sesskey'] = sesskey();
        return external_api::call_external_function(self::WSFUNCTION, [
            'planid' => $planid,
            'courseid' => $courseid,
        ]);
    }

    /**
     * Create an active learning plan owned by the given user, optionally based on a template.
     *
     * Active, not the generator's draft default: api::read_plan() routes a draft through
     * moodle/competency:planviewowndraft, which no default archetype holds, so even the owner
     * would be refused and every guard test here would read as the ownership check firing when it
     * is really this default that fired instead.
     *
     * @param int $userid The owner.
     * @param int $templateid The template id, or 0 for a template-less plan.
     * @return int The plan id.
     */
    private function create_owned_plan(int $userid, int $templateid = 0): int {
        $record = ['userid' => $userid, 'status' => plan::STATUS_ACTIVE];
        if ($templateid > 0) {
            $record['templateid'] = $templateid;
        }
        $plan = $this->getDataGenerator()->get_plugin_generator('core_competency')->create_plan($record);
        return (int) $plan->get('id');
    }

    /**
     * Build a competency framework with one competency, linked to a template and to the given
     * courses through core_competency_coursecomp, the join fetch_courses_for_template() reads.
     *
     * @param array $courseids Course ids to link the template's one competency to.
     * @return int The template id.
     */
    private function create_template_with_linked_courses(array $courseids): int {
        $generator = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $generator->create_framework();
        $competency = $generator->create_competency(['competencyframeworkid' => $framework->get('id')]);
        $template = $generator->create_template();

        $generator->create_template_competency([
            'templateid' => $template->get('id'),
            'competencyid' => $competency->get('id'),
        ]);
        foreach ($courseids as $courseid) {
            $generator->create_course_competency([
                'courseid' => $courseid,
                'competencyid' => $competency->get('id'),
            ]);
        }

        return (int) $template->get('id');
    }

    /**
     * A guest is refused before any plan is read or anything is stored.
     */
    public function test_execute_rejects_guest_user(): void {
        $this->resetAfterTest();
        $this->setGuestUser();
        set_config('enablereturnbutton', 1, 'local_dimensions');

        $response = $this->call(1, 7);

        $this->assertTrue($response['error']);
        $this->assertSame('noguest', $response['exception']->errorcode);
        $this->assertNull(helper::get_return_context_for_course(7));
    }

    /**
     * A user cannot store a context keyed to another user's plan, and nothing is stored.
     *
     * api::read_plan() is the only guard between an arbitrary logged-in user and a write for a
     * course tied to somebody else's plan; its required_capability_exception carries an errorcode
     * ('nopermissions') no other guard in this endpoint produces, so the errorcode alone identifies
     * it. Running the owner through the same plan and course afterwards is the control: it proves
     * the write path itself works, so the attacker case above is failing on the ownership check and
     * not on some unrelated fault.
     */
    public function test_execute_rejects_another_users_plan_and_stores_nothing(): void {
        $this->resetAfterTest();
        set_config('enablereturnbutton', 1, 'local_dimensions');

        $owner = $this->getDataGenerator()->create_user();
        $attacker = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $planid = $this->create_owned_plan((int) $owner->id);

        $this->setUser($attacker);
        $response = $this->call($planid, (int) $course->id);

        $this->assertTrue($response['error']);
        $this->assertSame('nopermissions', $response['exception']->errorcode);
        $this->assertNull(helper::get_return_context_for_course((int) $course->id));

        // Control: the plan's actual owner can store a context for the same plan and course.
        $this->setUser($owner);
        $control = $this->call($planid, (int) $course->id);
        $this->assertFalse($control['error'], $control['exception']->message ?? '');
        $this->assertTrue($control['data']['success']);
        $this->assertNotNull(helper::get_return_context_for_course((int) $course->id));
    }

    /**
     * With the return-button feature off, the call reports failure and stores nothing; the same
     * call with the feature on (the control) reports success and does store.
     */
    public function test_execute_reports_failure_and_stores_nothing_when_feature_disabled(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $this->setUser($user);
        $planid = $this->create_owned_plan((int) $user->id);

        set_config('enablereturnbutton', 0, 'local_dimensions');
        $off = $this->call($planid, (int) $course->id);
        $this->assertFalse($off['error'], $off['exception']->message ?? '');
        $this->assertFalse($off['data']['success']);
        $this->assertNull(helper::get_return_context_for_course((int) $course->id));

        // Control: the same call succeeds and stores once the feature is enabled.
        set_config('enablereturnbutton', 1, 'local_dimensions');
        $on = $this->call($planid, (int) $course->id);
        $this->assertFalse($on['error'], $on['exception']->message ?? '');
        $this->assertTrue($on['data']['success']);
        $this->assertNotNull(helper::get_return_context_for_course((int) $course->id));
    }

    /**
     * A positive courseid stores the plan's view URL for that course only.
     */
    public function test_execute_with_positive_courseid_stores_for_that_course_only(): void {
        $this->resetAfterTest();
        set_config('enablereturnbutton', 1, 'local_dimensions');

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $targetcourse = $this->getDataGenerator()->create_course();
        $othercourse = $this->getDataGenerator()->create_course();
        $planid = $this->create_owned_plan((int) $user->id);

        $response = $this->call($planid, (int) $targetcourse->id);

        $this->assertFalse($response['error'], $response['exception']->message ?? '');
        $this->assertTrue($response['data']['success']);

        $expected = (new moodle_url('/local/dimensions/view-plan.php', ['id' => $planid]))->out(false);
        $stored = helper::get_return_context_for_course((int) $targetcourse->id);
        $this->assertNotNull($stored);
        $this->assertSame($expected, $stored['url']);

        // The unrelated course never carried by this call must stay unset.
        $this->assertNull(helper::get_return_context_for_course((int) $othercourse->id));
    }

    /**
     * A courseid of 0 resolves every course the plan's template reaches through its competencies
     * and stores the plan's view URL for each one, and for no other course.
     *
     * The template is seeded with one competency linked to two courses (courseA, courseB), mirroring
     * template_course_cache::get_courses_for_plan() -> get_courses_for_template() ->
     * fetch_courses_for_template(), and a third, unlinked course is the negative control.
     */
    public function test_execute_with_zero_courseid_stores_for_every_course_the_template_reaches(): void {
        $this->resetAfterTest();
        set_config('enablereturnbutton', 1, 'local_dimensions');

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $coursea = $this->getDataGenerator()->create_course();
        $courseb = $this->getDataGenerator()->create_course();
        $unlinkedcourse = $this->getDataGenerator()->create_course();
        $templateid = $this->create_template_with_linked_courses([(int) $coursea->id, (int) $courseb->id]);
        $planid = $this->create_owned_plan((int) $user->id, $templateid);

        $response = $this->call($planid, 0);

        $this->assertFalse($response['error'], $response['exception']->message ?? '');
        $this->assertTrue($response['data']['success']);

        $expected = (new moodle_url('/local/dimensions/view-plan.php', ['id' => $planid]))->out(false);
        $storeda = helper::get_return_context_for_course((int) $coursea->id);
        $storedb = helper::get_return_context_for_course((int) $courseb->id);
        $this->assertNotNull($storeda);
        $this->assertNotNull($storedb);
        $this->assertSame($expected, $storeda['url']);
        $this->assertSame($expected, $storedb['url']);

        // The course the template never links through a competency must stay unset.
        $this->assertNull(helper::get_return_context_for_course((int) $unlinkedcourse->id));
    }

    /**
     * execute_parameters()/execute_returns() define what clean_returnvalue() actually lets through:
     * an undeclared key in the handler's array would be silently stripped rather than surfaced.
     */
    public function test_execute_returns_is_cleaned_to_the_declared_structure(): void {
        $this->resetAfterTest();
        set_config('enablereturnbutton', 1, 'local_dimensions');

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $course = $this->getDataGenerator()->create_course();
        $planid = $this->create_owned_plan((int) $user->id);

        $raw = set_return_context::execute($planid, (int) $course->id);
        $cleaned = \core_external\external_api::clean_returnvalue(
            set_return_context::execute_returns(),
            $raw
        );

        $this->assertSame(['success'], array_keys($cleaned));
        $this->assertTrue($cleaned['success']);
    }
}
