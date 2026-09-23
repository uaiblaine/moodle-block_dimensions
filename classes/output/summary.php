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
use block_dimensions\local\bootstrap;
use block_dimensions\local\dataset_provider;

/**
 * Summary renderable class.
 *
 * The block's server-rendered shell carries labels and settings only; amd/src/filters.js fetches
 * the cards through block_dimensions_get_block_dataset and renders them client-side.
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
     * @param stdClass|null $user The user, or null for the current user.
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
        // The strings amd/src/filters.js writes into its own markup come only from here; a missing key renders wrong, silently.
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
            'statusfilter' => get_string('statusfilter', 'block_dimensions'),
            'statusactive' => get_string('statusactive', 'block_dimensions'),
            'statusreview' => get_string('statusreview', 'block_dimensions'),
            'statuscomplete' => get_string('statuscomplete', 'block_dimensions'),
            'statusloadingreview' => get_string('statusloadingreview', 'block_dimensions'),
            'statusloadingcomplete' => get_string('statusloadingcomplete', 'block_dimensions'),
        ];

        return [
            'containerid' => $containerid,
            /* Gates the Bootstrap 4 utility polyfill in styles.css. A block cannot use a body
               class - see block_dimensions\local\bootstrap for why - so the marker rides the
               block's own root element instead. */
            'isbs4' => bootstrap::is_bs4(),
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
     * Whether the block has anything to show: the user holds a plan in one of the status buckets
     * the block renders - active, in review or completed.
     *
     * A plain draft does not count: no bucket shows one, so the block would open empty. The plan
     * list comes from the same provider the web service uses, so this gate and the dataset cannot
     * disagree about the same user.
     *
     * A failure reading the plan list answers true (fails open). The block renders inline with the
     * page and core's block manager catches nothing, so a throw here would replace the whole page
     * with an error; an open gate renders the shell, and the web service reads the plans again and
     * shows its own error with a retry button. Failing closed would hide the block silently for
     * everyone while the failure repeats. The failure is logged with error_log() rather than
     * debugging(): with developer debugging and pretty exceptions, Whoops turns debugging() during
     * a page render into an ErrorException, the very throw this catch prevents.
     *
     * @return bool
     */
    public function has_content() {
        try {
            return $this->create_dataset_provider()->has_displayable_plans();
        } catch (\Throwable $e) {
            // The suggested debugging() throws under Whoops during a page render; see the docblock.
            // phpcs:ignore moodle.PHP.ForbiddenFunctions.FoundWithAlternative
            error_log('block_dimensions: reading the plan list for the block gate failed: ' . $e->getMessage());
            return true;
        }
    }

    /**
     * Build the provider the gate reads the plan list from.
     *
     * Extracted so a test can make the read fail without breaking the database.
     *
     * @return dataset_provider
     */
    protected function create_dataset_provider(): dataset_provider {
        return new dataset_provider((int)$this->user->id);
    }
}
