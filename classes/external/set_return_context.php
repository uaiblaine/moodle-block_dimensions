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
 * External API to set return context for the "Return to Plan" button.
 *
 * Called by the block's JavaScript when a trail item or card is clicked,
 * so the floating "Return to Plan" button knows which plan to go back to
 * after the user lands on the course page.
 *
 * @package    block_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_dimensions\external;

use core_competency\api;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use moodle_url;

/**
 * External API to set return context for the "Return to Plan" button.
 *
 * @package    block_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class set_return_context extends external_api {
    /**
     * Define input parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'planid' => new external_value(
                PARAM_INT,
                'The learning plan ID to set as return destination'
            ),
            'courseid' => new external_value(
                PARAM_INT,
                'The course ID the user is navigating to',
                VALUE_DEFAULT,
                0
            ),
        ]);
    }

    /**
     * Store the return context for the "Return to Plan" button.
     *
     * When courseid is provided, stores the context for that specific course.
     * When courseid is 0, resolves all courses linked to the plan's competencies
     * and stores the context for each one (same behaviour as view-plan.php).
     *
     * @param int $planid The learning plan ID.
     * @param int $courseid The target course ID (0 to resolve all from the plan).
     * @return array Result with success flag.
     */
    public static function execute(int $planid, int $courseid = 0): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'planid' => $planid,
            'courseid' => $courseid,
        ]);
        $planid = (int) $params['planid'];
        $courseid = (int) $params['courseid'];

        require_login();
        if (isguestuser()) {
            throw new \moodle_exception('noguest');
        }

        $usercontext = \context_user::instance($USER->id);
        self::validate_context($usercontext);

        // Verify the user can read this plan (security check).
        $plan = api::read_plan($planid);

        // Check the return button feature is enabled.
        if (!get_config('local_dimensions', 'enablereturnbutton')) {
            return ['success' => false];
        }

        $returnurl = new moodle_url('/local/dimensions/view-plan.php', ['id' => $planid]);

        if ($courseid > 0) {
            // Store context for a single specific course.
            \local_dimensions\helper::set_return_context_for_course($courseid, $returnurl);
        } else {
            // Resolve all courses from the plan and store context for each.
            $validcourseids = \local_dimensions\template_course_cache::get_courses_for_plan($plan);
            \local_dimensions\helper::set_return_context($returnurl, $validcourseids);
        }

        return ['success' => true];
    }

    /**
     * Define return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the context was stored successfully'),
        ]);
    }
}
