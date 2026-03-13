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
use required_capability_exception;
use moodle_url;
use local_dimensions\picture_manager;
use local_dimensions\constants;

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

        // Get the plans.
        try {
            $this->plans = api::list_user_plans($this->user->id);
        } catch (required_capability_exception $e) {
            $this->plans = [];
        }
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
     * Get the card image URL for a learning plan template.
     *
     * @param int $templateid The template ID
     * @return string|null The image URL or null if not found
     */
    protected function get_template_card_image(int $templateid): ?string {
        // Built-in mode: try picture_manager first, fall back to external storage.
        if (picture_manager::is_builtin_mode()) {
            $url = picture_manager::get_image_url('lp', $templateid, 'cardimage');
            if ($url) {
                return $url;
            }
            // Fall through to check external storage for legacy images.
        }

        // External mode (or fallback): use customfield_picture component.
        global $DB;

        // Get the cached custom field definition for 'customcard' in the local_dimensions lp area.
        $field = $this->get_field_definition(constants::CFIELD_CUSTOMCARD, 'lp');

        if (!$field) {
            return null;
        }

        // Get the custom field data for this template.
        $data = $DB->get_record('customfield_data', [
            'fieldid' => $field->id,
            'instanceid' => $templateid,
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
     * Get a custom field value for a learning plan template.
     *
     * @param int $templateid The template ID
     * @param string $shortname The field shortname to retrieve
     * @return string|null The field value or null if not found
     */
    protected function get_template_custom_field(int $templateid, string $shortname): ?string {
        global $DB;

        // Get the cached field definition.
        $field = $this->get_field_definition($shortname, 'lp');

        if (!$field) {
            return null;
        }

        // Get the data for this instance.
        $data = $DB->get_record('customfield_data', [
            'fieldid' => $field->id,
            'instanceid' => $templateid,
        ]);

        if (!$data || empty($data->value)) {
            return null;
        }

        // Validate hex color.
        $value = trim($data->value);
        if (preg_match('/^#?([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $value)) {
            if ($value[0] !== '#') {
                $value = '#' . $value;
            }
            return $value;
        }

        return null;
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
     * Get tag custom field values for a learning plan template.
     *
     * @param int $templateid The template ID
     * @return array Array with 'tag1', 'tag2' keys and their string values (null if not set)
     */
    protected function get_template_tags(int $templateid): array {
        return [
            'tag1' => $this->get_select_field_value($templateid, constants::CFIELD_TAG1, 'lp'),
            'tag2' => $this->get_select_field_value($templateid, constants::CFIELD_TAG2, 'lp'),
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
        // Filter active plans.
        $activeplans = [];
        foreach ($this->plans as $plan) {
            if ($plan->get('status') == plan::STATUS_ACTIVE) {
                $activeplans[] = $plan;
            }
        }

        // Build cards based on display mode.
        $competencycards = [];
        $plancards = [];
        $seencompetencies = []; // Avoid duplicates across plans.

        foreach ($activeplans as $plan) {
            $planid = $plan->get('id');
            $templateid = $plan->get('templateid');

            // Get display mode based on template or default.
            // Individual plans (no template) default to PLAN mode.
            // Template-based plans use the template's configured display mode.
            if ($templateid) {
                $displaymode = \local_dimensions\helper::get_template_display_mode($templateid);
            } else {
                // Individual plan - default to PLAN mode.
                $displaymode = \local_dimensions\constants::DISPLAYMODE_PLAN;
            }

            // If display mode is PLAN, add the plan as a card with competency trail.
            if ($displaymode == \local_dimensions\constants::DISPLAYMODE_PLAN) {
                // Build plan card.
                $viewurl = new moodle_url('/local/dimensions/view-plan.php', [
                    'id' => $planid,
                ]);

                // Build plan competency count text with optional custom suffix from template.
                $competencytypesuffix = get_string('competency_count_suffix', 'block_dimensions');
                if ($templateid) {
                    $customtype = $this->get_select_field_value($templateid, 'local_dimensions_type', 'lp');
                    if (!empty($customtype)) {
                        $competencytypesuffix = format_string(trim($customtype));
                    }
                }
                $totalcompetencies = 0;
                $hasitemsbeforetrail = false;
                $hasitemsaftertrail = false;

                // Get template custom card image first, fallback to first competency.
                $imageurl = null;
                if ($templateid) {
                    $imageurl = $this->get_template_card_image($templateid);
                }

                // Get template color custom fields.
                $bgcolor = null;
                $textcolor = null;
                $tags = ['tag1' => null, 'tag2' => null];
                if ($templateid) {
                    $bgcolor = $this->get_template_custom_field($templateid, constants::CFIELD_CUSTOMBGCOLOR);
                    $textcolor = $this->get_template_custom_field($templateid, constants::CFIELD_CUSTOMTEXTCOLOR);
                    $tags = $this->get_template_tags($templateid);
                }

                // Get competencies with proficiency status.
                $competencytrail = [];
                try {
                    $competencies = api::list_plan_competencies($plan);
                    $totalcompetencies = count($competencies);
                    if (!empty($competencies)) {
                        // If no template image, use first competency's image as fallback.
                        if (!$imageurl) {
                            $firstcomp = reset($competencies);
                            $imageurl = $this->get_competency_card_image($firstcomp->competency->get('id'));
                        }

                        // Build competency data with proficiency status.
                        $competencydata = [];
                        $lastcompletedindex = -1;
                        $index = 0;

                        foreach ($competencies as $compdata) {
                            $competency = $compdata->competency;
                            $competencyid = $competency->get('id');

                            // Check user proficiency — data already in list_plan_competencies() result.
                            $isproficient = false;
                            if ($compdata->usercompetency && $compdata->usercompetency->get('proficiency')) {
                                $isproficient = true;
                                $lastcompletedindex = $index;
                            }

                            $competencydata[] = [
                                'id' => $competencyid,
                                'shortname' => format_string($competency->get('shortname')),
                                'iscompleted' => $isproficient,
                                'index' => $index,
                                'url' => (new moodle_url('/local/dimensions/view-plan.php', [
                                    'id' => $planid,
                                    'competencyid' => $competencyid,
                                ]))->out(false),
                            ];
                            $index++;
                        }

                        $trailstartindex = $this->get_trail_start_index(count($competencydata), $lastcompletedindex);

                        // Select 5 competencies centered on last completed.
                        $competencytrail = $this->select_trail_competencies(
                            $competencydata,
                            $lastcompletedindex
                        );

                        $hasitemsbeforetrail = ($trailstartindex > 0);
                        $hasitemsaftertrail = (($trailstartindex + count($competencytrail)) < count($competencydata));
                    }
                } catch (\Exception $e) {
                    // Ignore competency errors.
                    debugging('Error processing competencies for trail: ' . $e->getMessage(), DEBUG_DEVELOPER);
                }

                $competencycounttext = $totalcompetencies . ' ' . $competencytypesuffix;

                // Check if trail competencies should be clickable.
                $trailclickable = (bool) get_config('block_dimensions', 'enable_trail_links');

                // Determine access button label: "Continue" if partial progress, "Access" otherwise.
                $haspartialtrail = false;
                if (!empty($competencytrail)) {
                    $completedcount = 0;
                    foreach ($competencytrail as $item) {
                        if (!empty($item['iscompleted'])) {
                            $completedcount++;
                        }
                    }
                    $totaltrail = count($competencytrail);
                    if ($completedcount > 0 && $completedcount < $totaltrail) {
                        $haspartialtrail = true;
                    }
                }

                $planname = format_string($plan->get('name'));
                if ($haspartialtrail) {
                    $buttonlabel = get_string('continuecard', 'block_dimensions');
                    $buttonarialabel = get_string('continuecardaria', 'block_dimensions', $planname);
                } else {
                    $buttonlabel = get_string('accesscard', 'block_dimensions');
                    $buttonarialabel = get_string('accesscardaria', 'block_dimensions', $planname);
                }

                $layoutmode = get_config('block_dimensions', 'plancard_layout') ?: 'vertical';

                $plancards[] = [
                    'id' => $planid,
                    'name' => $planname,
                    'url' => $viewurl->out(false),
                    'imageurl' => $imageurl,
                    'hasimage' => !empty($imageurl),
                    'hastrail' => !empty($competencytrail),
                    'trail' => $competencytrail,
                    'trailclickable' => $trailclickable,
                    'competencycounttext' => $competencycounttext,
                    'hasitemsbeforetrail' => $hasitemsbeforetrail,
                    'hasitemsaftertrail' => $hasitemsaftertrail,
                    'bgcolor' => $bgcolor,
                    'hasbgcolor' => !empty($bgcolor),
                    'textcolor' => $textcolor,
                    'hastextcolor' => !empty($textcolor),
                    'tag1' => $tags['tag1'],
                    'hastag1' => !empty($tags['tag1']),
                    'tag2' => $tags['tag2'],
                    'hastag2' => !empty($tags['tag2']),
                    'showcardtitle' => true,
                    'buttonlabel' => $buttonlabel,
                    'buttonarialabel' => $buttonarialabel,
                    'ishorizontal' => ($layoutmode === 'horizontal'),
                    'isvertical' => ($layoutmode !== 'horizontal'),
                ];
                continue;
            }

            // Default: Display mode is COMPETENCIES.
            try {
                $competencies = api::list_plan_competencies($plan);
            } catch (\Exception $e) {
                continue;
            }

            // Pre-fetch which competencies have visible linked courses (single query).
            $allcompids = array_map(fn($c) => $c->competency->get('id'), $competencies);
            $competencieswithcourses = $this->get_competencies_with_courses($allcompids);

            foreach ($competencies as $compdata) {
                $competency = $compdata->competency;
                $competencyid = $competency->get('id');

                // Skip if we've already seen this competency.
                if (isset($seencompetencies[$competencyid])) {
                    continue;
                }

                // Skip competencies without linked courses.
                if (!isset($competencieswithcourses[$competencyid])) {
                    $seencompetencies[$competencyid] = true;
                    continue;
                }

                $seencompetencies[$competencyid] = true;

                // Build the view URL.
                $viewurl = new moodle_url('/local/dimensions/view-plan.php', [
                    'id' => $planid,
                    'competencyid' => $competencyid,
                ]);

                // Get the card image.
                $imageurl = $this->get_competency_card_image($competencyid);

                // Get tags for this competency.
                $tags = $this->get_competency_tags($competencyid);

                $compname = format_string($competency->get('shortname'));
                $competencycards[] = [
                    'id' => $competencyid,
                    'name' => $compname,
                    'url' => $viewurl->out(false),
                    'imageurl' => $imageurl,
                    'hasimage' => !empty($imageurl),
                    'tag1' => $tags['tag1'],
                    'hastag1' => !empty($tags['tag1']),
                    'tag2' => $tags['tag2'],
                    'hastag2' => !empty($tags['tag2']),
                    'showcardtitle' => true,
                    'buttonlabel' => get_string('accesscard', 'block_dimensions'),
                    'buttonarialabel' => get_string('accesscardaria', 'block_dimensions', $compname),
                ];
            }
        }

        // Read admin settings for display visibility.
        $showheading = (bool) get_config('block_dimensions', 'show_heading');
        $showsearch = (bool) get_config('block_dimensions', 'enable_search');

        // If show_heading has never been set (null), default to true.
        if (get_config('block_dimensions', 'show_heading') === false) {
            $showheading = true;
        }

        // Build filter configuration for the template.
        $competencyfilters = [];
        $planfilters = [];

        // Competency filters — only if there are competency cards.
        if (!empty($competencycards)) {
            $competencyfilters = $this->build_filter_config(
                $competencycards,
                'competency',
                'block_dimensions'
            );
        }

        // Plan filters — only if there are plan cards.
        if (!empty($plancards)) {
            $planfilters = $this->build_filter_config(
                $plancards,
                'plan',
                'block_dimensions'
            );
        }

        $hascompetencyfilters = !empty($competencyfilters);
        $hasplanfilters = !empty($planfilters);

        $data = [
            'hascompetencies' => !empty($competencycards),
            'competencycards' => $competencycards,
            'hasplans' => !empty($this->plans),
            'hasactiveplans' => !empty($activeplans),
            'hasplancards' => !empty($plancards),
            'plancards' => $plancards,
            'showheading' => $showheading,
            'showsearch' => $showsearch,
            'hascompetencyfilters' => $hascompetencyfilters,
            'competencyfilterdata' => ['filters' => $competencyfilters],
            'hasplanfilters' => $hasplanfilters,
            'planfilterdata' => ['filters' => $planfilters],
        ];

        return $data;
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
        // Check if user has any active plans.
        foreach ($this->plans as $plan) {
            if ($plan->get('status') == plan::STATUS_ACTIVE) {
                return true;
            }
        }
        return false;
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
