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
use core_external\external_api;

/**
 * PHPUnit tests for toggle_favourite external endpoint.
 *
 * Every negative case is built so that the guard under test is the only one able to throw: an
 * itemid that already owns a plan for the acting user, favourites left enabled, and a valid
 * itemtype, except for the one input the test deliberately breaks. Without that, an earlier test
 * user with no plan of their own always falls through to the final ownership/existence check
 * (toggle_favourite.php:94-98), which throws {@see \invalid_parameter_exception} regardless of
 * which specific guard the test meant to pin, and a bare exception-class assertion cannot tell the
 * two apart.
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
     * @param int $userid The owner.
     * @return int The plan id.
     */
    private function create_owned_plan(int $userid): int {
        $plan = $this->getDataGenerator()->get_plugin_generator('core_competency')->create_plan([
            'userid' => $userid,
        ]);
        return (int) $plan->get('id');
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
     * ownership/existence check instead — which, for an itemtype other than 'plan', looks the
     * itemid up in the competency table and throws a differently worded exception. Asserting the
     * exact debuginfo text is what tells the two apart.
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
     * Without the itemid <= 0 guard, execution reaches the ownership check with itemid 0, which
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
     * A 'competency' itemtype is checked against the competency table, not the plan table.
     *
     * Without the competency branch of the existence check in execute(), an arbitrary or
     * non-existent competency id could be favourited, or a real one wrongly refused.
     */
    public function test_execute_rejects_unknown_competency(): void {
        $this->resetAfterTest();
        set_debugging(DEBUG_DEVELOPER, true);

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        set_config('enable_favourites', 1, 'block_dimensions');

        $response = $this->call('competency', 999999);

        $this->assertTrue($response['error']);
        $this->assertSame('invalidparameter', $response['exception']->errorcode);
        $this->assertStringContainsString('Unknown competency: 999999', $this->debuginfo($response));

        // Control: a real competency id succeeds for the same guard.
        $competency = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $competency->create_framework();
        $compid = (int) $competency->create_competency(['competencyframeworkid' => $framework->get('id')])->get('id');

        $control = $this->call('competency', $compid);
        $this->assertFalse($control['error'], $control['exception']->message ?? '');
        $this->assertTrue($control['data']['isfavourite']);
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
     * The plan ownership check in execute() is the only one standing between an arbitrary logged-in user and a
     * favourite row for a plan they do not own. Running the owner through the same itemid in the
     * same test proves the guard rejects the attacker specifically, not every plan of that id.
     */
    public function test_execute_rejects_another_users_plan(): void {
        $this->resetAfterTest();
        set_debugging(DEBUG_DEVELOPER, true);

        $owner = $this->getDataGenerator()->create_user();
        $attacker = $this->getDataGenerator()->create_user();
        $planid = $this->create_owned_plan((int) $owner->id);

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
