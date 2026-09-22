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

/**
 * PHPUnit tests for get_block_dataset external endpoint.
 *
 * @package    block_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \block_dimensions\external\get_block_dataset
 */
final class get_block_dataset_test extends advanced_testcase {
    /**
     * Guest user should be rejected with moodle_exception.
     *
     * @covers ::execute
     */
    public function test_execute_throws_for_guest_user(): void {
        $this->resetAfterTest();
        $this->setGuestUser();

        $this->expectException(\moodle_exception::class);
        get_block_dataset::execute(false);
    }

    /**
     * When core_competency is disabled the endpoint should return a zero-item dataset.
     *
     * @covers ::execute
     */
    public function test_execute_returns_empty_dataset_when_competencies_disabled(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        set_config('enabled', 0, 'core_competency');

        $result = get_block_dataset::execute(false);

        $this->assertFalse($result['hasactiveplans']);
        $this->assertFalse($result['hasplancards']);
        $this->assertFalse($result['hascompetencies']);
        $this->assertSame([], $result['plancards']);
        $this->assertSame([], $result['competencycards']);
        $this->assertSame(0, $result['totalplans']);
        $this->assertSame(0, $result['totalcompetencies']);
    }

    /**
     * Logged-in user with competencies enabled should receive a dataset with expected keys.
     *
     * @covers ::execute
     */
    public function test_execute_returns_expected_keys_for_logged_in_user(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        set_config('enabled', 1, 'core_competency');

        $result = get_block_dataset::execute(false);

        $this->assertArrayHasKey('hasactiveplans', $result);
        $this->assertArrayHasKey('plancards', $result);
        $this->assertArrayHasKey('competencycards', $result);
        $this->assertArrayHasKey('totalplans', $result);
        $this->assertArrayHasKey('totalcompetencies', $result);
        $this->assertArrayHasKey('favouritesenabled', $result);
        $this->assertArrayHasKey('filtersettings', $result);
        $this->assertArrayHasKey('planstatus', $result);
        $this->assertArrayHasKey('plancounts', $result);
    }

    /**
     * Set one of the sibling plugin's template custom fields.
     *
     * The save runs as the admin and restores the current user afterwards: instance_form_save()
     * silently drops every field the current user cannot edit, so a fixture that saves as the
     * learner writes nothing at all and the test that depends on it proves nothing.
     *
     * @param int $templateid Learning plan template id.
     * @param string $shortname Custom field shortname.
     * @param mixed $value Value to store.
     * @return void
     */
    protected function set_template_field(int $templateid, string $shortname, $value): void {
        global $USER;

        $previous = $USER;
        $this->setAdminUser();
        \local_dimensions\helper::ensure_custom_fields_exist(\local_dimensions\helper::AREA_LP);
        \local_dimensions\customfield\lp_handler::create()->instance_form_save((object) [
            'id' => $templateid,
            'customfield_' . $shortname => $value,
        ], true);
        $this->setUser($previous);
    }

    /**
     * Seed one learner with a plan in every status the block shows, plus a draft.
     *
     * @return \stdClass The learner.
     */
    protected function seed_plans_in_every_status(): \stdClass {
        global $CFG;

        set_config('enabled', 1, 'core_competency');
        assign_capability(
            'moodle/competency:planviewowndraft',
            CAP_ALLOW,
            $CFG->defaultuserroleid,
            \context_system::instance()->id,
            true
        );
        accesslib_clear_all_caches_for_unit_testing();

        $user = $this->getDataGenerator()->create_user();
        $competency = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $statuses = [
            plan::STATUS_DRAFT,
            plan::STATUS_ACTIVE,
            plan::STATUS_WAITING_FOR_REVIEW,
            plan::STATUS_IN_REVIEW,
            plan::STATUS_COMPLETE,
        ];
        foreach ($statuses as $i => $status) {
            $competency->create_plan([
                'userid' => $user->id,
                'status' => $status,
                'name' => 'Plan ' . $i,
            ]);
        }

        return $user;
    }

    /**
     * Every bucket's count rides the first response, whichever bucket's cards it carries.
     *
     * @covers ::execute
     */
    public function test_execute_counts_every_bucket_while_building_one(): void {
        $this->resetAfterTest();
        $this->setUser($this->seed_plans_in_every_status());

        $result = get_block_dataset::execute(false);

        $this->assertSame('active', $result['planstatus']);
        $this->assertSame(['active' => 1, 'review' => 2, 'complete' => 1], $result['plancounts']);
        $this->assertCount(1, $result['plancards']);
        $this->assertTrue($result['hasactiveplans']);
    }

    /**
     * A non-active bucket builds its own cards: chip, no favourite toggle, read-only button.
     *
     * @covers ::execute
     */
    public function test_execute_builds_the_requested_bucket(): void {
        $this->resetAfterTest();
        $this->setUser($this->seed_plans_in_every_status());

        $complete = get_block_dataset::execute(false, '', 'complete');
        $this->assertSame('complete', $complete['planstatus']);
        $this->assertCount(1, $complete['plancards']);
        $card = $complete['plancards'][0];
        $this->assertTrue($card['iscompleteplan']);
        $this->assertFalse($card['isreviewplan']);
        $this->assertTrue($card['hasstatuslabel']);
        $this->assertFalse($card['showfavourite']);
        $this->assertSame(get_string('viewplancard', 'block_dimensions'), $card['buttonlabel']);
        // The counts do not move with the bucket, and hasactiveplans keeps meaning what it says.
        $this->assertSame(['active' => 1, 'review' => 2, 'complete' => 1], $complete['plancounts']);
        $this->assertTrue($complete['hasactiveplans']);

        $review = get_block_dataset::execute(false, '', 'review');
        $this->assertCount(2, $review['plancards']);
        $this->assertTrue($review['plancards'][0]['isreviewplan']);
        $this->assertFalse($review['plancards'][0]['iscompleteplan']);

        // Control: the active bucket keeps the favourite toggle and its own wording.
        $active = get_block_dataset::execute(false, '', 'active');
        $this->assertTrue($active['plancards'][0]['showfavourite']);
        $this->assertFalse($active['plancards'][0]['hasstatuslabel']);
    }

    /**
     * The active pill counts the plans the grid will actually show, not every active plan.
     *
     * A competencies-mode template becomes competency cards in the section below, so counting it
     * as a plan card would put a number on the pill that the grid never reaches - and disagree
     * with the "Show all" count beside it.
     *
     * @covers ::execute
     */
    public function test_active_count_excludes_a_plan_that_renders_as_competencies(): void {
        $this->resetAfterTest();
        $user = $this->seed_plans_in_every_status();
        $competency = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $template = $competency->create_template();
        $this->set_template_field(
            (int) $template->get('id'),
            \local_dimensions\constants::CFIELD_DISPLAYMODE,
            \local_dimensions\constants::DISPLAYMODE_COMPETENCIES
        );
        $competency->create_plan([
            'userid' => $user->id,
            'templateid' => $template->get('id'),
            'status' => plan::STATUS_ACTIVE,
        ]);
        $this->setUser($user);

        $result = get_block_dataset::execute(false);

        // Two active plans exist; only the one that renders a plan card is counted.
        $active = array_filter(\core_competency\api::list_user_plans($user->id), static function ($plan) {
            return (int) $plan->get('status') === plan::STATUS_ACTIVE;
        });
        $this->assertCount(2, $active);
        $this->assertSame(1, $result['plancounts']['active']);
        $this->assertCount(1, $result['plancards']);
    }

    /**
     * The tag pills a card draws survive the return allowlist.
     *
     * The provider builds `tags`/`hastags` and both card templates render them, but
     * clean_returnvalue() strips whatever the structure does not declare - silently, with the
     * pills simply never appearing. Nothing else in the pipeline reads a template variable.
     *
     * @covers ::execute_returns
     */
    public function test_card_tag_pills_survive_the_return_allowlist(): void {
        $this->resetAfterTest();
        $user = $this->seed_plans_in_every_status();
        $competency = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $template = $competency->create_template();
        /* The template must render a plan card, which is the display mode it does NOT default to:
           without the field a template renders its competencies instead, and the fixture would
           produce no plan card at all to carry a tag. */
        $this->set_template_field(
            (int) $template->get('id'),
            \local_dimensions\constants::CFIELD_DISPLAYMODE,
            \local_dimensions\constants::DISPLAYMODE_PLAN
        );
        $this->set_template_field((int) $template->get('id'), \local_dimensions\constants::CFIELD_TAG1, 1);
        $competency->create_plan([
            'userid' => $user->id,
            'templateid' => $template->get('id'),
            'status' => plan::STATUS_ACTIVE,
        ]);
        $this->setUser($user);

        $raw = get_block_dataset::execute(false);
        $tagged = null;
        foreach ($raw['plancards'] as $card) {
            if (!empty($card['hastags'])) {
                $tagged = $card;
            }
        }
        $this->assertNotNull($tagged, 'the fixture must produce a tagged card, or this proves nothing');

        $clean = \core_external\external_api::clean_returnvalue(get_block_dataset::execute_returns(), $raw);
        $cleaned = null;
        foreach ($clean['plancards'] as $card) {
            if ($card['id'] === $tagged['id']) {
                $cleaned = $card;
            }
        }
        $this->assertNotNull($cleaned);
        $this->assertTrue($cleaned['hastags']);
        $this->assertSame($tagged['tags'], $cleaned['tags']);
    }

    /**
     * With no active plan the block opens on the first bucket that has one.
     *
     * A learner whose plans have all finished must land on them rather than on an empty Active
     * bucket with a notice - the reason the render gate was widened beyond active plans at all.
     *
     * @covers ::execute
     */
    public function test_execute_opens_on_the_first_bucket_with_plans(): void {
        $this->resetAfterTest();
        set_config('enabled', 1, 'core_competency');
        $competency = $this->getDataGenerator()->get_plugin_generator('core_competency');

        // Control: a learner who does hold an active plan opens on the active bucket.
        $active = $this->getDataGenerator()->create_user();
        $competency->create_plan(['userid' => $active->id, 'status' => plan::STATUS_ACTIVE]);
        $competency->create_plan(['userid' => $active->id, 'status' => plan::STATUS_COMPLETE]);
        $this->setUser($active);
        $opened = get_block_dataset::execute(false);
        $this->assertSame('active', $opened['planstatus']);
        $this->assertCount(1, $opened['plancards']);

        $finished = $this->getDataGenerator()->create_user();
        $competency->create_plan([
            'userid' => $finished->id,
            'status' => plan::STATUS_COMPLETE,
            'name' => 'New Educator Induction',
        ]);
        $this->setUser($finished);

        $result = get_block_dataset::execute(false);

        $this->assertSame('complete', $result['planstatus']);
        $this->assertSame(['active' => 0, 'review' => 0, 'complete' => 1], $result['plancounts']);
        $this->assertCount(1, $result['plancards']);
        $this->assertSame('New Educator Induction', $result['plancards'][0]['name']);
    }

    /**
     * A bucket name the block does not know is refused rather than quietly served as active.
     *
     * @covers ::execute
     */
    public function test_execute_refuses_an_unknown_bucket(): void {
        $this->resetAfterTest();
        $this->setUser($this->seed_plans_in_every_status());

        $this->expectException(\invalid_parameter_exception::class);
        get_block_dataset::execute(false, '', 'draft');
    }
}
