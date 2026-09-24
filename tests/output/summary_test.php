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

namespace block_dimensions\output;

use advanced_testcase;
use block_dimensions\local\dataset_provider;

/**
 * Tests for the summary renderable's block gate.
 *
 * @package    block_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_dimensions\output\summary
 * @covers     \block_dimensions\local\bootstrap
 */
final class summary_test extends advanced_testcase {
    /**
     * A failure reading the plan list opens the gate instead of throwing, and is logged.
     *
     * The control is the same user read for real: they hold no plan, so the gate is closed, and
     * a true answer can only have come from the failure path.
     */
    public function test_has_content_fails_open_when_the_plan_list_cannot_be_read(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $this->assertFalse((new summary($user))->has_content());

        $summary = new class ($user) extends summary {
            /**
             * Fail the way a lost database connection does.
             *
             * @return dataset_provider Never returns.
             * @throws \dml_read_exception Always.
             */
            protected function create_dataset_provider(): dataset_provider {
                throw new \dml_read_exception('simulated failure');
            }
        };

        $logfile = make_request_directory() . '/error.log';
        $previous = ini_set('error_log', $logfile);
        try {
            $this->assertTrue($summary->has_content());
        } finally {
            ini_set('error_log', (string) $previous);
        }
        $this->assertStringContainsString(
            'block_dimensions: reading the plan list for the block gate failed',
            (string) file_get_contents($logfile)
        );
    }

    /**
     * The shell's labelsjson payload carries every label of the status filter.
     *
     * The pills are drawn by JavaScript from this payload alone: a label missing here does not
     * fail anything, it silently draws the bucket's internal key at the learner.
     *
     * @return void
     */
    public function test_export_ships_every_label_the_client_draws(): void {
        global $PAGE;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $export = (new summary($user))->export_for_template($PAGE->get_renderer('block_dimensions'));
        $labels = json_decode($export['labelsjson'], true);

        $statuskeys = [
            'statusfilter',
            'statusactive',
            'statusreview',
            'statuscomplete',
            'statusloadingactive',
            'statusloadingreview',
            'statusloadingcomplete',
        ];
        foreach ($statuskeys as $key) {
            $this->assertArrayHasKey($key, $labels);
            $this->assertSame(get_string($key, 'block_dimensions'), $labels[$key]);
        }

        // Control: the payload is the one the client reads, so an old label is still in it.
        $this->assertArrayHasKey('myfavourites', $labels);
    }
}
