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
use context_user;

/**
 * PHPUnit tests for block_dimensions' uninstall hook.
 *
 * @package    block_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers ::xmldb_block_dimensions_uninstall
 */
final class uninstall_test extends advanced_testcase {
    public static function setUpBeforeClass(): void {
        require_once(__DIR__ . '/../db/uninstall.php');
        parent::setUpBeforeClass();
    }

    /**
     * Create a favourite row for the given user, in the user's own context.
     *
     * Written the way {@see \block_dimensions\external\toggle_favourite} writes them: through the
     * core_favourites service, in the user's own context.
     *
     * @param int $userid The favourite's owner.
     * @param string $component Frankenstyle component the favourite belongs to.
     * @param string $itemtype Item type within that component.
     * @param int $itemid Item id.
     */
    private function create_favourite(int $userid, string $component, string $itemtype, int $itemid): void {
        $usercontext = context_user::instance($userid);
        \core_favourites\service_factory::get_service_for_user_context($usercontext)
            ->create_favourite($component, $itemtype, $itemid, $usercontext);
    }

    /**
     * The uninstall hook deletes every block_dimensions favourite, for every user, and leaves
     * every other component's favourites untouched.
     *
     * Core's uninstall_plugin() does not purge the 'favourite' table by component, so this hook is
     * the only thing that does. The core_course favourites seeded beside them are the control: a
     * delete that dropped its component condition would empty the table and still pass the first
     * assertion.
     */
    public function test_uninstall_deletes_only_block_dimensions_favourites(): void {
        global $DB;
        $this->resetAfterTest();

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();

        foreach ([$user1, $user2] as $user) {
            $this->create_favourite((int) $user->id, 'block_dimensions', 'plan', 1);
            $this->create_favourite((int) $user->id, 'block_dimensions', 'competency', 2);
            $this->create_favourite((int) $user->id, 'core_course', 'courses', (int) $course->id);
        }

        // Precondition: both kinds of favourite exist before the hook runs.
        $this->assertEquals(4, $DB->count_records('favourite', ['component' => 'block_dimensions']));
        $this->assertEquals(2, $DB->count_records('favourite', ['component' => 'core_course']));

        $result = xmldb_block_dimensions_uninstall();

        $this->assertTrue($result);
        $this->assertEquals(0, $DB->count_records('favourite', ['component' => 'block_dimensions']));
        $this->assertEquals(2, $DB->count_records('favourite', ['component' => 'core_course']));
    }
}
