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

use core_competency\api;
use core_competency\plan;
use renderable;
use renderer_base;
use templatable;
use moodle_url;
use local_dimensions\picture_manager;
use local_dimensions\constants;
use local_dimensions\template_metadata_cache;
use block_dimensions\local\dataset_provider;

/**
 * Summary renderable class.
 *
 * @package    block_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class summary implements renderable, templatable {
    /** @var array Active plans. */
    protected $activeplans = [];
    /** @var array Plans. */
    protected $plans = [];
    /** @var stdClass The user. */
    protected $user;

    /** @var array Static cache for custom field definitions (keyed by shortname_area). */
    protected static $fieldcache = [];

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
     * Get the card image URL for a competency.
     *
     * @param int $competencyid The competency ID
     * @return string|null The image URL or null if not found
     */
    protected function get_competency_card_image(int $competencyid): ?string {
        // Built-in mode: try picture_manager first, fall back to external storage.
        if (picture_manager::is_builtin_mode()) {
            $url = picture_manager::get_image_url('competency', $competencyid, 'cardimage');
            if ($url) {
                return $url;
            }
            // Fall through to check external storage for legacy images.
        }

        // External mode (or fallback): use customfield_picture component.
        global $DB;

        // Get the cached custom field definition for 'customcard' in the local_dimensions competency area.
        $field = $this->get_field_definition(constants::CFIELD_CUSTOMCARD, 'competency');

        if (!$field) {
            return null;
        }

        // Get the custom field data for this competency.
        $data = $DB->get_record('customfield_data', [
            'fieldid' => $field->id,
            'instanceid' => $competencyid,
        ]);

        if (!$data) {
            return null;
        }

        // Get the file from storage (using customfield_picture component).
        $fs = get_file_storage();
        $files = $fs->get_area_files(
            $data->contextid,
            'customfield_picture',
            'file',
            $data->id,
            '',
            false
        );

        if (empty($files)) {
            return null;
        }

        $file = reset($files);
        return moodle_url::make_pluginfile_url(
            $file->get_contextid(),
            $file->get_component(),
            $file->get_filearea(),
            $file->get_itemid(),
            $file->get_filepath(),
            $file->get_filename()
        )->out();
    }


    /**
     * Get tag custom field values for a competency.
     *
     * @param int $competencyid The competency ID
     * @return array Array with 'tag1', 'tag2' keys and their string values (null if not set)
     */
    protected function get_competency_tags(int $competencyid): array {
        return [
            'tag1' => $this->get_select_field_value($competencyid, constants::CFIELD_TAG1, 'competency'),
            'tag2' => $this->get_select_field_value($competencyid, constants::CFIELD_TAG2, 'competency'),
        ];
    }

    /**
     * Get a select field value (the selected option label) for an instance.
     *
     * @param int $instanceid The instance ID (competency or template)
     * @param string $shortname The field shortname
     * @param string $area The custom field area (competency or lp)
     * @return string|null The selected option label or null if not found
     */
    protected function get_select_field_value(int $instanceid, string $shortname, string $area): ?string {
        global $DB;

        // Get the cached field definition with configdata for options.
        $field = $this->get_field_definition($shortname, $area);

        if (!$field) {
            return null;
        }

        // Get the data for this instance.
        $data = $DB->get_record('customfield_data', [
            'fieldid' => $field->id,
            'instanceid' => $instanceid,
        ]);

        if (!$data) {
            return null;
        }

        // For select fields, intvalue contains the 1-based index of selected option.
        $selectedindex = (int) $data->intvalue;
        if ($selectedindex <= 0) {
            return null;
        }

        // Decode configdata to get options.
        $config = json_decode($field->configdata, true);
        if (empty($config['options'])) {
            return null;
        }

        // Options are stored as newline-separated string.
        $options = explode("\n", $config['options']);
        $optionindex = $selectedindex - 1; // Convert to 0-based.

        if (!isset($options[$optionindex])) {
            return null;
        }

        return trim($options[$optionindex]);
    }

    /**
     * Get a custom field definition from cache, or fetch and cache it.
     *
     * @param string $shortname The field shortname
     * @param string $area The custom field area (competency or lp)
     * @return object|false The field record, or false if not found
     */
    protected function get_field_definition(string $shortname, string $area) {
        $key = "{$shortname}_{$area}";
        if (!isset(self::$fieldcache[$key])) {
            global $DB;
            $sql = "SELECT f.*
                      FROM {customfield_field} f
                      JOIN {customfield_category} c ON c.id = f.categoryid
                     WHERE f.shortname = :shortname
                       AND c.component = :component
                       AND c.area = :area";
            self::$fieldcache[$key] = $DB->get_record_sql($sql, [
                'shortname' => $shortname,
                'component' => 'local_dimensions',
                'area' => $area,
            ]);
        }
        return self::$fieldcache[$key];
    }

    /**
     * Get competency IDs that have at least one visible linked course.
     *
     * @param array $competencyids Array of competency IDs to check
     * @return array Associative array keyed by competency ID (values are records)
     */
    protected function get_competencies_with_courses(array $competencyids): array {
        global $DB;
        if (empty($competencyids)) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($competencyids, SQL_PARAMS_NAMED);
        $sql = "SELECT DISTINCT cc.competencyid
                  FROM {competency_coursecomp} cc
                  JOIN {course} c ON c.id = cc.courseid
                 WHERE cc.competencyid $insql AND c.visible = 1";
        return $DB->get_records_sql($sql, $params);
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

        return [
            'containerid' => $containerid,
            'showheading' => $uiconfig['showheading'],
            'showsearch' => $uiconfig['showsearch'],
            'endpointmethod' => 'block_dimensions_get_block_dataset',
            'filtersettingsjson' => json_encode($uiconfig['filtersettings']),
        ];
    }

    /**
     * Build filter configuration for a set of cards.
     *
     * Reads admin settings and extracts unique tag values from the cards
     * to build the filter options for the template.
     *
     * @param array $cards The cards data array (competencycards or plancards)
     * @param string $type The type: 'competency' or 'plan'
     * @param string $component The component name for get_config
     * @return array Array of filter configurations for the template
     */
    protected function build_filter_config(array $cards, string $type, string $component): array {
        $filters = [];
        $filterall = get_string('filterall', 'block_dimensions');

        foreach (['tag1', 'tag2'] as $tag) {
            $enablekey = "enable_{$type}_{$tag}_filter";
            $modekey = "{$type}_{$tag}_displaymode";

            $enabled = (bool) get_config($component, $enablekey);
            if (!$enabled) {
                continue;
            }

            $displaymode = get_config($component, $modekey) ?: 'tabs';

            // Collect unique non-empty values from cards.
            $values = [];
            foreach ($cards as $card) {
                if (!empty($card[$tag])) {
                    $values[$card[$tag]] = true;
                }
            }

            // If no values found, skip this filter.
            if (empty($values)) {
                continue;
            }

            $options = [];
            foreach (array_keys($values) as $value) {
                $options[] = [
                    'value' => $value,
                    'label' => $value,
                ];
            }

            // Sort options alphabetically.
            usort($options, function ($a, $b) {
                return strcmp($a['label'], $b['label']);
            });

            $filters[] = [
                'field' => "{$type}_{$tag}",
                'tag' => $tag,
                'type' => $type,
                'label' => $tag,
                'istabs' => ($displaymode === 'tabs'),
                'isdropdown' => ($displaymode === 'dropdown'),
                'alllabel' => $filterall,
                'options' => $options,
            ];
        }

        return $filters;
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

    /**
     * Select 5 competencies centered on the last completed one.
     *
     * Algorithm:
     * - If no completed: show first 5
     * - If all completed: show last 5
     * - Otherwise: center on last completed
     *
     * @param array $competencies Array of competency data
     * @param int $lastcompletedindex Index of last completed (-1 if none)
     * @return array Selected competencies with position markers
     */
    protected function select_trail_competencies(array $competencies, int $lastcompletedindex): array {
        $total = count($competencies);
        $maxitems = 5;

        // If 5 or fewer, return all.
        if ($total <= $maxitems) {
            return $this->add_trail_positions($competencies);
        }

        $start = $this->get_trail_start_index($total, $lastcompletedindex, $maxitems);

        $selected = array_slice($competencies, $start, $maxitems);
        return $this->add_trail_positions($selected);
    }

    /**
     * Get the start index for the competency trail window.
     *
     * @param int $total Total number of competencies
     * @param int $lastcompletedindex Index of last completed (-1 if none)
     * @param int $maxitems Max competencies to display in the trail
     * @return int Start index for array_slice
     */
    protected function get_trail_start_index(int $total, int $lastcompletedindex, int $maxitems = 5): int {
        if ($total <= $maxitems) {
            return 0;
        }

        if ($lastcompletedindex < 0) {
            return 0;
        }

        if ($lastcompletedindex >= $total - 1) {
            return $total - $maxitems;
        }

        $halfwindow = floor($maxitems / 2);
        $start = max(0, $lastcompletedindex - $halfwindow);

        // Adjust if window extends beyond end.
        if ($start + $maxitems > $total) {
            $start = $total - $maxitems;
        }

        return $start;
    }

    /**
     * Add position markers to trail competencies.
     *
     * @param array $competencies
     * @return array Competencies with position markers (isfirst, islast)
     */
    protected function add_trail_positions(array $competencies): array {
        $count = count($competencies);
        foreach ($competencies as $i => &$comp) {
            $comp['isfirst'] = ($i === 0);
            $comp['islast'] = ($i === $count - 1);
        }
        return $competencies;
    }
}
