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
 * A service declared 'readonlysession' runs without the session lock, so nothing it writes to
 * $SESSION survives the request.
 *
 * Core's contract for a read-only session is the one write_close() checks
 * (lib/classes/session/manager.php, the array_session_diff() of $SESSION taken at session start
 * and at close): no $SESSION key added, removed or changed. That is what is asserted here around
 * every call the block makes, the way the browser makes it, with local_dimensions' caches cold so
 * every fill runs. $USER is not compared, as core does not compare it.
 *
 * The metadata stays in docblocks rather than attributes because the plugin still supports
 * Moodle 4.5, whose moodle-cs cannot see attributes.
 *
 * @package    block_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \block_dimensions\external\get_block_dataset
 * @covers \block_dimensions\local\dataset_provider
 */
final class readonly_session_test extends advanced_testcase {
    /** @var string[] The local_dimensions cache definitions the dataset reads, purged before every call. */
    private const CACHES = ['template_metadata', 'competency_metadata', 'plan_trail'];

    /**
     * resetAfterTest() does not restore superglobals: drop the sesskey the calls plant in $_POST.
     *
     * @return void
     */
    protected function tearDown(): void {
        unset($_POST['sesskey']);
        parent::tearDown();
    }

    /**
     * The services that must hold no session lock, each with the argument sets the block sends
     * (amd/src/filters.js): the first load, the competency group, and the favourites filter.
     *
     * @return array Service name => list of argument arrays.
     */
    private static function read_only_calls(): array {
        return [
            'block_dimensions_get_block_dataset' => [
                ['favouritesonly' => false, 'loadgroup' => '', 'planstatus' => ''],
                ['favouritesonly' => false, 'loadgroup' => 'competency', 'planstatus' => 'active'],
                ['favouritesonly' => true, 'loadgroup' => 'plan', 'planstatus' => 'active'],
            ],
        ];
    }

    /**
     * Read the plugin's db/services.php as core does.
     *
     * @return array The $functions array it declares.
     */
    private static function declared_functions(): array {
        $functions = null;
        include(\core_component::get_component_directory('block_dimensions') . '/db/services.php');

        return $functions;
    }

    /**
     * $SESSION as core compares it: one entry per key, the value serialised so a change inside a
     * nested object is seen too.
     *
     * @return array Key => serialised value.
     */
    private static function session_snapshot(): array {
        global $SESSION;

        return array_map('serialize', (array) $SESSION);
    }

    /**
     * The $SESSION keys added, removed or changed between two snapshots.
     *
     * @param array $before Snapshot taken before.
     * @param array $after Snapshot taken after.
     * @return array The keys, sorted.
     */
    private static function changed_keys(array $before, array $after): array {
        $keys = array_keys(array_diff_assoc($before, $after) + array_diff_assoc($after, $before));
        sort($keys);

        return $keys;
    }

    /**
     * Every read and no write is declared read-only, the exercised set is the declared one, and
     * core's own reader of db/services.php sees the flag.
     *
     * Reading through external_function_info() is the point: it is the value
     * external_api::call_external_function() decides the session lock from.
     *
     * @return void
     */
    public function test_every_read_and_no_write_is_declared_read_only(): void {
        $declared = [];
        foreach (self::declared_functions() as $name => $function) {
            $readonly = !empty($function['readonlysession']);
            $this->assertSame($function['type'] === 'read', $readonly, $name);
            $this->assertSame($readonly, (bool) external_api::external_function_info($name)->readonlysession, $name);
            if ($readonly) {
                $declared[] = $name;
            }
        }

        $this->assertSame(array_keys(self::read_only_calls()), $declared);
    }

    /**
     * The comparison sees a write, so an empty result below is not an empty comparison.
     *
     * @return void
     */
    public function test_the_snapshot_sees_a_write(): void {
        global $SESSION;

        $this->resetAfterTest();
        $before = self::session_snapshot();
        $SESSION->block_dimensions_probe = (object) ['seen' => 1];
        $added = self::session_snapshot();
        $SESSION->block_dimensions_probe->seen = 2;

        $this->assertSame(['block_dimensions_probe'], self::changed_keys($before, $added));
        $this->assertSame(['block_dimensions_probe'], self::changed_keys($added, self::session_snapshot()));
    }

    /**
     * Every read-only service leaves $SESSION as it found it, with every argument set.
     *
     * The learner holds two active plans: one with no template, which draws a plan card and is
     * starred, and one from a template in its default competencies mode, whose competency is
     * linked to a course, which draws a competency card. So the plan cards, the competency cards
     * and the favourites filter all have something to return. Before each call the dataset's caches are purged, so every fill runs
     * inside the call. The read-only branch of external_api::call_external_function() is taken,
     * as on a site with $CFG->enable_read_only_sessions on.
     *
     * One require_login() runs before each snapshot. Core's after_require_login callbacks run
     * inside every service of every component, and under PHPUnit tool_mfa's writes a marker
     * ($SESSION->mfa_login_hook_test, admin/tool/mfa/lib.php); paying it first keeps that core
     * write out of the comparison without exempting any key from it.
     *
     * @return void
     */
    public function test_read_only_services_leave_the_session_untouched(): void {
        global $CFG;

        $this->resetAfterTest();
        set_config('enabled', 1, 'core_competency');
        $generator = $this->getDataGenerator();
        $ccg = $generator->get_plugin_generator('core_competency');
        $framework = $ccg->create_framework();
        $competency = $ccg->create_competency(['competencyframeworkid' => $framework->get('id')]);
        $course = $generator->create_course();
        $ccg->create_course_competency(['courseid' => $course->id, 'competencyid' => $competency->get('id')]);
        $template = $ccg->create_template();
        $ccg->create_template_competency([
            'templateid' => $template->get('id'),
            'competencyid' => $competency->get('id'),
        ]);
        $user = $generator->create_user();
        $plan = $ccg->create_plan(['userid' => $user->id, 'status' => plan::STATUS_ACTIVE]);
        $planid = (int) $plan->get('id');
        $ccg->create_plan([
            'userid' => $user->id,
            'templateid' => $template->get('id'),
            'status' => plan::STATUS_ACTIVE,
        ]);
        $usercontext = \context_user::instance($user->id);
        \core_favourites\service_factory::get_service_for_user_context($usercontext)
            ->create_favourite('block_dimensions', 'plan', $planid, $usercontext);
        $this->setUser($user);
        $CFG->enable_read_only_sessions = true;

        foreach (self::read_only_calls() as $name => $argsets) {
            foreach ($argsets as $index => $args) {
                foreach (self::CACHES as $definition) {
                    \cache::make('local_dimensions', $definition)->purge();
                }
                require_login();
                $_POST['sesskey'] = sesskey();

                $before = self::session_snapshot();
                $result = external_api::call_external_function($name, $args, true);
                $after = self::session_snapshot();

                $this->assertFalse($result['error'], "$name #$index: " . json_encode($result['exception'] ?? null));
                // The control: the call found the learner's plan, so its whole path ran.
                $this->assertSame(1, (int) $result['data']['totalplans'], "$name #$index");
                if ($args['loadgroup'] === 'competency') {
                    $this->assertGreaterThan(0, (int) $result['data']['totalcompetencies'], "$name #$index");
                } else {
                    $planids = array_map('intval', array_column($result['data']['plancards'], 'id'));
                    $this->assertContains($planid, $planids, "$name #$index");
                }
                $this->assertSame([], self::changed_keys($before, $after), "$name #$index changed \$SESSION");
            }
        }
    }
}
