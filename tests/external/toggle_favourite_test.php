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

/**
 * PHPUnit tests for toggle_favourite external endpoint.
 *
 * Every negative case is built so that the guard under test is the only one able to throw: an
 * itemid that already owns a plan for the acting user, favourites left enabled, and a valid
 * itemtype, except for the one input the test deliberately breaks. Without that, an earlier test
 * user with no plan of their own always falls through to the final listing check (the item must be
 * one {@see \block_dimensions\local\dataset_provider::lists_item()} lists), which throws
 * {@see \invalid_parameter_exception} regardless of which specific guard the test meant to pin, and
 * a bare exception-class assertion cannot tell the two apart.
 *
 * @package    block_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \block_dimensions\external\toggle_favourite
 */
final class toggle_favourite_test extends advanced_testcase {
    /** @var string Web service function name registered in db/services.php. */
    private const WSFUNCTION = 'block_dimensions_toggle_favourite';

    /**
     * Call the web service the way a client would.
     *
     * Routes through call_external_function() rather than a bare execute(): that is what maps a
     * thrown guard into the response array's 'exception' entry instead of letting it propagate,
     * and it is what a real caller of the AJAX-registered function actually gets back.
     *
     * @param string $itemtype The itemtype argument.
     * @param int $itemid The itemid argument.
     * @return array The call_external_function() response: error, data|exception.
     */
    private function call(string $itemtype, int $itemid): array {
        $_POST['sesskey'] = sesskey();
        return external_api::call_external_function(self::WSFUNCTION, [
            'itemtype' => $itemtype,
            'itemid' => $itemid,
        ]);
    }

    /**
     * Create a learning plan owned by the given user.
     *
     * Active by default, not the generator's draft: api::list_user_plans() lists a draft only to a
     * holder of moodle/competency:planviewowndraft, which no archetype has, so a draft would be
     * refused by the listing check even for its owner.
     *
     * @param int $userid The owner.
     * @param int $status The plan status.
     * @return int The plan id.
     */
    private function create_owned_plan(int $userid, int $status = plan::STATUS_ACTIVE): int {
        $plan = $this->getDataGenerator()->get_plugin_generator('core_competency')->create_plan([
            'userid' => $userid,
            'status' => $status,
        ]);
        return (int) $plan->get('id');
    }

    /**
     * Create a competency in a new framework.
     *
     * @return int The competency id.
     */
    private function create_competency(): int {
        $generator = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $generator->create_framework();
        return (int) $generator->create_competency(['competencyframeworkid' => $framework->get('id')])->get('id');
    }

    /**
     * The debuginfo a thrown moodle_exception carries into the web service response.
     *
     * call_external_function() only keeps 'exception'->debuginfo when debugging('', DEBUG_DEVELOPER)
     * is true. PHPUnit resets the site to that level between tests; the fallback to the message
     * keeps a debug-level change elsewhere in the suite from turning this into an unrelated failure.
     *
     * @param array $response A call_external_function() response with 'error' true.
     * @return string
     */
    private function debuginfo(array $response): string {
        return (string) ($response['exception']->debuginfo ?? $response['exception']->message);
    }

    /**
     * Invalid itemtype must be what stops the call, not the ownership check further down.
     *
     * The fixture owns a plan, so if the itemtype guard were deleted the call would reach the
     * listing check instead, which throws a differently worded exception. Asserting the exact
     * debuginfo text is what tells the two apart.
     */
    public function test_execute_rejects_invalid_itemtype(): void {
        $this->resetAfterTest();
        set_debugging(DEBUG_DEVELOPER, true);

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        set_config('enable_favourites', 1, 'block_dimensions');
        $planid = $this->create_owned_plan((int) $user->id);

        $response = $this->call('invalidtype', $planid);

        $this->assertTrue($response['error']);
        $this->assertSame('invalidparameter', $response['exception']->errorcode);
        $this->assertStringContainsString('Invalid itemtype: invalidtype', $this->debuginfo($response));
    }

    /**
     * A zero itemid must be what stops the call, not the ownership check further down.
     *
     * Without the itemid <= 0 guard, execution reaches the listing check with itemid 0, which
     * throws its own, differently worded, invalid_parameter_exception — so the exact debuginfo
     * text, not just the exception class, is what proves this guard fired.
     */
    public function test_execute_rejects_non_positive_itemid(): void {
        $this->resetAfterTest();
        set_debugging(DEBUG_DEVELOPER, true);

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        set_config('enable_favourites', 1, 'block_dimensions');

        $response = $this->call('plan', 0);

        $this->assertTrue($response['error']);
        $this->assertSame('invalidparameter', $response['exception']->errorcode);
        $this->assertStringContainsString(
            'Invalid itemid: must be greater than zero.',
            $this->debuginfo($response)
        );
    }

    /**
     * A negative itemid must be what stops the call, not the ownership check further down.
     */
    public function test_execute_rejects_negative_itemid(): void {
        $this->resetAfterTest();
        set_debugging(DEBUG_DEVELOPER, true);

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        set_config('enable_favourites', 1, 'block_dimensions');

        $response = $this->call('plan', -1);

        $this->assertTrue($response['error']);
        $this->assertSame('invalidparameter', $response['exception']->errorcode);
        $this->assertStringContainsString(
            'Invalid itemid: must be greater than zero.',
            $this->debuginfo($response)
        );
    }

    /**
     * A disabled favourites setting must be what stops the call, not the ownership check.
     *
     * The plugin's own exception ('favouritesdisabled', block_dimensions) has an errorcode no
     * other guard in this endpoint can produce, so this is the one case in the file where the
     * errorcode alone already distinguishes it. The owned-plan fixture still matters: without it,
     * this test would pass even after the guard was deleted, because the final ownership check
     * would then throw its own invalid_parameter_exception instead.
     */
    public function test_execute_throws_when_favourites_disabled(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $planid = $this->create_owned_plan((int) $user->id);
        set_config('enable_favourites', 0, 'block_dimensions');

        $response = $this->call('plan', $planid);

        $this->assertTrue($response['error']);
        $this->assertSame('favouritesdisabled', $response['exception']->errorcode);
    }

    /**
     * A guest must be rejected before any itemtype, itemid or favourites guard runs.
     */
    public function test_execute_rejects_guest_user(): void {
        $this->resetAfterTest();
        $this->setGuestUser();

        $response = $this->call('plan', 1);

        $this->assertTrue($response['error']);
        $this->assertSame('noguest', $response['exception']->errorcode);
    }

    /**
     * A competency is favourited only when it is in one of the caller's plans; one that exists
     * elsewhere is refused with the same message as an id nobody holds.
     *
     * The same message is the point: a distinct one for a missing id told any caller which
     * competency ids exist. The control adds the same competency to the caller's own plan, and the
     * same call then succeeds.
     */
    public function test_execute_refuses_a_competency_outside_the_callers_plans(): void {
        global $DB;
        $this->resetAfterTest();
        set_debugging(DEBUG_DEVELOPER, true);

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        set_config('enable_favourites', 1, 'block_dimensions');
        $planid = $this->create_owned_plan((int) $user->id);
        $compid = $this->create_competency();
        // The caller's plan holds another competency, so the check must compare ids, not count rows.
        $this->getDataGenerator()->get_plugin_generator('core_competency')->create_plan_competency([
            'planid' => $planid,
            'competencyid' => $this->create_competency(),
        ]);
        $missingid = (int) $DB->get_field_sql('SELECT MAX(id) FROM {competency}') + 1000;

        $elsewhere = $this->call('competency', $compid);
        $this->assertTrue($elsewhere['error']);
        $this->assertSame('invalidparameter', $elsewhere['exception']->errorcode);
        $this->assertStringContainsString('Unknown or inaccessible competency: ' . $compid, $this->debuginfo($elsewhere));

        $missing = $this->call('competency', $missingid);
        $this->assertTrue($missing['error']);
        $this->assertSame('invalidparameter', $missing['exception']->errorcode);
        $this->assertStringContainsString('Unknown or inaccessible competency: ' . $missingid, $this->debuginfo($missing));

        // Control: in the caller's own plan, the same competency can be favourited.
        $this->getDataGenerator()->get_plugin_generator('core_competency')->create_plan_competency([
            'planid' => $planid,
            'competencyid' => $compid,
        ]);
        $control = $this->call('competency', $compid);
        $this->assertFalse($control['error'], $control['exception']->message ?? '');
        $this->assertTrue($control['data']['isfavourite']);
    }

    /**
     * An own draft plan is refused while core does not list drafts to their owner, and accepted
     * once it does (the control): the check follows the plan list the block is built from, not
     * the plan's owner column.
     */
    public function test_execute_follows_core_listing_for_an_own_draft_plan(): void {
        global $CFG;
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        set_config('enable_favourites', 1, 'block_dimensions');
        $planid = $this->create_owned_plan((int) $user->id, plan::STATUS_DRAFT);

        $refused = $this->call('plan', $planid);
        $this->assertTrue($refused['error']);
        $this->assertSame('invalidparameter', $refused['exception']->errorcode);

        assign_capability(
            'moodle/competency:planviewowndraft',
            CAP_ALLOW,
            $CFG->defaultuserroleid,
            \context_system::instance()->id,
            true
        );
        accesslib_clear_all_caches_for_unit_testing();

        $control = $this->call('plan', $planid);
        $this->assertFalse($control['error'], $control['exception']->message ?? '');
        $this->assertTrue($control['data']['isfavourite']);
    }

    /**
     * A favourite whose competency the block no longer lists can still be removed.
     *
     * The row is written straight through core_favourites, as one left behind by a plan that has
     * since lost the competency would be. The precondition proves adding the same item is refused,
     * so the removal succeeds although the listing check would refuse it.
     */
    public function test_execute_removes_a_favourite_the_block_no_longer_lists(): void {
        global $DB;
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        set_config('enable_favourites', 1, 'block_dimensions');
        $compid = $this->create_competency();
        $usercontext = \context_user::instance((int) $user->id);
        $favourite = [
            'component' => 'block_dimensions',
            'itemtype' => 'competency',
            'itemid' => $compid,
            'userid' => (int) $user->id,
        ];

        // Precondition: the listing check refuses this competency.
        $this->assertTrue($this->call('competency', $compid)['error']);

        \core_favourites\service_factory::get_service_for_user_context($usercontext)
            ->create_favourite('block_dimensions', 'competency', $compid, $usercontext);
        $this->assertTrue($DB->record_exists('favourite', $favourite));

        $removed = $this->call('competency', $compid);
        $this->assertFalse($removed['error'], $removed['exception']->message ?? '');
        $this->assertFalse($removed['data']['isfavourite']);
        $this->assertFalse($DB->record_exists('favourite', $favourite));
    }

    /**
     * A owner favouriting their own plan gets a written, then removed, favourite row.
     *
     * The control that closes B2: proves the endpoint can actually succeed at all, so the negative
     * tests above are excluding real callers rather than passing by accident.
     */
    public function test_execute_succeeds_and_round_trips_for_the_owner(): void {
        global $DB;
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $planid = $this->create_owned_plan((int) $user->id);
        $favourite = [
            'component' => 'block_dimensions',
            'itemtype' => 'plan',
            'itemid' => $planid,
            'userid' => (int) $user->id,
        ];
        $this->assertFalse($DB->record_exists('favourite', $favourite));

        $added = $this->call('plan', $planid);
        $this->assertFalse($added['error'], $added['exception']->message ?? '');
        $this->assertTrue($added['data']['isfavourite']);
        $this->assertTrue($DB->record_exists('favourite', $favourite));

        $removed = $this->call('plan', $planid);
        $this->assertFalse($removed['error'], $removed['exception']->message ?? '');
        $this->assertFalse($removed['data']['isfavourite']);
        $this->assertFalse($DB->record_exists('favourite', $favourite));
    }

    /**
     * A user cannot favourite another user's plan; the plan's own owner still can (the control).
     *
     * The listing check in execute() is the only one standing between an arbitrary logged-in user and a
     * favourite row for a plan they do not own. Running the owner through the same itemid in the
     * same test proves the guard rejects the attacker specifically, not every plan of that id.
     */
    public function test_execute_rejects_another_users_plan(): void {
        $this->resetAfterTest();
        set_debugging(DEBUG_DEVELOPER, true);

        $owner = $this->getDataGenerator()->create_user();
        $attacker = $this->getDataGenerator()->create_user();
        $planid = $this->create_owned_plan((int) $owner->id);
        // The attacker has a plan of their own, so the check must compare ids, not find any plan.
        $this->create_owned_plan((int) $attacker->id);

        $this->setUser($attacker);
        $response = $this->call('plan', $planid);
        $this->assertTrue($response['error']);
        $this->assertSame('invalidparameter', $response['exception']->errorcode);
        $this->assertStringContainsString(
            'Unknown or inaccessible plan: ' . $planid,
            $this->debuginfo($response)
        );

        // Control: the same itemid succeeds for the plan's actual owner.
        $this->setUser($owner);
        $control = $this->call('plan', $planid);
        $this->assertFalse($control['error'], $control['exception']->message ?? '');
        $this->assertTrue($control['data']['isfavourite']);
    }
}
