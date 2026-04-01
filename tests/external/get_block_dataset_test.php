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
    }
}
