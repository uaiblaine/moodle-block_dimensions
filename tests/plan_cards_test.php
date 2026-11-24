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
use block_dimensions\external\get_block_dataset;
use block_dimensions\local\dataset_provider;
use core\exception\coding_exception;
use core_competency\api;
use core_competency\plan;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * PHPUnit tests for the plan cards another plugin asks the block for.
 *
 * @package    block_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\block_dimensions\plan_cards::class)]
#[CoversClass(\block_dimensions\local\dataset_provider::class)]
final class plan_cards_test extends advanced_testcase {
    /**
     * Seed a learner with one plan in each status, and let the learner read own drafts.
     *
     * The names sort in the order the plans are created, which is the order core lists them in.
     *
     * @return array{user: \stdClass, plans: array<string, int>} The learner, and plan ids keyed by status name.
     */
    protected function seed_plans(): array {
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
        $generator = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $statuses = [
            'draft' => plan::STATUS_DRAFT,
            'active' => plan::STATUS_ACTIVE,
            'waiting' => plan::STATUS_WAITING_FOR_REVIEW,
            'inreview' => plan::STATUS_IN_REVIEW,
            'complete' => plan::STATUS_COMPLETE,
        ];
        $plans = [];
        $i = 0;
        foreach ($statuses as $name => $status) {
            $plan = $generator->create_plan([
                'userid' => $user->id,
                'status' => $status,
                'name' => 'Plan ' . ++$i . ' ' . $name,
            ]);
            $plans[$name] = (int) $plan->get('id');
        }

        return ['user' => $user, 'plans' => $plans];
    }

    /**
     * Create a viewer who holds plan capabilities in a learner's user context.
     *
     * @param int $learnerid The learner whose context the capabilities are assigned in.
     * @param string[] $capabilities The capabilities to allow.
     * @return \stdClass The viewer.
     */
    protected function create_viewer(int $learnerid, array $capabilities): \stdClass {
        $viewer = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role();
        $context = \context_user::instance($learnerid);
        foreach ($capabilities as $capability) {
            assign_capability($capability, CAP_ALLOW, $roleid, $context->id, true);
        }
        role_assign($roleid, $viewer->id, $context->id);
        accesslib_clear_all_caches_for_unit_testing();

        return $viewer;
    }

    /**
     * A viewer core refuses gets nothing and no exception.
     */
    public function test_a_viewer_core_refuses_gets_nothing(): void {
        $this->resetAfterTest();
        $seed = $this->seed_plans();
        $stranger = $this->getDataGenerator()->create_user();

        $this->setUser($stranger);
        $this->assertSame([], plan_cards::for_user((int) $seed['user']->id));

        // Control: the learner reads the same plans, so the empty answer is core's refusal.
        $this->setUser($seed['user']);
        $this->assertCount(5, plan_cards::for_user((int) $seed['user']->id));
    }

    /**
     * Another viewer's list follows core's draft rule: draft statuses need the draft capability.
     */
    public function test_drafts_follow_cores_rule_for_another_viewer(): void {
        $this->resetAfterTest();
        $seed = $this->seed_plans();
        $learnerid = (int) $seed['user']->id;
        $plans = $seed['plans'];

        $viewer = $this->create_viewer($learnerid, ['moodle/competency:planview']);
        $this->setUser($viewer);
        $cards = plan_cards::for_user($learnerid);
        $this->assertEqualsCanonicalizing([$plans['active'], $plans['complete']], array_keys($cards));

        // Control: with the draft capability too, every plan is listed.
        $viewer = $this->create_viewer($learnerid, ['moodle/competency:planview', 'moodle/competency:planviewdraft']);
        $this->setUser($viewer);
        $this->assertEqualsCanonicalizing(array_values($plans), array_keys(plan_cards::for_user($learnerid)));
    }

    /**
     * With competencies disabled the answer is empty.
     */
    public function test_competencies_disabled_gives_nothing(): void {
        $this->resetAfterTest();
        $seed = $this->seed_plans();
        $this->setUser($seed['user']);
        $this->assertNotEmpty(plan_cards::for_user((int) $seed['user']->id));

        set_config('enabled', 0, 'core_competency');

        $this->assertSame([], plan_cards::for_user((int) $seed['user']->id));
    }

    /**
     * Every status gets a card, keyed by plan id in core's order, with the keys the web service allows.
     */
    public function test_every_status_gets_a_card_in_cores_order(): void {
        $this->resetAfterTest();
        $seed = $this->seed_plans();
        $this->setUser($seed['user']);
        $userid = (int) $seed['user']->id;

        $cards = plan_cards::for_user($userid);

        $expected = array_map(
            static fn($plan) => (int) $plan->get('id'),
            array_values(api::list_user_plans($userid))
        );
        $this->assertCount(5, $expected);
        $this->assertSame($expected, array_keys($cards));

        // Control: a card the block builds in a bucket has the same keys, none added and none lost.
        $reference = (new dataset_provider($userid))->get_dataset(false, '', dataset_provider::BUCKET_COMPLETE)['plancards'];
        $this->assertCount(1, $reference);
        $referencekeys = array_keys($reference[0]);
        sort($referencekeys);
        foreach ($cards as $planid => $card) {
            $keys = array_keys($card);
            sort($keys);
            $this->assertSame($referencekeys, $keys, 'Card keys of plan ' . $planid);
        }
    }

    /**
     * An active plan on a competencies-mode template gets a card here, and none from the block's bucket.
     */
    public function test_an_active_plan_on_a_competencies_template_gets_a_card(): void {
        $this->resetAfterTest();
        $seed = $this->seed_plans();
        $this->setUser($seed['user']);
        $userid = (int) $seed['user']->id;
        $generator = $this->getDataGenerator()->get_plugin_generator('core_competency');
        // A template without the display mode field renders its competencies, which is the default mode.
        $template = $generator->create_template();
        $plan = $generator->create_plan([
            'userid' => $userid,
            'templateid' => $template->get('id'),
            'status' => plan::STATUS_ACTIVE,
            'name' => 'Plan 6 competencies',
        ]);
        $planid = (int) $plan->get('id');

        $this->assertArrayHasKey($planid, plan_cards::for_user($userid));

        // Control: the active bucket draws no plan card for it.
        $bucket = (new dataset_provider($userid))->get_dataset(false, '', dataset_provider::BUCKET_ACTIVE)['plancards'];
        $this->assertNotContains($planid, array_column($bucket, 'id'));
        $this->assertContains($seed['plans']['active'], array_column($bucket, 'id'));
    }

    /**
     * A draft's card carries the draft chip and the view wording.
     */
    public function test_a_draft_card_reads_draft_and_view(): void {
        $this->resetAfterTest();
        $seed = $this->seed_plans();
        $this->setUser($seed['user']);

        $cards = plan_cards::for_user((int) $seed['user']->id);
        $draft = $cards[$seed['plans']['draft']];

        $this->assertSame(get_string('planstatusdraft', 'block_dimensions'), $draft['statuslabel']);
        $this->assertTrue($draft['hasstatuslabel']);
        $this->assertFalse($draft['iscompleteplan']);
        $this->assertFalse($draft['isreviewplan']);
        $this->assertSame(get_string('viewplancard', 'block_dimensions'), $draft['buttonlabel']);
        $this->assertSame(
            get_string('viewplancardaria', 'block_dimensions', $draft['name']),
            $draft['buttonarialabel']
        );
    }

    /**
     * No card offers the star, even for a favourite plan.
     */
    public function test_no_card_offers_the_favourite_star(): void {
        $this->resetAfterTest();
        $seed = $this->seed_plans();
        $this->setUser($seed['user']);
        $userid = (int) $seed['user']->id;
        $planid = $seed['plans']['active'];
        set_config('enable_favourites', 1, 'block_dimensions');
        $this->assertTrue(dataset_provider::is_favourites_enabled());
        $usercontext = \context_user::instance($userid);
        \core_favourites\service_factory::get_service_for_user_context($usercontext)
            ->create_favourite('block_dimensions', 'plan', $planid, $usercontext);

        foreach (plan_cards::for_user($userid) as $card) {
            $this->assertFalse($card['showfavourite']);
            $this->assertFalse($card['isfavourite']);
        }

        // Control: the block's own bucket draws the star, pressed, for the same plan.
        $bucket = (new dataset_provider($userid))->get_dataset(false, '', dataset_provider::BUCKET_ACTIVE)['plancards'];
        $own = array_values(array_filter($bucket, static fn($card) => (int) $card['id'] === $planid));
        $this->assertCount(1, $own);
        $this->assertTrue($own[0]['showfavourite']);
        $this->assertTrue($own[0]['isfavourite']);
    }

    /**
     * The layout argument overrides the site setting, and an unknown one is refused.
     */
    public function test_the_layout_argument_overrides_the_site_setting(): void {
        $this->resetAfterTest();
        $seed = $this->seed_plans();
        $this->setUser($seed['user']);
        $userid = (int) $seed['user']->id;
        $planid = $seed['plans']['active'];

        set_config('plancard_layout', 'vertical', 'block_dimensions');
        $this->assertSame(plan_cards::LAYOUT_VERTICAL, plan_cards::site_layout());
        $this->assertSame(plan_cards::item_classes('vertical'), plan_cards::item_classes(''));
        $card = plan_cards::for_user($userid)[$planid];
        $this->assertTrue($card['isvertical']);
        $this->assertFalse($card['ishorizontal']);
        $card = plan_cards::for_user($userid, 'horizontal')[$planid];
        $this->assertTrue($card['ishorizontal']);
        $this->assertFalse($card['isvertical']);

        set_config('plancard_layout', 'horizontal', 'block_dimensions');
        $this->assertSame(plan_cards::LAYOUT_HORIZONTAL, plan_cards::site_layout());
        $this->assertSame(plan_cards::item_classes('horizontal'), plan_cards::item_classes(''));
        $card = plan_cards::for_user($userid)[$planid];
        $this->assertTrue($card['ishorizontal']);
        $this->assertFalse($card['isvertical']);
        $card = plan_cards::for_user($userid, 'vertical')[$planid];
        $this->assertTrue($card['isvertical']);
        $this->assertFalse($card['ishorizontal']);

        $this->assertNotSame(plan_cards::item_classes('vertical'), plan_cards::item_classes('horizontal'));
    }

    /**
     * An unknown layout is refused by each method that takes one.
     */
    public function test_an_unknown_layout_throws(): void {
        $this->resetAfterTest();
        $seed = $this->seed_plans();
        $this->setUser($seed['user']);

        try {
            plan_cards::for_user((int) $seed['user']->id, 'diagonal');
            $this->fail('for_user() accepted an unknown layout.');
        } catch (coding_exception $e) {
            $this->assertStringContainsString('diagonal', $e->getMessage());
        }

        // Control: the same call with a known layout answers.
        $this->assertNotEmpty(plan_cards::for_user((int) $seed['user']->id, 'horizontal'));

        $this->expectException(coding_exception::class);
        plan_cards::item_classes('diagonal');
    }

    /**
     * Somebody else's list reads "View plan", even for an active plan.
     */
    public function test_another_users_list_reads_view_plan(): void {
        $this->resetAfterTest();
        $seed = $this->seed_plans();
        $learnerid = (int) $seed['user']->id;
        $planid = $seed['plans']['active'];

        $viewer = $this->create_viewer($learnerid, ['moodle/competency:planview', 'moodle/competency:planviewdraft']);
        $this->setUser($viewer);
        $card = plan_cards::for_user($learnerid)[$planid];
        $this->assertSame(get_string('viewplancard', 'block_dimensions'), $card['buttonlabel']);
        $this->assertSame(get_string('viewplancardaria', 'block_dimensions', $card['name']), $card['buttonarialabel']);

        // Control: on the learner's own list the same plan keeps the active wording.
        $this->setUser($seed['user']);
        $card = plan_cards::for_user($learnerid)[$planid];
        $this->assertContains(
            $card['buttonlabel'],
            [get_string('accesscard', 'block_dimensions'), get_string('continuecard', 'block_dimensions')]
        );
    }

    /**
     * The item classes are the ones filters.js writes.
     */
    public function test_item_classes_are_what_the_client_writes(): void {
        $source = (string) file_get_contents(__DIR__ . '/../amd/src/filters.js');
        $found = preg_match(
            "~li\.className = '([^']*)' \+\s*\(card\.ishorizontal \? '([^']*)' : '([^']*)'\)~",
            $source,
            $parts
        );
        $this->assertSame(1, $found, 'filters.js no longer builds the plan item classes this way.');

        $this->assertSame($parts[1] . $parts[2], plan_cards::item_classes(plan_cards::LAYOUT_HORIZONTAL));
        $this->assertSame($parts[1] . $parts[3], plan_cards::item_classes(plan_cards::LAYOUT_VERTICAL));
    }

    /**
     * The card's template renders from PHP in both layouts, without a favourite button.
     */
    public function test_the_template_renders_from_php(): void {
        global $PAGE;
        $this->resetAfterTest();
        $seed = $this->seed_plans();
        $this->setUser($seed['user']);
        $userid = (int) $seed['user']->id;
        $planid = $seed['plans']['active'];
        set_config('enable_favourites', 1, 'block_dimensions');

        foreach ([plan_cards::LAYOUT_VERTICAL, plan_cards::LAYOUT_HORIZONTAL] as $layout) {
            $card = plan_cards::for_user($userid, $layout)[$planid];
            $html = $PAGE->get_renderer('core')->render_from_template(plan_cards::TEMPLATE, $card);

            $this->assertStringContainsString($card['name'], $html);
            $this->assertStringContainsString('/local/dimensions/view-plan.php?id=' . $planid, $html);
            $this->assertStringNotContainsString('data-fav-id', $html);
        }
    }

    /**
     * Every key of a card is one the web service's plan card structure declares.
     */
    public function test_card_keys_stay_inside_the_web_service_allowlist(): void {
        $this->resetAfterTest();
        $seed = $this->seed_plans();
        $this->setUser($seed['user']);

        $allowed = array_keys(get_block_dataset::execute_returns()->keys['plancards']->content->keys);
        $this->assertNotEmpty($allowed);
        foreach (plan_cards::for_user((int) $seed['user']->id) as $planid => $card) {
            $this->assertSame([], array_diff(array_keys($card), $allowed), 'Keys of plan ' . $planid);
        }
    }
}
