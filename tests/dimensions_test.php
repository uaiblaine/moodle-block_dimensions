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

namespace block_dimensions;

use advanced_testcase;
use block_dimensions;
use context_course;
use context_system;
use core_competency\api;
use core_competency\plan;

/**
 * PHPUnit block_dimensions tests
 *
 * @package    block_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \block_dimensions
 * @covers \block_dimensions\output\renderer
 * @covers \block_dimensions\output\summary
 */
final class dimensions_test extends advanced_testcase {
    public static function setUpBeforeClass(): void {
        require_once(__DIR__ . '/../../moodleblock.class.php');
        require_once(__DIR__ . '/../block_dimensions.php');
        parent::setUpBeforeClass();
    }

    /**
     * Test the behaviour of can_block_be_added() method.
     */
    public function test_can_block_be_added(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        // Create a course and prepare the page where the block will be added.
        $course = $this->getDataGenerator()->create_course();
        $page = new \moodle_page();
        $page->set_context(context_course::instance($course->id));
        $page->set_pagelayout('course');

        $block = new block_dimensions();

        // If competencies advanced feature is enabled, the method should return true.
        set_config('enabled', true, 'core_competency');
        $this->assertTrue($block->can_block_be_added($page));

        // However, if the competencies advanced feature is disabled, the method should return false.
        set_config('enabled', false, 'core_competency');
        $this->assertFalse($block->can_block_be_added($page));
    }

    /**
     * The block is available on the site front page, inside a course, and on the dashboard.
     */
    public function test_applicable_formats(): void {
        $block = new block_dimensions();
        $this->assertSame(['site' => true, 'course' => true, 'my' => true], $block->applicable_formats());
    }

    /**
     * This block has a global settings page.
     */
    public function test_has_config(): void {
        $block = new block_dimensions();
        $this->assertTrue($block->has_config());
    }

    /**
     * specialization() blanks the title only while hide_block_title is on.
     *
     * Control: with the setting at its shipped default (off), the instance keeps the title
     * init() set from the pluginname string. Without that control, a mutation that always blanks
     * the title would still pass the "hidden" assertion below and the test would prove nothing
     * about the setting actually gating it.
     */
    public function test_specialization_hides_title_when_hide_block_title_is_enabled(): void {
        $this->resetAfterTest();

        $default = $this->create_block_instance();
        $this->assertSame(get_string('pluginname', 'block_dimensions'), $default->title);

        set_config('hide_block_title', 1, 'block_dimensions');
        $hidden = $this->create_block_instance();
        $this->assertSame('', $hidden->title);
    }

    /**
     * get_content() short-circuits before any plan work when core_competency is disabled, and a
     * second call returns the exact same object rather than rebuilding it.
     *
     * Control: the user holds an active plan that WOULD render the block if the disabled-feature
     * guard were skipped (proved by enabling the feature first and checking it renders) - without
     * a plan to render, a mutation deleting that guard would still leave the content empty and
     * this test would pass having tested nothing.
     */
    public function test_get_content_short_circuits_and_memoises_when_competency_is_disabled(): void {
        $this->resetAfterTest();
        set_config('enabled', true, 'core_competency');

        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->get_plugin_generator('core_competency')->create_plan([
            'userid' => $user->id,
            'status' => plan::STATUS_ACTIVE,
        ]);
        $this->setUser($user);

        // Control: with the feature enabled, this same plan does reach the rendered block.
        $this->assert_block_rendered($this->create_block_instance(), true);

        set_config('enabled', false, 'core_competency');
        $block = $this->create_block_instance();

        $first = $block->get_content();
        $this->assertEquals(new \stdClass(), $first);

        $second = $block->get_content();
        $this->assertSame($first, $second);
    }

    /**
     * Every plan status, and whether a viewer holding only that plan gets a rendered block.
     *
     * The block renders a plan in any of its three status buckets - active, in review (which
     * carries both review statuses) and completed. A plain draft belongs to no bucket, so it
     * opens nothing.
     *
     * @return array
     */
    public static function plan_status_provider(): array {
        return [
            'draft' => [plan::STATUS_DRAFT, false],
            'waiting for review' => [plan::STATUS_WAITING_FOR_REVIEW, true],
            'in review' => [plan::STATUS_IN_REVIEW, true],
            'complete' => [plan::STATUS_COMPLETE, true],
            'active' => [plan::STATUS_ACTIVE, true],
        ];
    }

    /**
     * The block renders for a viewer holding a plan in one of its status buckets.
     *
     * The viewer is allowed to read their own drafts, and the test asserts that the plan reaches
     * the plan list, so the draft case is excluded for its status and not merely hidden by a
     * capability - without that, it would pass having tested nothing.
     *
     * @dataProvider plan_status_provider
     * @param int $status Status of the only plan the viewer holds.
     * @param bool $rendered Whether the block is expected to render.
     */
    public function test_get_content_follows_the_plan_status(int $status, bool $rendered): void {
        global $CFG;
        $this->resetAfterTest();
        set_config('enabled', true, 'core_competency');

        assign_capability(
            'moodle/competency:planviewowndraft',
            CAP_ALLOW,
            $CFG->defaultuserroleid,
            context_system::instance()->id,
            true
        );
        accesslib_clear_all_caches_for_unit_testing();

        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->get_plugin_generator('core_competency')->create_plan([
            'userid' => $user->id,
            'status' => $status,
        ]);
        $block = $this->create_block_instance();
        $this->setUser($user);

        $this->assertCount(1, api::list_user_plans($user->id));
        $this->assert_block_rendered($block, $rendered);
    }

    /**
     * Another user's active plan does not render the block for a viewer who holds none.
     */
    public function test_get_content_ignores_other_users_plans(): void {
        $this->resetAfterTest();
        set_config('enabled', true, 'core_competency');

        $owner = $this->getDataGenerator()->create_user();
        $viewer = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->get_plugin_generator('core_competency')->create_plan([
            'userid' => $owner->id,
            'status' => plan::STATUS_ACTIVE,
        ]);
        $record = $this->getDataGenerator()->create_block('dimensions');

        // Control: the owner does get the block, so the plan is one the block would render.
        $this->setUser($owner);
        $this->assert_block_rendered($this->create_block_instance($record), true);

        $this->setUser($viewer);
        $this->assert_block_rendered($this->create_block_instance($record), false);
    }

    /**
     * An active plan the viewer may not read does not render the block.
     *
     * The web service builds its dataset from the same plan list, so the block must not render a
     * shell whose dataset would come back empty.
     */
    public function test_get_content_needs_a_plan_the_viewer_can_read(): void {
        global $CFG;
        $this->resetAfterTest();
        set_config('enabled', true, 'core_competency');

        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->get_plugin_generator('core_competency')->create_plan([
            'userid' => $user->id,
            'status' => plan::STATUS_ACTIVE,
        ]);
        $record = $this->getDataGenerator()->create_block('dimensions');
        $this->setUser($user);

        // Control: with the default capabilities the plan renders the block.
        $this->assert_block_rendered($this->create_block_instance($record), true);

        assign_capability(
            'moodle/competency:planviewown',
            CAP_PROHIBIT,
            $CFG->defaultuserroleid,
            context_system::instance()->id,
            true
        );
        accesslib_clear_all_caches_for_unit_testing();

        $this->assert_block_rendered($this->create_block_instance($record), false);
    }

    /**
     * Load a Dimensions block instance on a system-context page, as the block manager does.
     *
     * @param \stdClass|null $record Existing block_instances record, or null to create one.
     * @return block_dimensions
     */
    protected function create_block_instance(?\stdClass $record = null): block_dimensions {
        if ($record === null) {
            $record = $this->getDataGenerator()->create_block('dimensions');
        }
        $page = new \moodle_page();
        $page->set_context(context_system::instance());

        return block_instance('dimensions', $record, $page);
    }

    /**
     * Assert whether the block renders, through is_empty() - the check core uses to drop a block.
     *
     * @param block_dimensions $block A block instance whose content has not been built yet.
     * @param bool $rendered Whether the block is expected to render.
     */
    protected function assert_block_rendered(block_dimensions $block, bool $rendered): void {
        if ($rendered) {
            $this->assertFalse($block->is_empty());
            $this->assertStringContainsString('block-dimensions-', $block->get_content()->text);
        } else {
            $this->assertTrue($block->is_empty());
            $this->assertSame('', $block->get_content()->text ?? '');
        }
    }
}
