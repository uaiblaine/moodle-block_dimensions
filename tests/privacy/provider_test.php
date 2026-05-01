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

namespace block_dimensions\privacy;

use context_system;
use context_user;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\writer;
use core_privacy\tests\provider_testcase;

/**
 * PHPUnit tests for the block_dimensions privacy provider.
 *
 * @package    block_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \block_dimensions\privacy\provider
 */
final class provider_test extends provider_testcase {

    /**
     * Helper: create a favourite for the given user, itemtype and itemid.
     *
     * @param int $userid
     * @param string $itemtype
     * @param int $itemid
     */
    private function create_favourite(int $userid, string $itemtype, int $itemid): void {
        $usercontext = context_user::instance($userid);
        $service = \core_favourites\service_factory::get_service_for_user_context($usercontext);
        $service->create_favourite('block_dimensions', $itemtype, $itemid, $usercontext);
    }

    /**
     * @covers ::get_metadata
     */
    public function test_get_metadata(): void {
        $collection = new \core_privacy\local\metadata\collection('block_dimensions');
        $newcollection = provider::get_metadata($collection);
        $this->assertSame($collection, $newcollection);
        $this->assertNotEmpty($collection->get_collection());
    }

    /**
     * @covers ::get_contexts_for_userid
     */
    public function test_get_contexts_for_userid(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();

        $this->assertCount(0, provider::get_contexts_for_userid((int) $user->id)->get_contextids());

        $this->create_favourite((int) $user->id, 'plan', 42);
        $this->create_favourite((int) $user->id, 'competency', 7);

        $contextids = provider::get_contexts_for_userid((int) $user->id)->get_contextids();
        $this->assertEqualsCanonicalizing(
            [context_user::instance($user->id)->id],
            array_values(array_unique($contextids))
        );
    }

    /**
     * @covers ::get_users_in_context
     */
    public function test_get_users_in_context(): void {
        $this->resetAfterTest();
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();

        $this->create_favourite((int) $user1->id, 'plan', 10);
        $this->create_favourite((int) $user2->id, 'competency', 20);

        // Each user has their own user context — verify each one separately.
        $userlist1 = new \core_privacy\local\request\userlist(
            context_user::instance($user1->id),
            'block_dimensions'
        );
        provider::get_users_in_context($userlist1);
        $this->assertEqualsCanonicalizing([$user1->id], $userlist1->get_userids());

        $userlist2 = new \core_privacy\local\request\userlist(
            context_user::instance($user2->id),
            'block_dimensions'
        );
        provider::get_users_in_context($userlist2);
        $this->assertEqualsCanonicalizing([$user2->id], $userlist2->get_userids());
    }

    /**
     * @covers ::get_users_in_context
     */
    public function test_get_users_in_context_ignores_other_components(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->create_favourite((int) $user->id, 'plan', 5);

        $userlist = new \core_privacy\local\request\userlist(
            context_user::instance($user->id),
            'core_course'
        );
        provider::get_users_in_context($userlist);
        $this->assertEmpty($userlist->get_userids());
    }

    /**
     * @covers ::get_users_in_context
     */
    public function test_get_users_in_context_ignores_non_user_contexts(): void {
        $this->resetAfterTest();
        $userlist = new \core_privacy\local\request\userlist(
            context_system::instance(),
            'block_dimensions'
        );
        provider::get_users_in_context($userlist);
        $this->assertEmpty($userlist->get_userids());
    }

    /**
     * @covers ::export_user_data
     */
    public function test_export_user_data(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $usercontext = context_user::instance($user->id);

        $this->create_favourite((int) $user->id, 'plan', 101);
        $this->create_favourite((int) $user->id, 'competency', 202);

        $approved = new approved_contextlist($user, 'block_dimensions', [$usercontext->id]);
        provider::export_user_data($approved);

        $writer = writer::with_context($usercontext);
        $this->assertTrue($writer->has_any_data());

        $root = get_string('pluginname', 'block_dimensions');
        $plandata = $writer->get_data([$root, 'plan']);
        $this->assertNotEmpty($plandata);
        $this->assertSame(101, $plandata->favourites[0]->itemid);

        $compdata = $writer->get_data([$root, 'competency']);
        $this->assertNotEmpty($compdata);
        $this->assertSame(202, $compdata->favourites[0]->itemid);
    }

    /**
     * @covers ::delete_data_for_all_users_in_context
     */
    public function test_delete_data_for_all_users_in_context(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $usercontext = context_user::instance($user->id);

        $this->create_favourite((int) $user->id, 'plan', 1);
        $this->create_favourite((int) $user->id, 'competency', 2);

        provider::delete_data_for_all_users_in_context($usercontext);

        $this->assertSame(0, $DB->count_records('favourite', [
            'component' => 'block_dimensions',
            'userid' => $user->id,
        ]));
    }

    /**
     * @covers ::delete_data_for_all_users_in_context
     */
    public function test_delete_data_for_all_users_in_context_ignores_non_user_context(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->create_favourite((int) $user->id, 'plan', 1);

        provider::delete_data_for_all_users_in_context(context_system::instance());

        $this->assertSame(1, $DB->count_records('favourite', [
            'component' => 'block_dimensions',
            'userid' => $user->id,
        ]));
    }

    /**
     * @covers ::delete_data_for_user
     */
    public function test_delete_data_for_user(): void {
        global $DB;
        $this->resetAfterTest();
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();

        $this->create_favourite((int) $user1->id, 'plan', 1);
        $this->create_favourite((int) $user1->id, 'competency', 2);
        $this->create_favourite((int) $user2->id, 'plan', 3);

        $approved = new approved_contextlist(
            $user1,
            'block_dimensions',
            [context_user::instance($user1->id)->id]
        );
        provider::delete_data_for_user($approved);

        $this->assertSame(0, $DB->count_records('favourite', [
            'component' => 'block_dimensions',
            'userid' => $user1->id,
        ]));
        $this->assertSame(1, $DB->count_records('favourite', [
            'component' => 'block_dimensions',
            'userid' => $user2->id,
        ]));
    }

    /**
     * @covers ::delete_data_for_users
     */
    public function test_delete_data_for_users(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $usercontext = context_user::instance($user->id);

        $this->create_favourite((int) $user->id, 'plan', 1);
        $this->create_favourite((int) $user->id, 'competency', 2);

        $userlist = new approved_userlist($usercontext, 'block_dimensions', [$user->id]);
        provider::delete_data_for_users($userlist);

        $this->assertSame(0, $DB->count_records('favourite', [
            'component' => 'block_dimensions',
            'userid' => $user->id,
        ]));
    }
}
