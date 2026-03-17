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
 * External API to get block_dimensions dataset.
 *
 * @package    block_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_dimensions\external;

use block_dimensions\local\dataset_provider;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * External API to get block_dimensions dataset.
 *
 * @package    block_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_block_dataset extends external_api {
    /**
     * Define input parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([]);
    }

    /**
     * Execute method.
     *
     * @return array<string, mixed>
     */
    public static function execute() {
        global $USER;

        self::validate_parameters(self::execute_parameters(), []);

        require_login();
        if (isguestuser()) {
            throw new \moodle_exception('noguest');
        }

        if (!get_config('core_competency', 'enabled')) {
            return [
                'hasactiveplans' => false,
                'hasplancards' => false,
                'hascompetencies' => false,
                'plancards' => [],
                'competencycards' => [],
                'filtersettings' => dataset_provider::get_ui_config()['filtersettings'],
            ];
        }

        $usercontext = \context_user::instance($USER->id);
        self::validate_context($usercontext);

        $provider = new dataset_provider((int)$USER->id);
        $dataset = $provider->get_dataset();

        return [
            'hasactiveplans' => $dataset['hasactiveplans'],
            'hasplancards' => $dataset['hasplancards'],
            'hascompetencies' => $dataset['hascompetencies'],
            'plancards' => $dataset['plancards'],
            'competencycards' => $dataset['competencycards'],
            'filtersettings' => dataset_provider::get_ui_config()['filtersettings'],
        ];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns() {
        $trailitem = new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Competency id'),
            'shortname' => new external_value(PARAM_TEXT, 'Competency shortname'),
            'iscompleted' => new external_value(PARAM_BOOL, 'Whether the competency is completed'),
            'index' => new external_value(PARAM_INT, 'Index in the original trail list'),
            'url' => new external_value(PARAM_URL, 'View url'),
            'isfirst' => new external_value(PARAM_BOOL, 'First marker'),
            'islast' => new external_value(PARAM_BOOL, 'Last marker'),
        ]);

        $plancard = new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Plan id'),
            'name' => new external_value(PARAM_TEXT, 'Plan name'),
            'url' => new external_value(PARAM_URL, 'Plan url'),
            'imageurl' => new external_value(PARAM_RAW, 'Image url', VALUE_OPTIONAL),
            'hasimage' => new external_value(PARAM_BOOL, 'Has image'),
            'hastrail' => new external_value(PARAM_BOOL, 'Has trail'),
            'trail' => new external_multiple_structure($trailitem, 'Trail items'),
            'trailclickable' => new external_value(PARAM_BOOL, 'Trail clickable'),
            'competencycounttext' => new external_value(PARAM_TEXT, 'Competency count text'),
            'hasitemsbeforetrail' => new external_value(PARAM_BOOL, 'Has hidden items before trail'),
            'hasitemsaftertrail' => new external_value(PARAM_BOOL, 'Has hidden items after trail'),
            'bgcolor' => new external_value(PARAM_TEXT, 'Background color', VALUE_OPTIONAL),
            'hasbgcolor' => new external_value(PARAM_BOOL, 'Has background color'),
            'textcolor' => new external_value(PARAM_TEXT, 'Text color', VALUE_OPTIONAL),
            'hastextcolor' => new external_value(PARAM_BOOL, 'Has text color'),
            'tag1' => new external_value(PARAM_TEXT, 'Tag 1', VALUE_OPTIONAL),
            'hastag1' => new external_value(PARAM_BOOL, 'Has tag 1'),
            'tag2' => new external_value(PARAM_TEXT, 'Tag 2', VALUE_OPTIONAL),
            'hastag2' => new external_value(PARAM_BOOL, 'Has tag 2'),
            'showcardtitle' => new external_value(PARAM_BOOL, 'Show card title'),
            'buttonlabel' => new external_value(PARAM_TEXT, 'Button label'),
            'buttonarialabel' => new external_value(PARAM_TEXT, 'Button aria label'),
            'ishorizontal' => new external_value(PARAM_BOOL, 'Is horizontal'),
            'isvertical' => new external_value(PARAM_BOOL, 'Is vertical'),
        ]);

        $competencycard = new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Competency id'),
            'name' => new external_value(PARAM_TEXT, 'Competency name'),
            'url' => new external_value(PARAM_URL, 'Competency url'),
            'imageurl' => new external_value(PARAM_RAW, 'Image url', VALUE_OPTIONAL),
            'hasimage' => new external_value(PARAM_BOOL, 'Has image'),
            'tag1' => new external_value(PARAM_TEXT, 'Tag 1', VALUE_OPTIONAL),
            'hastag1' => new external_value(PARAM_BOOL, 'Has tag 1'),
            'tag2' => new external_value(PARAM_TEXT, 'Tag 2', VALUE_OPTIONAL),
            'hastag2' => new external_value(PARAM_BOOL, 'Has tag 2'),
            'bgcolor' => new external_value(PARAM_TEXT, 'Background color', VALUE_OPTIONAL),
            'hasbgcolor' => new external_value(PARAM_BOOL, 'Has background color'),
            'textcolor' => new external_value(PARAM_TEXT, 'Text color', VALUE_OPTIONAL),
            'hastextcolor' => new external_value(PARAM_BOOL, 'Has text color'),
            'showcardtitle' => new external_value(PARAM_BOOL, 'Show card title'),
            'buttonlabel' => new external_value(PARAM_TEXT, 'Button label'),
            'buttonarialabel' => new external_value(PARAM_TEXT, 'Button aria label'),
        ]);

        $filtersettings = new external_single_structure([
            'plan' => new external_single_structure([
                'tag1enabled' => new external_value(PARAM_BOOL, 'Plan tag1 enabled'),
                'tag2enabled' => new external_value(PARAM_BOOL, 'Plan tag2 enabled'),
                'tag1displaymode' => new external_value(PARAM_TEXT, 'Plan tag1 display mode'),
                'tag2displaymode' => new external_value(PARAM_TEXT, 'Plan tag2 display mode'),
            ]),
            'competency' => new external_single_structure([
                'tag1enabled' => new external_value(PARAM_BOOL, 'Competency tag1 enabled'),
                'tag2enabled' => new external_value(PARAM_BOOL, 'Competency tag2 enabled'),
                'tag1displaymode' => new external_value(PARAM_TEXT, 'Competency tag1 display mode'),
                'tag2displaymode' => new external_value(PARAM_TEXT, 'Competency tag2 display mode'),
            ]),
        ]);

        return new external_single_structure([
            'hasactiveplans' => new external_value(PARAM_BOOL, 'Has active plans'),
            'hasplancards' => new external_value(PARAM_BOOL, 'Has plan cards'),
            'hascompetencies' => new external_value(PARAM_BOOL, 'Has competency cards'),
            'plancards' => new external_multiple_structure($plancard, 'Plan cards'),
            'competencycards' => new external_multiple_structure($competencycard, 'Competency cards'),
            'filtersettings' => $filtersettings,
        ]);
    }
}
