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

namespace block_dimensions\local;

use advanced_testcase;
use core_competency\plan;
use local_dimensions\constants;

/**
 * PHPUnit tests for dataset provider helper behavior.
 *
 * @package    block_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \block_dimensions\local\dataset_provider
 */
final class dataset_provider_test extends advanced_testcase {
    /**
     * Build a test double exposing protected helper methods as public test_*() proxies.
     *
     * The constructor is skipped, so the plan list stays empty until test_set_plans() fills it,
     * and the competency fetchers and build_competency_card() are stubbed. A template id given a
     * display mode through test_set_display_mode() resolves to it without reading any metadata.
     *
     * @return object
     */
    protected function get_provider_double() {
        return new class extends dataset_provider {
            /** @var array Stubbed competency payload for fetch_plan_competencies_api(). */
            protected array $stubbedcompetencies = [];
            /** @var array Stubbed courses map for get_competencies_with_courses. */
            protected array $stubbedcourses = [];
            /** @var array Stubbed display modes, keyed by template id. */
            protected array $stubbeddisplaymodes = [];

            /**
             * Constructor.
             */
            public function __construct() {
                // Do not call parent constructor in pure helper tests.
            }

            /**
             * Make a template id resolve to a display mode without template metadata.
             *
             * @param int $templateid Template id a fake plan reports.
             * @param int $displaymode One of the local_dimensions DISPLAYMODE_* constants.
             * @return void
             */
            public function test_set_display_mode(int $templateid, int $displaymode): void {
                $this->stubbeddisplaymodes[$templateid] = $displaymode;
            }

            /**
             * Resolve a stubbed template id without reading metadata.
             *
             * @param int|null $templateid Template id.
             * @return array
             */
            protected function resolve_plan_display_context(?int $templateid): array {
                if ($templateid && isset($this->stubbeddisplaymodes[$templateid])) {
                    return [[], $this->stubbeddisplaymodes[$templateid]];
                }

                return parent::resolve_plan_display_context($templateid);
            }

            /**
             * Skip the batch read: the fake plans' template ids exist nowhere.
             *
             * @param array $plans Plans.
             * @return void
             */
            protected function prefetch_template_metadata(array $plans): void {
            }

            public function test_get_trail_start_index(
                int $total,
                int $lastcompletedindex,
                int $maxitems = 5
            ): int {
                return $this->get_trail_start_index($total, $lastcompletedindex, $maxitems);
            }

            public function test_select_trail_competencies(array $competencies, int $lastcompletedindex): array {
                return $this->select_trail_competencies($competencies, $lastcompletedindex);
            }

            public function test_has_partial_trail(array $competencytrail): bool {
                return $this->has_partial_trail($competencytrail);
            }

            public function test_set_plans(array $plans): void {
                $this->plans = $plans;
            }

            public function test_get_plans_in_bucket(string $bucket): array {
                return $this->get_plans_in_bucket($bucket);
            }

            public function test_get_eligible_competency_ids(
                array $competencies,
                array $seencompetencies,
                array $competencieswithcourses
            ): array {
                return $this->get_eligible_competency_ids($competencies, $seencompetencies, $competencieswithcourses);
            }

            public function test_get_ids_to_process(
                array $eligibleids,
                bool $favouritesonly,
                array $compfavids
            ): array {
                return $this->get_ids_to_process($eligibleids, $favouritesonly, $compfavids);
            }

            public function test_build_trail_competency_data(int $planid, array $trailcompetencies): array {
                return $this->build_trail_competency_data($planid, $trailcompetencies);
            }

            public function test_skip_competency_for_visibility(
                int $competencyid,
                array &$seencompetencies,
                array $competencieswithcourses
            ): bool {
                return $this->skip_competency_for_visibility($competencyid, $seencompetencies, $competencieswithcourses);
            }

            public function test_skip_competency_for_favourites(
                bool $favouritesonly,
                int $competencyid,
                array $compfavids
            ): bool {
                return $this->skip_competency_for_favourites($favouritesonly, $competencyid, $compfavids);
            }

            public function test_process_competency_dataset_item(
                int $planid,
                object $competency,
                bool $favouritesonly,
                array $compfavids,
                array $competencieswithcourses,
                array $bulkmetadata,
                array &$seencompetencies
            ): array {
                return $this->process_competency_dataset_item(
                    $planid,
                    $competency,
                    $favouritesonly,
                    $compfavids,
                    $competencieswithcourses,
                    $bulkmetadata,
                    $seencompetencies
                );
            }

            /**
             * Build a fake competency card payload for helper tests.
             *
             * @param int $planid Plan id.
             * @param int $competencyid Competency id.
             * @param mixed $competency Competency object.
             * @param array|null $metadata Metadata.
             * @return array
             */
            protected function build_competency_card(
                int $planid,
                int $competencyid,
                $competency,
                ?array $metadata = null
            ): array {
                return [
                    'id' => $competencyid,
                    'name' => 'Fake competency',
                    'metadata' => $metadata,
                    'planid' => $planid,
                ];
            }

            /**
             * Return stubbed courses map for unit-test isolation.
             *
             * @param array $competencyids Competency ids.
             * @return array
             */
            protected function get_competencies_with_courses(array $competencyids): array {
                return $this->stubbedcourses ?? [];
            }

            /**
             * Return stubbed competency list for unit-test isolation.
             *
             * @param \core_competency\plan $plan Plan object.
             * @return array
             */
            protected function fetch_plan_competencies_api(\core_competency\plan $plan): array {
                return $this->stubbedcompetencies ?? [];
            }

            /**
             * Return empty bulk metadata for unit-test isolation.
             *
             * @param array $competencyids Competency ids.
             * @return array
             */
            protected function fetch_bulk_competency_metadata(array $competencyids): array {
                return [];
            }

            /**
             * Public proxy for process_plan_competencies using injected stubs.
             *
             * @param int $planid Plan id.
             * @param \core_competency\plan $plan Plan object.
             * @param bool $favouritesonly Whether favourites-only mode is active.
             * @param array $compfavids Favourite competency ids.
             * @param array $seencompetencies Seen competency ids (updated by reference).
             * @param array $stubbedcompetencies Stub competency payload to return.
             * @param array $stubbedcourses Stub courses-with-competencies map.
             * @return array
             */
            public function test_process_plan_competencies_with(
                int $planid,
                \core_competency\plan $plan,
                bool $favouritesonly,
                array $compfavids,
                array &$seencompetencies,
                array $stubbedcompetencies,
                array $stubbedcourses
            ): array {
                $this->stubbedcompetencies = $stubbedcompetencies;
                $this->stubbedcourses = $stubbedcourses;

                return $this->process_plan_competencies(
                    $planid,
                    $plan,
                    $favouritesonly,
                    $compfavids,
                    $seencompetencies
                );
            }

            /**
             * Proxy for get_plan_button_data.
             *
             * @param string $planname Plan name.
             * @param bool $haspartialtrail Whether trail is partial.
             * @param string|null $bucket The plan's status bucket, or null for the active wording.
             * @return array
             */
            public function test_get_plan_button_data(string $planname, bool $haspartialtrail, ?string $bucket = null): array {
                return $this->get_plan_button_data($planname, $haspartialtrail, $bucket);
            }

            /**
             * Proxy for resolve_plan_display_context.
             *
             * @param int|null $templateid Template id.
             * @return array
             */
            public function test_resolve_plan_display_context(?int $templateid): array {
                return $this->resolve_plan_display_context($templateid);
            }

            /**
             * Proxy for build_plan_dataset_card.
             *
             * @param \core_competency\plan $plan Plan object.
             * @param int|null $templateid Template id.
             * @param int $planid Plan id.
             * @param array $templatemetadata Template metadata.
             * @param bool $favouritesonly Whether favourites-only mode is active.
             * @param array $planfavids Plan favourites map.
             * @return array|null
             */
            public function test_build_plan_dataset_card(
                \core_competency\plan $plan,
                ?int $templateid,
                int $planid,
                array $templatemetadata,
                bool $favouritesonly,
                array $planfavids
            ): ?array {
                return $this->build_plan_dataset_card($plan, $templateid, $planid, $templatemetadata, $favouritesonly, $planfavids);
            }

            /**
             * Proxy for sanitize_color.
             *
             * @param string|null $color Raw colour value.
             * @return string|null
             */
            public function test_sanitize_color(?string $color): ?string {
                return $this->sanitize_color($color);
            }

            /**
             * Proxy for sanitize_image_url.
             *
             * @param string|null $url Raw URL value.
             * @return string|null
             */
            public function test_sanitize_image_url(?string $url): ?string {
                return $this->sanitize_image_url($url);
            }
        };
    }

    /**
     * Trail start index should follow edge and centering rules.
     *
     * @covers ::get_trail_start_index
     */
    public function test_get_trail_start_index_edges_and_centering(): void {
        $provider = $this->get_provider_double();

        $this->assertSame(0, $provider->test_get_trail_start_index(4, -1));
        $this->assertSame(0, $provider->test_get_trail_start_index(8, -1));
        $this->assertSame(1, $provider->test_get_trail_start_index(8, 3));
        $this->assertSame(3, $provider->test_get_trail_start_index(8, 7));
    }

    /**
     * Trail selection should keep a 5-item window and set first/last markers.
     *
     * @covers ::select_trail_competencies
     */
    public function test_select_trail_competencies_window_and_markers(): void {
        $provider = $this->get_provider_double();

        $competencies = [];
        for ($i = 1; $i <= 8; $i++) {
            $competencies[] = [
                'id' => $i,
                'shortname' => 'C' . $i,
                'iscompleted' => ($i <= 4),
                'index' => $i - 1,
                'url' => '/local/dimensions/view-competency.php?id=99&competencyid=' . $i,
            ];
        }

        $selected = $provider->test_select_trail_competencies($competencies, 3);

        $this->assertCount(5, $selected);
        $this->assertSame([2, 3, 4, 5, 6], array_column($selected, 'id'));
        $this->assertTrue($selected[0]['isfirst']);
        $this->assertFalse($selected[0]['islast']);
        $this->assertFalse($selected[4]['isfirst']);
        $this->assertTrue($selected[4]['islast']);
    }

    /**
     * Partial trail should only be true when there are both completed and pending items.
     *
     * @covers ::has_partial_trail
     */
    public function test_has_partial_trail_states(): void {
        $provider = $this->get_provider_double();

        $this->assertFalse($provider->test_has_partial_trail([]));
        $this->assertFalse($provider->test_has_partial_trail([
            ['iscompleted' => false],
            ['iscompleted' => false],
        ]));
        $this->assertFalse($provider->test_has_partial_trail([
            ['iscompleted' => true],
            ['iscompleted' => true],
        ]));
        $this->assertTrue($provider->test_has_partial_trail([
            ['iscompleted' => true],
            ['iscompleted' => false],
        ]));
    }

    /**
     * Build a fake plan that reports one status, with no database behind it.
     *
     * A non-zero template id has no metadata behind it either: give it a display mode with the
     * double's test_set_display_mode().
     *
     * @param int $status Status the fake plan reports.
     * @param int $templateid Template id the fake plan reports, 0 for none.
     * @return object
     */
    protected function fake_plan(int $status, int $templateid = 0): object {
        return new class ($status, $templateid) {
            /** @var int Status this fake plan reports. */
            protected $status;

            /** @var int Template id this fake plan reports. */
            protected $templateid;

            /**
             * Constructor.
             *
             * @param int $status Status to report.
             * @param int $templateid Template id to report.
             */
            public function __construct(int $status, int $templateid) {
                $this->status = $status;
                $this->templateid = $templateid;
            }

            /**
             * Get a field from the fake plan object.
             *
             * Every field but status and templateid reads 0. A templateid of 0 means no
             * template, so count_plans_by_bucket() counts an active fake plan as a plan card
             * without reading template metadata.
             *
             * @param string $field Field name.
             * @return int
             */
            public function get(string $field): int {
                if ($field === 'status') {
                    return $this->status;
                }

                return $field === 'templateid' ? $this->templateid : 0;
            }
        };
    }

    /**
     * Read the statuses out of a list of fake plans.
     *
     * @param array $plans Fake plans.
     * @return array
     */
    protected function statuses_of(array $plans): array {
        return array_map(static function ($plan) {
            return $plan->get('status');
        }, $plans);
    }

    /**
     * Each bucket carries its own statuses, and a plain draft belongs to none of them.
     *
     * @covers ::get_plans_in_bucket
     * @covers ::count_plans_by_bucket
     * @covers ::has_displayable_plans
     */
    public function test_status_buckets_partition_the_plan_list(): void {
        $provider = $this->get_provider_double();
        $provider->test_set_plans([
            $this->fake_plan(plan::STATUS_DRAFT),
            $this->fake_plan(plan::STATUS_ACTIVE),
            $this->fake_plan(plan::STATUS_WAITING_FOR_REVIEW),
            $this->fake_plan(plan::STATUS_IN_REVIEW),
            $this->fake_plan(plan::STATUS_COMPLETE),
        ]);

        $this->assertSame(
            [plan::STATUS_ACTIVE],
            $this->statuses_of($provider->test_get_plans_in_bucket(dataset_provider::BUCKET_ACTIVE))
        );
        $this->assertSame(
            [plan::STATUS_WAITING_FOR_REVIEW, plan::STATUS_IN_REVIEW],
            $this->statuses_of($provider->test_get_plans_in_bucket(dataset_provider::BUCKET_REVIEW))
        );
        $this->assertSame(
            [plan::STATUS_COMPLETE],
            $this->statuses_of($provider->test_get_plans_in_bucket(dataset_provider::BUCKET_COMPLETE))
        );
        $this->assertSame(['active' => 1, 'review' => 2, 'complete' => 1], $provider->count_plans_by_bucket());
        $this->assertTrue($provider->has_displayable_plans());
    }

    /**
     * A learner whose only plan is a draft has nothing the block could show.
     *
     * @covers ::count_plans_by_bucket
     * @covers ::has_displayable_plans
     */
    public function test_a_draft_alone_is_not_content(): void {
        $provider = $this->get_provider_double();

        // Control: an active plan in the same double does count.
        $provider->test_set_plans([$this->fake_plan(plan::STATUS_ACTIVE)]);
        $this->assertTrue($provider->has_displayable_plans());

        $provider->test_set_plans([$this->fake_plan(plan::STATUS_DRAFT)]);
        $this->assertFalse($provider->has_displayable_plans());
        $this->assertSame(['active' => 0, 'review' => 0, 'complete' => 0], $provider->count_plans_by_bucket());
    }

    /**
     * An active plan that shows competency cards still opens the block on the active bucket.
     *
     * It draws no plan card, so the active count stays at zero, but its competency cards are built
     * only in the active bucket: opening on the completed plan would leave them out of reach.
     *
     * @covers ::opening_bucket
     * @covers ::has_active_plans
     * @covers ::count_plans_by_bucket
     */
    public function test_an_active_plan_showing_competencies_opens_the_active_bucket(): void {
        $provider = $this->get_provider_double();
        $provider->test_set_display_mode(7, constants::DISPLAYMODE_COMPETENCIES);
        $provider->test_set_plans([
            $this->fake_plan(plan::STATUS_ACTIVE, 7),
            $this->fake_plan(plan::STATUS_COMPLETE),
        ]);

        $this->assertSame(['active' => 0, 'review' => 0, 'complete' => 1], $provider->count_plans_by_bucket());
        $this->assertSame(dataset_provider::BUCKET_ACTIVE, $provider->opening_bucket());
        $this->assertTrue($provider->has_active_plans());

        // Control: with the completed plan alone the block opens on it, and holds no active plan.
        $provider->test_set_plans([$this->fake_plan(plan::STATUS_COMPLETE)]);
        $this->assertSame(dataset_provider::BUCKET_COMPLETE, $provider->opening_bucket());
        $this->assertFalse($provider->has_active_plans());
    }

    /**
     * Eligible competency IDs should exclude seen items and items without visible courses.
     *
     * @covers ::get_eligible_competency_ids
     */
    public function test_get_eligible_competency_ids_filters_by_seen_and_courses(): void {
        $provider = $this->get_provider_double();

        $competencies = [
            (object) ['competency' => new class {
                /**
                 * Get a field from fake competency object.
                 *
                 * @param string $field Field name.
                 * @return int
                 */
                public function get(string $field): int {
                    return ($field === 'id') ? 10 : 0;
                }
            }],
            (object) ['competency' => new class {
                /**
                 * Get a field from fake competency object.
                 *
                 * @param string $field Field name.
                 * @return int
                 */
                public function get(string $field): int {
                    return ($field === 'id') ? 20 : 0;
                }
            }],
            (object) ['competency' => new class {
                /**
                 * Get a field from fake competency object.
                 *
                 * @param string $field Field name.
                 * @return int
                 */
                public function get(string $field): int {
                    return ($field === 'id') ? 30 : 0;
                }
            }],
        ];

        $seencompetencies = [20 => true];
        $competencieswithcourses = [10 => (object) ['competencyid' => 10], 20 => (object) ['competencyid' => 20]];

        $eligible = $provider->test_get_eligible_competency_ids($competencies, $seencompetencies, $competencieswithcourses);
        $this->assertSame([10], $eligible);
    }

    /**
     * IDs-to-process should respect favourites-only mode.
     *
     * @covers ::get_ids_to_process
     */
    public function test_get_ids_to_process_respects_favourites_mode(): void {
        $provider = $this->get_provider_double();

        $eligibleids = [10, 11, 12];
        $compfavids = [11 => true];

        $this->assertSame(
            [10, 11, 12],
            $provider->test_get_ids_to_process($eligibleids, false, $compfavids)
        );

        $this->assertSame(
            [11],
            $provider->test_get_ids_to_process($eligibleids, true, $compfavids)
        );
    }

    /**
     * Trail competency helper should map payload and track last completed index.
     *
     * @covers ::build_trail_competency_data
     */
    public function test_build_trail_competency_data_maps_rows_and_last_completed_index(): void {
        $provider = $this->get_provider_double();

        $rawrows = [
            ['id' => 101, 'shortname' => 'Comp 1', 'proficiency' => 0],
            ['id' => 102, 'shortname' => 'Comp 2', 'proficiency' => 1],
            ['id' => 103, 'shortname' => 'Comp 3', 'proficiency' => 0],
            ['id' => 104, 'shortname' => 'Comp 4', 'proficiency' => 1],
        ];

        [$competencydata, $lastcompletedindex] = $provider->test_build_trail_competency_data(77, $rawrows);

        $this->assertCount(4, $competencydata);
        $this->assertSame(3, $lastcompletedindex);
        $this->assertSame([101, 102, 103, 104], array_column($competencydata, 'id'));
        $this->assertSame([0, 1, 2, 3], array_column($competencydata, 'index'));
        $this->assertSame([false, true, false, true], array_column($competencydata, 'iscompleted'));
        $this->assertStringContainsString('id=77', (string) $competencydata[0]['url']);
        $this->assertStringContainsString('competencyid=101', (string) $competencydata[0]['url']);
    }

    /**
     * Visibility helper should skip already-seen and unavailable competencies.
     *
     * @covers ::skip_competency_for_visibility
     */
    public function test_skip_competency_for_visibility_rules(): void {
        $provider = $this->get_provider_double();

        $seencompetencies = [100 => true];
        $competencieswithcourses = [100 => (object) ['competencyid' => 100], 101 => (object) ['competencyid' => 101]];

        $this->assertTrue($provider->test_skip_competency_for_visibility(100, $seencompetencies, $competencieswithcourses));
        $this->assertTrue($provider->test_skip_competency_for_visibility(999, $seencompetencies, $competencieswithcourses));
        $this->assertArrayHasKey(999, $seencompetencies);
        $this->assertFalse($provider->test_skip_competency_for_visibility(101, $seencompetencies, $competencieswithcourses));
    }

    /**
     * Favourites helper should skip only when favourites-only is enabled and id is not favourite.
     *
     * @covers ::skip_competency_for_favourites
     */
    public function test_skip_competency_for_favourites_rules(): void {
        $provider = $this->get_provider_double();

        $compfavids = [55 => true];

        $this->assertFalse($provider->test_skip_competency_for_favourites(false, 77, $compfavids));
        $this->assertFalse($provider->test_skip_competency_for_favourites(true, 55, $compfavids));
        $this->assertTrue($provider->test_skip_competency_for_favourites(true, 77, $compfavids));
    }

    /**
     * Competency processor should count visible competencies and build card when allowed.
     *
     * @covers ::process_competency_dataset_item
     */
    public function test_process_competency_dataset_item_builds_card_when_eligible(): void {
        $provider = $this->get_provider_double();

        $competency = new class {
            /**
             * Get a field from fake competency object.
             *
             * @param string $field Field name.
             * @return int
             */
            public function get(string $field): int {
                return ($field === 'id') ? 501 : 0;
            }
        };

        $seencompetencies = [];
        $result = $provider->test_process_competency_dataset_item(
            77,
            $competency,
            false,
            [501 => true],
            [501 => (object) ['competencyid' => 501]],
            [501 => ['tag1' => 'A']],
            $seencompetencies
        );

        $this->assertTrue($result['counted']);
        $this->assertNotNull($result['card']);
        $this->assertSame(501, $result['card']['id']);
        $this->assertTrue($result['card']['isfavourite']);
        $this->assertArrayHasKey(501, $seencompetencies);
    }

    /**
     * Competency processor should count but not return card for favourites-only skipped items.
     *
     * @covers ::process_competency_dataset_item
     */
    public function test_process_competency_dataset_item_counts_when_favourite_skipped(): void {
        $provider = $this->get_provider_double();

        $competency = new class {
            /**
             * Get a field from fake competency object.
             *
             * @param string $field Field name.
             * @return int
             */
            public function get(string $field): int {
                return ($field === 'id') ? 777 : 0;
            }
        };

        $seencompetencies = [];
        $result = $provider->test_process_competency_dataset_item(
            88,
            $competency,
            true,
            [55 => true],
            [777 => (object) ['competencyid' => 777]],
            [],
            $seencompetencies
        );

        $this->assertTrue($result['counted']);
        $this->assertNull($result['card']);
        $this->assertArrayHasKey(777, $seencompetencies);
    }

    /**
     * Competency processor should not count when item is skipped by visibility.
     *
     * @covers ::process_competency_dataset_item
     */
    public function test_process_competency_dataset_item_does_not_count_when_visibility_skips(): void {
        $provider = $this->get_provider_double();

        $competency = new class {
            /**
             * Get a field from fake competency object.
             *
             * @param string $field Field name.
             * @return int
             */
            public function get(string $field): int {
                return ($field === 'id') ? 909 : 0;
            }
        };

        $seencompetencies = [];
        $result = $provider->test_process_competency_dataset_item(
            99,
            $competency,
            false,
            [],
            [],
            [],
            $seencompetencies
        );

        $this->assertFalse($result['counted']);
        $this->assertNull($result['card']);
        $this->assertArrayHasKey(909, $seencompetencies);
    }

    /**
     * Plan competency processor should count and collect cards for eligible items.
     *
     * @covers ::process_plan_competencies
     */
    public function test_process_plan_competencies_counts_and_collects_cards(): void {
        $this->resetAfterTest();

        $provider = $this->get_provider_double();
        $plan = $this->getDataGenerator()->get_plugin_generator('core_competency')->create_plan(['userid' => 2]);

        $stubcomp = new class {
            /**
             * Get a field from fake competency object.
             *
             * @param string $field Field name.
             * @return int
             */
            public function get(string $field): int {
                return ($field === 'id') ? 201 : 0;
            }
        };

        $seencompetencies = [];
        $result = $provider->test_process_plan_competencies_with(
            55,
            $plan,
            false,
            [],
            $seencompetencies,
            [(object) ['competency' => $stubcomp]],
            [201 => (object) ['competencyid' => 201]]
        );

        $this->assertSame(1, $result['counted']);
        $this->assertCount(1, $result['cards']);
        $this->assertSame(201, $result['cards'][0]['id']);
        $this->assertArrayHasKey(201, $seencompetencies);
    }

    /**
     * Plan competency processor should return an empty result, and say why, when the API throws.
     *
     * @covers ::process_plan_competencies
     */
    public function test_process_plan_competencies_returns_empty_on_exception(): void {
        $this->resetAfterTest();

        $provider = new class extends dataset_provider {
            /**
             * Constructor.
             */
            public function __construct() {
            }

            /**
             * Throw to simulate API failure in test.
             *
             * @param \core_competency\plan $plan Plan object.
             * @return array Never returns.
             * @throws \RuntimeException Always.
             */
            protected function fetch_plan_competencies_api(\core_competency\plan $plan): array {
                throw new \RuntimeException('API failure');
            }

            /**
             * Invoke process_plan_competencies for test assertions.
             *
             * @param int $planid Plan id.
             * @param \core_competency\plan $plan Plan object.
             * @return array
             */
            public function test_run(int $planid, \core_competency\plan $plan): array {
                $seen = [];
                return $this->process_plan_competencies(
                    $planid,
                    $plan,
                    false,
                    [],
                    $seen
                );
            }
        };

        $plan = $this->getDataGenerator()->get_plugin_generator('core_competency')->create_plan(['userid' => 2]);
        $result = $provider->test_run(11, $plan);

        $this->assertSame(0, $result['counted']);
        $this->assertSame([], $result['cards']);
        $this->assertDebuggingCalled('Error reading the competencies of plan 11: API failure', DEBUG_DEVELOPER);
    }

    /**
     * Button data: continue on a partial trail, access otherwise, and view outside the active bucket.
     *
     * @covers ::get_plan_button_data
     */
    public function test_get_plan_button_data_returns_correct_strings(): void {
        $this->resetAfterTest();
        $provider = $this->get_provider_double();

        // Precondition: the wordings differ, or swapping the branches would go unnoticed.
        $this->assertNotSame(get_string('continuecard', 'block_dimensions'), get_string('accesscard', 'block_dimensions'));

        $this->assertSame([
            'buttonlabel' => get_string('continuecard', 'block_dimensions'),
            'buttonarialabel' => get_string('continuecardaria', 'block_dimensions', 'My plan'),
        ], $provider->test_get_plan_button_data('My plan', true));

        $this->assertSame([
            'buttonlabel' => get_string('accesscard', 'block_dimensions'),
            'buttonarialabel' => get_string('accesscardaria', 'block_dimensions', 'Other plan'),
        ], $provider->test_get_plan_button_data('Other plan', false));

        // Outside the active bucket there is nothing to continue, even on a partial trail.
        $this->assertSame([
            'buttonlabel' => get_string('viewplancard', 'block_dimensions'),
            'buttonarialabel' => get_string('viewplancardaria', 'block_dimensions', 'Done plan'),
        ], $provider->test_get_plan_button_data('Done plan', true, dataset_provider::BUCKET_COMPLETE));
    }

    /**
     * Resolve plan display context with null template should return plan display mode and empty metadata.
     *
     * @covers ::resolve_plan_display_context
     */
    public function test_resolve_plan_display_context_returns_plan_mode_for_null_template(): void {
        $provider = $this->get_provider_double();
        [$templatemetadata, $displaymode] = $provider->test_resolve_plan_display_context(null);
        $this->assertSame([], $templatemetadata);
        $this->assertSame(constants::DISPLAYMODE_PLAN, $displaymode);
    }

    /**
     * Eligible competency IDs should return empty array when all competencies have already been seen.
     *
     * @covers ::get_eligible_competency_ids
     */
    public function test_get_eligible_competency_ids_all_seen_returns_empty(): void {
        $provider = $this->get_provider_double();

        $competencies = [
            (object) ['competency' => new class {
                /**
                 * Get a field from fake competency object.
                 *
                 * @param string $field Field name.
                 * @return int
                 */
                public function get(string $field): int {
                    return ($field === 'id') ? 10 : 0;
                }
            }],
        ];

        $seencompetencies = [10 => true];
        $competencieswithcourses = [10 => (object)['competencyid' => 10]];

        $eligible = $provider->test_get_eligible_competency_ids($competencies, $seencompetencies, $competencieswithcourses);
        $this->assertSame([], $eligible);
    }

    /**
     * Trail selection with fewer items than window size should return all items with first/last markers.
     *
     * @covers ::select_trail_competencies
     */
    public function test_select_trail_competencies_with_fewer_than_window_items(): void {
        $provider = $this->get_provider_double();

        $competencies = [
            ['id' => 1, 'shortname' => 'C1', 'iscompleted' => false, 'index' => 0, 'url' => '/'],
            ['id' => 2, 'shortname' => 'C2', 'iscompleted' => true, 'index' => 1, 'url' => '/'],
            ['id' => 3, 'shortname' => 'C3', 'iscompleted' => false, 'index' => 2, 'url' => '/'],
        ];

        $selected = $provider->test_select_trail_competencies($competencies, 1);

        $this->assertCount(3, $selected);
        $this->assertTrue($selected[0]['isfirst']);
        $this->assertFalse($selected[0]['islast']);
        $this->assertFalse($selected[2]['isfirst']);
        $this->assertTrue($selected[2]['islast']);
    }

    /**
     * Plan competency processor with no competencies should return zero counts and empty cards.
     *
     * @covers ::process_plan_competencies
     */
    public function test_process_plan_competencies_empty_stub_returns_zero(): void {
        $this->resetAfterTest();

        $provider = $this->get_provider_double();
        $plan = $this->getDataGenerator()->get_plugin_generator('core_competency')->create_plan(['userid' => 2]);

        $seencompetencies = [];
        $result = $provider->test_process_plan_competencies_with(
            10,
            $plan,
            false,
            [],
            $seencompetencies,
            [],
            []
        );

        $this->assertSame(0, $result['counted']);
        $this->assertSame([], $result['cards']);
        $this->assertSame([], $seencompetencies);
    }

    /**
     * Duplicate competency already seen in a previous plan should be skipped by the processor.
     *
     * @covers ::process_competency_dataset_item
     */
    public function test_process_competency_dataset_item_skips_already_seen_competency(): void {
        $provider = $this->get_provider_double();

        $competency = new class {
            /**
             * Get a field from fake competency object.
             *
             * @param string $field Field name.
             * @return int
             */
            public function get(string $field): int {
                return ($field === 'id') ? 501 : 0;
            }
        };

        // Pre-mark as seen to simulate duplicate from another plan.
        $seencompetencies = [501 => true];
        $result = $provider->test_process_competency_dataset_item(
            77,
            $competency,
            false,
            [501 => true],
            [501 => (object)['competencyid' => 501]],
            [],
            $seencompetencies
        );

        $this->assertFalse($result['counted']);
        $this->assertNull($result['card']);
    }

    /**
     * build_plan_dataset_card should return null when favourites-only and plan is not a favourite.
     *
     * @covers ::build_plan_dataset_card
     */
    public function test_build_plan_dataset_card_returns_null_for_non_favourite_in_favourites_mode(): void {
        $this->resetAfterTest();

        $provider = $this->get_provider_double();
        $plan = $this->getDataGenerator()->get_plugin_generator('core_competency')->create_plan(['userid' => 2]);
        $planid = (int) $plan->get('id');

        $result = $provider->test_build_plan_dataset_card($plan, null, $planid, [], true, []);
        $this->assertNull($result);
    }

    /**
     * Trail start index when total items is below window size should always be zero.
     *
     * @covers ::get_trail_start_index
     */
    public function test_get_trail_start_index_short_total_stays_at_zero(): void {
        $provider = $this->get_provider_double();

        $this->assertSame(0, $provider->test_get_trail_start_index(3, 0));
        $this->assertSame(0, $provider->test_get_trail_start_index(3, 2));
        $this->assertSame(0, $provider->test_get_trail_start_index(1, 0));
    }

    /**
     * sanitize_color should accept hex colours and reject CSS injection payloads.
     *
     * @covers ::sanitize_color
     */
    public function test_sanitize_color_accepts_hex_and_rejects_injection(): void {
        $provider = $this->get_provider_double();

        // Valid hex colours in 3, 6 and 8 digit forms pass through (trimmed).
        $this->assertSame('#fff', $provider->test_sanitize_color('#fff'));
        $this->assertSame('#A1B2C3', $provider->test_sanitize_color('#A1B2C3'));
        $this->assertSame('#a1b2c3d4', $provider->test_sanitize_color(' #a1b2c3d4 '));

        // Anything that could smuggle extra CSS declarations is dropped.
        $this->assertNull($provider->test_sanitize_color('#fff; position: fixed; inset: 0'));
        $this->assertNull($provider->test_sanitize_color('red'));
        $this->assertNull($provider->test_sanitize_color('rgb(0, 0, 0)'));
        $this->assertNull($provider->test_sanitize_color('url(https://evil.example)'));
        $this->assertNull($provider->test_sanitize_color(''));
        $this->assertNull($provider->test_sanitize_color(null));
    }

    /**
     * sanitize_image_url should accept pluginfile URLs and reject CSS-breaking values.
     *
     * @covers ::sanitize_image_url
     */
    public function test_sanitize_image_url_accepts_urls_and_rejects_injection(): void {
        $provider = $this->get_provider_double();

        // File API style URLs pass through untouched.
        $pluginfile = 'https://example.com/pluginfile.php/1/local_dimensions/cardimage/7/photo.png';
        $this->assertSame($pluginfile, $provider->test_sanitize_image_url($pluginfile));
        $encoded = 'https://example.com/pluginfile.php/1/local_dimensions/cardimage/7/my%20photo.png';
        $this->assertSame($encoded, $provider->test_sanitize_image_url($encoded));

        // A URL built by moodle_url already encodes a quote and parentheses, so it is left alone.
        $built = \moodle_url::make_pluginfile_url(1, 'local_dimensions', 'cardimage', 7, '/', "it's (my) photo.png")->out(false);
        $this->assertStringContainsString('%27s%20%28my%29', $built);
        $this->assertSame($built, $provider->test_sanitize_image_url($built));

        // Values that are not URLs at all are dropped.
        $this->assertNull($provider->test_sanitize_image_url("x') no-repeat; position: fixed; ('"));
        $this->assertNull($provider->test_sanitize_image_url('javascript:alert(1)'));
        $this->assertNull($provider->test_sanitize_image_url(''));
        $this->assertNull($provider->test_sanitize_image_url(null));
    }

    /**
     * A URL that passes PARAM_URL cannot close url('...') and append declarations.
     *
     * PARAM_URL accepts a quote and parentheses in the query and the fragment, and the browser
     * decodes Mustache's &#39; before the CSS parser reads the style attribute.
     *
     * @covers ::sanitize_image_url
     */
    public function test_sanitize_image_url_encodes_what_param_url_lets_through(): void {
        $provider = $this->get_provider_double();

        $breakouts = [
            [
                "https://x.example/a.png?');background-image:url('https://evil.example/t",
                'https://x.example/a.png?%27%29;background-image:url%28%27https://evil.example/t',
            ],
            [
                "https://x.example/a.png#');position:fixed;x:url('y",
                'https://x.example/a.png#%27%29;position:fixed;x:url%28%27y',
            ],
        ];
        foreach ($breakouts as [$breakout, $expected]) {
            // Precondition: PARAM_URL lets the value through unchanged, which is the gap under test.
            $this->assertSame($breakout, clean_param($breakout, PARAM_URL));

            $clean = $provider->test_sanitize_image_url($breakout);
            $this->assertSame($expected, $clean);
            $this->assertDoesNotMatchRegularExpression('/[\'"()\\\\\s]/', $clean);
        }
    }

    /**
     * Favourites are read for the user the provider was built for, not for whoever is logged in.
     *
     * @covers ::get_dataset
     */
    public function test_favourites_belong_to_the_provider_user(): void {
        global $USER;
        $this->resetAfterTest();
        set_config('enabled', 1, 'core_competency');

        $learner = $this->getDataGenerator()->create_user();
        $plan = $this->getDataGenerator()->get_plugin_generator('core_competency')->create_plan([
            'userid' => $learner->id,
            'status' => plan::STATUS_ACTIVE,
        ]);
        $learnercontext = \context_user::instance($learner->id);
        \core_favourites\service_factory::get_service_for_user_context($learnercontext)
            ->create_favourite('block_dimensions', 'plan', (int) $plan->get('id'), $learnercontext);

        // The viewer holds no favourite of their own, so a starred card can only be the learner's.
        $this->setAdminUser();
        $this->assertNotSame((int) $learner->id, (int) $USER->id);

        $dataset = (new dataset_provider((int) $learner->id))->get_dataset(false, 'plan', dataset_provider::BUCKET_ACTIVE);

        $this->assertCount(1, $dataset['plancards']);
        $this->assertSame((int) $plan->get('id'), $dataset['plancards'][0]['id']);
        $this->assertTrue($dataset['plancards'][0]['isfavourite']);
    }

    /**
     * A custom field's display name is read afresh, so a rename shows on the very next read.
     *
     * @covers ::get_customfield_name
     */
    public function test_get_customfield_name_follows_a_rename(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        \local_dimensions\helper::ensure_custom_fields_exist(\local_dimensions\helper::AREA_LP);
        $field = \local_dimensions\helper::find_field_by_shortname(constants::CFIELD_TAG1, \local_dimensions\helper::AREA_LP);
        $this->assertNotNull($field, 'the tag field must exist, or this proves nothing');

        $DB->set_field('customfield_field', 'name', 'Intake year', ['id' => $field->get('id')]);
        $this->assertSame('Intake year', dataset_provider::get_customfield_name(constants::CFIELD_TAG1, 'lp'));

        $DB->set_field('customfield_field', 'name', 'Cohort', ['id' => $field->get('id')]);
        $this->assertSame('Cohort', dataset_provider::get_customfield_name(constants::CFIELD_TAG1, 'lp'));

        $this->assertNull(dataset_provider::get_customfield_name('block_dimensions_nosuchfield', 'lp'));
    }

    /**
     * A competency card offers the favourite toggle only while favourites are enabled.
     *
     * A setting that was never saved counts as enabled, as it does for toggle_favourite; a raw
     * read of the setting would hide the star on such a site.
     *
     * @covers ::process_competency_dataset_item
     * @covers ::favourite_fields
     */
    public function test_competency_card_offers_the_favourite_toggle_only_while_favourites_are_enabled(): void {
        $this->resetAfterTest();
        $provider = $this->get_provider_double();
        $competency = new class {
            /**
             * Get a field from fake competency object.
             *
             * @param string $field Field name.
             * @return int
             */
            public function get(string $field): int {
                return ($field === 'id') ? 601 : 0;
            }
        };
        $buildcard = function () use ($provider, $competency): array {
            $seencompetencies = [];
            $result = $provider->test_process_competency_dataset_item(
                12,
                $competency,
                false,
                [],
                [601 => (object) ['competencyid' => 601]],
                [],
                $seencompetencies
            );
            $this->assertNotNull($result['card'], 'the competency must produce a card, or this proves nothing');

            return $result['card'];
        };

        // Control: with favourites enabled the card offers the toggle.
        set_config('enable_favourites', 1, 'block_dimensions');
        $this->assertTrue($buildcard()['showfavourite']);

        unset_config('enable_favourites', 'block_dimensions');
        $this->assertFalse(get_config('block_dimensions', 'enable_favourites'));
        $this->assertTrue($buildcard()['showfavourite']);

        set_config('enable_favourites', 0, 'block_dimensions');
        $card = $buildcard();
        $this->assertFalse($card['showfavourite']);
        // The return structure declares the other favourite fields as required, so they stay.
        $this->assertFalse($card['isfavourite']);
        $this->assertSame(get_string('addtofavourites', 'block_dimensions'), $card['favouritearialabel']);
        $this->assertSame($card['favouritearialabel'], $card['favouritetitle']);
    }

    /**
     * A plan card offers the favourite toggle only for an active plan, and only while favourites
     * are enabled.
     *
     * @covers ::get_dataset
     * @covers ::build_plan_dataset_card
     * @covers ::favourite_fields
     */
    public function test_plan_card_offers_the_favourite_toggle_only_while_favourites_are_enabled(): void {
        $this->resetAfterTest();
        set_config('enabled', 1, 'core_competency');

        $learner = $this->getDataGenerator()->create_user();
        $generator = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $generator->create_plan(['userid' => $learner->id, 'status' => plan::STATUS_ACTIVE]);
        $generator->create_plan(['userid' => $learner->id, 'status' => plan::STATUS_COMPLETE]);
        $this->setUser($learner);
        $provider = new dataset_provider((int) $learner->id);
        $showfavourite = static function (string $bucket) use ($provider): array {
            return array_column($provider->get_dataset(false, 'plan', $bucket)['plancards'], 'showfavourite');
        };

        // Control: with favourites enabled the active plan offers the toggle and the completed one does not.
        set_config('enable_favourites', 1, 'block_dimensions');
        $this->assertSame([true], $showfavourite(dataset_provider::BUCKET_ACTIVE));
        $this->assertSame([false], $showfavourite(dataset_provider::BUCKET_COMPLETE));

        set_config('enable_favourites', 0, 'block_dimensions');
        $this->assertSame([false], $showfavourite(dataset_provider::BUCKET_ACTIVE));
        $this->assertSame([false], $showfavourite(dataset_provider::BUCKET_COMPLETE));
    }
}
