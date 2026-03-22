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
 * External API to toggle a favourite in block_dimensions.
 *
 * @package    block_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_dimensions\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * External API to toggle a favourite for a plan or competency.
 *
 * @package    block_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class toggle_favourite extends external_api {
    /**
     * Define input parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'itemtype' => new external_value(PARAM_ALPHA, 'Item type: plan or competency'),
            'itemid' => new external_value(PARAM_INT, 'Item ID'),
        ]);
    }

    /**
     * Execute method — toggles a favourite on or off.
     *
     * @param string $itemtype Item type (plan or competency).
     * @param int $itemid Item ID.
     * @return array<string, bool>
     */
    public static function execute(string $itemtype, int $itemid) {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'itemtype' => $itemtype,
            'itemid' => $itemid,
        ]);
        $itemtype = $params['itemtype'];
        $itemid = $params['itemid'];

        require_login();
        if (isguestuser()) {
            throw new \moodle_exception('noguest');
        }

        // Validate itemtype.
        if (!in_array($itemtype, ['plan', 'competency'])) {
            throw new \invalid_parameter_exception('Invalid itemtype: ' . $itemtype);
        }

        // Check admin setting.
        if (!get_config('block_dimensions', 'enable_favourites')) {
            throw new \moodle_exception('favouritesdisabled', 'block_dimensions');
        }

        $usercontext = \context_user::instance($USER->id);
        self::validate_context($usercontext);

        $ufservice = \core_favourites\service_factory::get_service_for_user_context($usercontext);

        if ($ufservice->favourite_exists('block_dimensions', $itemtype, $itemid, $usercontext)) {
            $ufservice->delete_favourite('block_dimensions', $itemtype, $itemid, $usercontext);
            return ['isfavourite' => false];
        }

        $ufservice->create_favourite('block_dimensions', $itemtype, $itemid, $usercontext);
        return ['isfavourite' => true];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns() {
        return new external_single_structure([
            'isfavourite' => new external_value(PARAM_BOOL, 'Whether the item is now a favourite'),
        ]);
    }
}
