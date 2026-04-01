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
 * PHPUnit tests for toggle_favourite external endpoint.
 *
 * @package    block_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \block_dimensions\external\toggle_favourite
 */
final class toggle_favourite_test extends advanced_testcase {
    /**
     * Invalid itemtype should throw invalid_parameter_exception.
     *
     * @covers ::execute
     */
    public function test_execute_rejects_invalid_itemtype(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $this->expectException(\invalid_parameter_exception::class);
        toggle_favourite::execute('invalidtype', 1);
    }

    /**
     * Non-positive itemid should throw invalid_parameter_exception.
     *
     * @covers ::execute
     */
    public function test_execute_rejects_non_positive_itemid(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $this->expectException(\invalid_parameter_exception::class);
        toggle_favourite::execute('plan', 0);
    }

    /**
     * Disabled favourites setting should throw plugin exception.
     *
     * @covers ::execute
     */
    public function test_execute_throws_when_favourites_disabled(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        set_config('enable_favourites', 0, 'block_dimensions');

        $this->expectException(\moodle_exception::class);
        toggle_favourite::execute('plan', 1);
    }

    /**
     * Negative itemid should throw invalid_parameter_exception.
     *
     * @covers ::execute
     */
    public function test_execute_rejects_negative_itemid(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $this->expectException(\invalid_parameter_exception::class);
        toggle_favourite::execute('plan', -1);
    }
}
