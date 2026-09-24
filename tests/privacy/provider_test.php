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
     * Create a block_dimensions favourite in the user's own context, where the plugin keeps all of them.
     *
     * @param int $userid
     * @param string $itemtype
     * @param int $itemid
     */
    private function create_favourite(int $userid, string $itemtype, int $itemid): void {
        $this->create_favourite_at_context($userid, $itemtype, $itemid, context_user::instance($userid));
    }

    /**
     * Create a block_dimensions favourite recorded against an arbitrary context.
     *
     * The plugin only ever creates favourites in a user's own context ({@see create_favourite()}),
     * so a row at another context is a state the plugin should never produce itself. It is still a
     * legitimate favourites-table row (the service accepts any {@see \context} for the item being
     * favourited, independent of the user context the service instance is scoped to), and it is the
     * fixture the provider's context-type guards are written to exclude - a guard that is only ever
     * tested against a context with nothing in it proves nothing about the guard itself.
     *
     * @param int $userid The user the favourite belongs to.
     * @param string $itemtype
     * @param int $itemid
     * @param \context $context The context to record the favourite against.
     */
    private function create_favourite_at_context(int $userid, string $itemtype, int $itemid, \context $context): void {
        $service = \core_favourites\service_factory::get_service_for_user_context(context_user::instance($userid));
        $service->create_favourite('block_dimensions', $itemtype, $itemid, $context);
    }

    /**
     * The plugin counts as compliant with the privacy API.
     *
     * Core's own compliance test sweeps every component but is not in the plugin's testsuite,
     * which is all moodle-plugin-ci runs, so the check is repeated here: a metadata provider
     * without a request data provider fails it ({@see \core_privacy\manager::component_is_compliant()}).
     *
     * @covers \block_dimensions\privacy\provider
     * @return void
     */
    public function test_the_component_is_compliant(): void {
        $this->assertTrue((new \core_privacy\manager())->component_is_compliant('block_dimensions'));
    }

    /**
     * Test that metadata is returned correctly.
     *
     * @covers ::get_metadata
     */
    public function test_get_metadata(): void {
        $collection = new \core_privacy\local\metadata\collection('block_dimensions');
        $newcollection = provider::get_metadata($collection);
        $this->assertSame($collection, $newcollection);
        $this->assertNotEmpty($collection->get_collection());
    }

    /**
     * Test that correct contexts are returned for a user.
     *
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
     * Test that users in a context are returned correctly.
     *
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
     * A userlist for another component gets no users from a context holding this plugin's favourites.
     *
     * This does not pin provider::get_users_in_context()'s own component guard: core's
     * add_userids_for_context() filters its SQL on $userlist->get_component() directly
     * (favourites/classes/privacy/provider.php:117-125), so a userlist built for 'core_course' finds
     * nothing here whether or not this plugin's own guard runs first - deleting the guard cannot
     * change this test's outcome. Kept as a regression check on that underlying assumption, which
     * every other test in this file relies on for isolation between components.
     *
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
     * Non-user contexts are ignored, even when a favourite is recorded directly against one.
     *
     * A userlist built for context_system with nothing at that context ever added would pass
     * whether or not the instanceof context_user guard ran, because there would be nothing for
     * core's own contextid filter to find either way. The control seeds exactly the row that filter
     * WOULD find, so only the plugin's own guard keeps it out of the answer.
     *
     * @covers ::get_users_in_context
     */
    public function test_get_users_in_context_ignores_non_user_contexts(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->create_favourite_at_context((int) $user->id, 'plan', 5, context_system::instance());

        $userlist = new \core_privacy\local\request\userlist(
            context_system::instance(),
            'block_dimensions'
        );
        provider::get_users_in_context($userlist);
        $this->assertEmpty($userlist->get_userids());
    }

    /**
     * Test that user data is exported correctly.
     *
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
     * Test that all user data in a context is deleted correctly.
     *
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
     * Deletion is skipped for non-user contexts, even for a favourite recorded directly against one.
     *
     * The user-context favourite below survives this call purely because core's own contextid filter
     * in delete_favourites_for_all_users() never matches it - true whether or not the instanceof
     * context_user guard runs, so on its own it would not catch the guard's deletion. The
     * system-context favourite is the row the guard actually protects: without it, core's filter
     * would find and delete that row too.
     *
     * @covers ::delete_data_for_all_users_in_context
     */
    public function test_delete_data_for_all_users_in_context_ignores_non_user_context(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->create_favourite((int) $user->id, 'plan', 1);
        $this->create_favourite_at_context((int) $user->id, 'competency', 2, context_system::instance());

        provider::delete_data_for_all_users_in_context(context_system::instance());

        $this->assertSame(1, $DB->count_records('favourite', [
            'component' => 'block_dimensions',
            'userid' => $user->id,
            'contextid' => context_user::instance($user->id)->id,
        ]));
        $this->assertSame(1, $DB->count_records('favourite', [
            'component' => 'block_dimensions',
            'userid' => $user->id,
            'contextid' => context_system::instance()->id,
        ]));
    }

    /**
     * Test that user data is deleted correctly for a specific user.
     *
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
     * Test that data is deleted correctly for multiple users.
     *
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
