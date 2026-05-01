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
     * Build a test double exposing protected helper methods.
     *
     * @return object
     */
    protected function get_provider_double() {
        return new class extends dataset_provider {
            /** @var array Stubbed competency payload for get_plan_competencies. */
            protected array $stubbedcompetencies = [];
            /** @var array Stubbed courses map for get_competencies_with_courses. */
            protected array $stubbedcourses = [];

            /**
             * Constructor.
             */
            public function __construct() {
                // Do not call parent constructor in pure helper tests.
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

            public function test_get_active_plans(): array {
                return $this->get_active_plans();
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
             * @return array
             */
            public function test_get_plan_button_data(string $planname, bool $haspartialtrail): array {
                return $this->get_plan_button_data($planname, $haspartialtrail);
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
     * Active plans helper should keep only plans with active status.
     *
     * @covers ::get_active_plans
     */
    public function test_get_active_plans_filters_by_status(): void {
        $provider = $this->get_provider_double();

        $activeplan = new class {
            /**
             * Get a field from fake plan object.
             *
             * @param string $field Field name.
             * @return int
             */
            public function get(string $field): int {
                if ($field === 'status') {
                    return plan::STATUS_ACTIVE;
                }
                return 0;
            }
        };

        $inactiveplan = new class {
            /**
             * Get a field from fake plan object.
             *
             * @param string $field Field name.
             * @return int
             */
            public function get(string $field): int {
                if ($field === 'status') {
                    return plan::STATUS_COMPLETE;
                }
                return 0;
            }
        };

        $provider->test_set_plans([$inactiveplan, $activeplan]);
        $activeplans = $provider->test_get_active_plans();

        $this->assertCount(1, $activeplans);
        $this->assertSame(plan::STATUS_ACTIVE, $activeplans[0]->get('status'));
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
     * Plan competency processor should return empty result when API throws.
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
    }

    /**
     * Button data should use continue strings when trail is partial, access strings otherwise.
     *
     * @covers ::get_plan_button_data
     */
    public function test_get_plan_button_data_returns_correct_strings(): void {
        $this->resetAfterTest();
        $provider = $this->get_provider_double();

        $partialdata = $provider->test_get_plan_button_data('My plan', true);
        $this->assertArrayHasKey('buttonlabel', $partialdata);
        $this->assertArrayHasKey('buttonarialabel', $partialdata);
        $this->assertStringContainsString('My plan', $partialdata['buttonarialabel']);

        $accessdata = $provider->test_get_plan_button_data('Other plan', false);
        $this->assertArrayHasKey('buttonlabel', $accessdata);
        $this->assertArrayHasKey('buttonarialabel', $accessdata);
        $this->assertStringContainsString('Other plan', $accessdata['buttonarialabel']);

        $this->assertNotSame($partialdata['buttonlabel'], $accessdata['buttonlabel']);
    }

    /**
     * Resolve plan display context with null template should return plan display mode and empty metadata.
     *
     * @covers ::resolve_plan_display_context
     */
    public function test_resolve_plan_display_context_returns_plan_mode_for_null_template(): void {
        if (!class_exists(\local_dimensions\constants::class)) {
            $this->markTestSkipped('local_dimensions is not installed');
        }
        $provider = $this->get_provider_double();
        [$templatemetadata, $displaymode] = $provider->test_resolve_plan_display_context(null);
        $this->assertSame([], $templatemetadata);
        $this->assertSame(\local_dimensions\constants::DISPLAYMODE_PLAN, $displaymode);
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
}
