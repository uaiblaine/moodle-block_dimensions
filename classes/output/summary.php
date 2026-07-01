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
 * Summary renderable.
 *
 * @package    block_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_dimensions\output;

use renderable;
use renderer_base;
use templatable;
use block_dimensions\local\dataset_provider;

/**
 * Summary renderable class.
 *
 * @package    block_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class summary implements renderable, templatable {
    /** @var stdClass The user. */
    protected $user;

    /**
     * Constructor.
     * @param stdClass $user The user.
     */
    public function __construct($user = null) {
        global $USER;
        if (!$user) {
            $user = $USER;
        }
        $this->user = $user;
    }

    /**
     * Export data for template.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output) {
        $uiconfig = dataset_provider::get_ui_config();

        $containerid = 'block-dimensions-' . uniqid();
        $labels = [
            'filterall' => get_string('filterall', 'block_dimensions'),
            'noactiveplans' => get_string('noactiveplans', 'block_dimensions'),
            'nocompetencies' => get_string('nocompetencies', 'block_dimensions'),
            'loaderror' => get_string('loaderror', 'block_dimensions'),
            'myfavourites' => get_string('myfavourites', 'block_dimensions'),
            'addtofavourites' => get_string('addtofavourites', 'block_dimensions'),
            'removefromfavourites' => get_string('removefromfavourites', 'block_dimensions'),
            'showallitems' => get_string('showallitems', 'block_dimensions'),
            'ghostcardsubtitle' => get_string('ghostcardsubtitle', 'block_dimensions'),
            'ghostcardtitle' => get_string('ghostcardtitle', 'block_dimensions'),
            'togglefilters' => get_string('togglefilters', 'block_dimensions'),
            'clearfilters' => get_string('clearfilters', 'block_dimensions'),
            'favouriteerror' => get_string('favouriteerror', 'block_dimensions'),
            'resultsfound' => get_string('resultsfound', 'block_dimensions'),
            'resultsnonefound' => get_string('resultsnonefound', 'block_dimensions'),
            'paddleleft' => get_string('paddleleft', 'block_dimensions'),
            'paddleright' => get_string('paddleright', 'block_dimensions'),
            'filterbyplan' => get_string('filterbyplan', 'block_dimensions'),
            'filterbycompetency' => get_string('filterbycompetency', 'block_dimensions'),
        ];

        return [
            'containerid' => $containerid,
            'showheading' => $uiconfig['showheading'],
            'showsearch' => $uiconfig['showsearch'],
            'showsectionheaders' => $uiconfig['showsectionheaders'],
            'endpointmethod' => 'block_dimensions_get_block_dataset',
            'filtersettingsjson' => json_encode($uiconfig['filtersettings']),
            'labelsjson' => json_encode($labels),
            'favouritesenabled' => $uiconfig['favouritesenabled'] ? 'true' : 'false',
        ];
    }

    /**
     * Returns whether there is content in the summary.
     *
     * @return boolean
     */
    public function has_content() {
        $provider = new dataset_provider((int)$this->user->id);
        return $provider->has_active_plans();
    }
}
