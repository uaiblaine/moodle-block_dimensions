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

/**
 * Privacy Subsystem implementation for block_dimensions.
 *
 * @package    block_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_dimensions\privacy;

use context;
use context_user;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy Subsystem for block_dimensions.
 *
 * This plugin stores user favourites (learning plans and competencies) via the
 * core_favourites subsystem. All favourites are created in the user's own user
 * context with component "block_dimensions" and itemtype "plan" or "competency".
 *
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
        \core_privacy\local\metadata\provider,
        \core_privacy\local\request\plugin\provider,
        \core_privacy\local\request\core_userlist_provider {

    /** @var string Frankenstyle component name used by this plugin. */
    private const COMPONENT = 'block_dimensions';

    /** @var string[] Item types stored by this plugin in core_favourites. */
    private const ITEMTYPES = ['plan', 'competency'];

    /**
     * Returns meta data about this system.
     *
     * @param collection $collection The initialised collection to add items to.
     * @return collection A listing of user data stored through this system.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_subsystem_link(
            'core_favourites',
            [],
            'privacy:metadata:favourites'
        );
        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param int $userid The user to search.
     * @return contextlist The list of contexts used in this plugin.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        foreach (self::ITEMTYPES as $itemtype) {
            \core_favourites\privacy\provider::add_contexts_for_userid(
                $contextlist,
                $userid,
                self::COMPONENT,
                $itemtype
            );
        }
        return $contextlist;
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param userlist $userlist The userlist containing the list of users who have data.
     */
    public static function get_users_in_context(userlist $userlist) {
        if ($userlist->get_component() !== self::COMPONENT) {
            return;
        }
        if (!$userlist->get_context() instanceof context_user) {
            return;
        }
        foreach (self::ITEMTYPES as $itemtype) {
            \core_favourites\privacy\provider::add_userids_for_context($userlist, $itemtype);
        }
    }

    /**
     * Export all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts to export information for.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        if ($contextlist->get_component() !== self::COMPONENT) {
            return;
        }

        $userid = $contextlist->get_user()->id;
        $rootsubcontext = [get_string('pluginname', 'block_dimensions')];

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof context_user) {
                continue;
            }

            foreach (self::ITEMTYPES as $itemtype) {
                $records = $DB->get_records('favourite', [
                    'userid' => $userid,
                    'component' => self::COMPONENT,
                    'itemtype' => $itemtype,
                    'contextid' => $context->id,
                ]);
                if (empty($records)) {
                    continue;
                }

                $items = [];
                foreach ($records as $record) {
                    $info = \core_favourites\privacy\provider::get_favourites_info_for_user(
                        $userid,
                        $context,
                        self::COMPONENT,
                        $itemtype,
                        (int) $record->itemid
                    );
                    if ($info === null) {
                        continue;
                    }
                    $items[] = (object) array_merge(['itemid' => (int) $record->itemid], $info);
                }

                if (empty($items)) {
                    continue;
                }

                $subcontext = array_merge($rootsubcontext, [$itemtype]);
                writer::with_context($context)->export_data(
                    $subcontext,
                    (object) ['favourites' => $items]
                );
            }
        }
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param context $context The specific context to delete data for.
     */
    public static function delete_data_for_all_users_in_context(context $context) {
        if (!$context instanceof context_user) {
            return;
        }
        foreach (self::ITEMTYPES as $itemtype) {
            \core_favourites\privacy\provider::delete_favourites_for_all_users(
                $context,
                self::COMPONENT,
                $itemtype
            );
        }
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts and user to delete data for.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        if ($contextlist->get_component() !== self::COMPONENT) {
            return;
        }
        foreach (self::ITEMTYPES as $itemtype) {
            \core_favourites\privacy\provider::delete_favourites_for_user(
                $contextlist,
                self::COMPONENT,
                $itemtype
            );
        }
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param approved_userlist $userlist The approved context and users to delete data for.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        if ($userlist->get_component() !== self::COMPONENT) {
            return;
        }
        if (!$userlist->get_context() instanceof context_user) {
            return;
        }
        foreach (self::ITEMTYPES as $itemtype) {
            \core_favourites\privacy\provider::delete_favourites_for_userlist($userlist, $itemtype);
        }
    }
}
