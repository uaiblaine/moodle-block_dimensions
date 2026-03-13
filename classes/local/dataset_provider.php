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
 * Shared dataset provider for block_dimensions.
 *
 * @package    block_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_dimensions\local;

use core_competency\api;
use core_competency\plan;
use local_dimensions\constants;
use local_dimensions\picture_manager;
use local_dimensions\template_metadata_cache;
use moodle_url;
use required_capability_exception;

/**
 * Shared dataset provider for summary and AJAX endpoint.
 *
 * @package    block_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class dataset_provider {
    /** @var int User id. */
    protected $userid;

    /** @var array Plans for the user. */
    protected $plans = [];

    /** @var array Static cache for custom field definitions (keyed by shortname_area). */
    protected static $fieldcache = [];

    /**
     * Constructor.
     *
     * @param int $userid User id.
     */
    public function __construct(int $userid) {
        $this->userid = $userid;

        try {
            $this->plans = api::list_user_plans($this->userid);
        } catch (required_capability_exception $e) {
            $this->plans = [];
        }
    }

    /**
     * Whether the user has active plans.
     *
     * @return bool
     */
    public function has_active_plans(): bool {
        foreach ($this->plans as $plan) {
            if ($plan->get('status') == plan::STATUS_ACTIVE) {
                return true;
            }
        }

        return false;
    }

    /**
     * Build the full non-paginated dataset for frontend rendering.
     *
     * @return array<string, mixed>
     */
    public function get_dataset(): array {
        $activeplans = [];
        foreach ($this->plans as $plan) {
            if ($plan->get('status') == plan::STATUS_ACTIVE) {
                $activeplans[] = $plan;
            }
        }

        $competencycards = [];
        $plancards = [];
        $seencompetencies = [];

        foreach ($activeplans as $plan) {
            $planid = $plan->get('id');
            $templateid = $plan->get('templateid');

            if ($templateid) {
                $displaymode = \local_dimensions\helper::get_template_display_mode($templateid);
            } else {
                $displaymode = constants::DISPLAYMODE_PLAN;
            }

            if ($displaymode == constants::DISPLAYMODE_PLAN) {
                $plancards[] = $this->build_plan_card($plan, $templateid, $planid);
                continue;
            }

            try {
                $competencies = api::list_plan_competencies($plan);
            } catch (\Exception $e) {
                continue;
            }

            $allcompids = array_map(fn($c) => $c->competency->get('id'), $competencies);
            $competencieswithcourses = $this->get_competencies_with_courses($allcompids);

            foreach ($competencies as $compdata) {
                $competency = $compdata->competency;
                $competencyid = $competency->get('id');

                if (isset($seencompetencies[$competencyid])) {
                    continue;
                }

                if (!isset($competencieswithcourses[$competencyid])) {
                    $seencompetencies[$competencyid] = true;
                    continue;
                }

                $seencompetencies[$competencyid] = true;
                $competencycards[] = $this->build_competency_card($planid, $competencyid, $competency);
            }
        }

        return [
            'hasactiveplans' => !empty($activeplans),
            'hasplancards' => !empty($plancards),
            'hascompetencies' => !empty($competencycards),
            'plancards' => $plancards,
            'competencycards' => $competencycards,
        ];
    }

    /**
     * Read UI config flags from plugin settings.
     *
     * @return array<string, mixed>
     */
    public static function get_ui_config(): array {
        $showheading = (bool) get_config('block_dimensions', 'show_heading');
        if (get_config('block_dimensions', 'show_heading') === false) {
            $showheading = true;
        }

        return [
            'showheading' => $showheading,
            'showsearch' => (bool) get_config('block_dimensions', 'enable_search'),
            'trailclickable' => (bool) get_config('block_dimensions', 'enable_trail_links'),
            'plancardlayout' => get_config('block_dimensions', 'plancard_layout') ?: 'vertical',
            'filtersettings' => [
                'plan' => [
                    'tag1enabled' => (bool) get_config('block_dimensions', 'enable_plan_tag1_filter'),
                    'tag2enabled' => (bool) get_config('block_dimensions', 'enable_plan_tag2_filter'),
                    'tag1displaymode' => get_config('block_dimensions', 'plan_tag1_displaymode') ?: 'tabs',
                    'tag2displaymode' => get_config('block_dimensions', 'plan_tag2_displaymode') ?: 'tabs',
                ],
                'competency' => [
                    'tag1enabled' => (bool) get_config('block_dimensions', 'enable_competency_tag1_filter'),
                    'tag2enabled' => (bool) get_config('block_dimensions', 'enable_competency_tag2_filter'),
                    'tag1displaymode' => get_config('block_dimensions', 'competency_tag1_displaymode') ?: 'tabs',
                    'tag2displaymode' => get_config('block_dimensions', 'competency_tag2_displaymode') ?: 'tabs',
                ],
            ],
        ];
    }

    /**
     * Build a single plan card payload.
     *
     * @param \core_competency\plan $plan Plan object.
     * @param int|null $templateid Template id.
     * @param int $planid Plan id.
     * @return array<string, mixed>
     */
    protected function build_plan_card(\core_competency\plan $plan, ?int $templateid, int $planid): array {
        $viewurl = new moodle_url('/local/dimensions/view-plan.php', ['id' => $planid]);

        $competencytypesuffix = get_string('competency_count_suffix', 'block_dimensions');
        $templatemetadata = [];

        if ($templateid) {
            $templatemetadata = template_metadata_cache::get_template_metadata($templateid);
            $customtype = $templatemetadata['type'] ?? null;
            if (!empty($customtype)) {
                $competencytypesuffix = format_string(trim($customtype));
            }
        }

        $imageurl = $templateid ? ($templatemetadata['templatecardimageurl'] ?? null) : null;
        $bgcolor = $templateid ? ($templatemetadata['bgcolor'] ?? null) : null;
        $textcolor = $templateid ? ($templatemetadata['textcolor'] ?? null) : null;
        $tags = [
            'tag1' => $templateid ? ($templatemetadata['tag1'] ?? null) : null,
            'tag2' => $templateid ? ($templatemetadata['tag2'] ?? null) : null,
        ];

        $totalcompetencies = 0;
        $hasitemsbeforetrail = false;
        $hasitemsaftertrail = false;
        $competencytrail = [];

        try {
            $competencies = api::list_plan_competencies($plan);
            $totalcompetencies = count($competencies);

            if (!empty($competencies)) {
                if (!$imageurl) {
                    $firstcomp = reset($competencies);
                    $imageurl = $this->get_competency_card_image($firstcomp->competency->get('id'));
                }

                $competencydata = [];
                $lastcompletedindex = -1;
                $index = 0;

                foreach ($competencies as $compdata) {
                    $competency = $compdata->competency;
                    $competencyid = $competency->get('id');

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
                $competencytrail = $this->select_trail_competencies($competencydata, $lastcompletedindex);

                $hasitemsbeforetrail = ($trailstartindex > 0);
                $hasitemsaftertrail = (($trailstartindex + count($competencytrail)) < count($competencydata));
            }
        } catch (\Exception $e) {
            debugging('Error processing competencies for trail: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }

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

        return [
            'id' => $planid,
            'name' => $planname,
            'url' => $viewurl->out(false),
            'imageurl' => $imageurl,
            'hasimage' => !empty($imageurl),
            'hastrail' => !empty($competencytrail),
            'trail' => $competencytrail,
            'trailclickable' => (bool) get_config('block_dimensions', 'enable_trail_links'),
            'competencycounttext' => $totalcompetencies . ' ' . $competencytypesuffix,
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
    }

    /**
     * Build a single competency card payload.
     *
     * @param int $planid Plan id.
     * @param int $competencyid Competency id.
     * @param \core_competency\competency $competency Competency object.
     * @return array<string, mixed>
     */
    protected function build_competency_card(int $planid, int $competencyid, \core_competency\competency $competency): array {
        $viewurl = new moodle_url('/local/dimensions/view-plan.php', [
            'id' => $planid,
            'competencyid' => $competencyid,
        ]);

        $imageurl = $this->get_competency_card_image($competencyid);
        $tags = $this->get_competency_tags($competencyid);

        $compname = format_string($competency->get('shortname'));

        return [
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

    /**
     * Get competency card image URL.
     *
     * @param int $competencyid Competency id.
     * @return string|null
     */
    protected function get_competency_card_image(int $competencyid): ?string {
        if (picture_manager::is_builtin_mode()) {
            $url = picture_manager::get_image_url('competency', $competencyid, 'cardimage');
            if ($url) {
                return $url;
            }
        }

        global $DB;

        $field = $this->get_field_definition(constants::CFIELD_CUSTOMCARD, 'competency');
        if (!$field) {
            return null;
        }

        $data = $DB->get_record('customfield_data', [
            'fieldid' => $field->id,
            'instanceid' => $competencyid,
        ]);

        if (!$data) {
            return null;
        }

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
     * Get competency tags.
     *
     * @param int $competencyid Competency id.
     * @return array<string, string|null>
     */
    protected function get_competency_tags(int $competencyid): array {
        return [
            'tag1' => $this->get_select_field_value($competencyid, constants::CFIELD_TAG1, 'competency'),
            'tag2' => $this->get_select_field_value($competencyid, constants::CFIELD_TAG2, 'competency'),
        ];
    }

    /**
     * Get select field value label.
     *
     * @param int $instanceid Instance id.
     * @param string $shortname Field shortname.
     * @param string $area Area.
     * @return string|null
     */
    protected function get_select_field_value(int $instanceid, string $shortname, string $area): ?string {
        global $DB;

        $field = $this->get_field_definition($shortname, $area);
        if (!$field) {
            return null;
        }

        $data = $DB->get_record('customfield_data', [
            'fieldid' => $field->id,
            'instanceid' => $instanceid,
        ]);

        if (!$data) {
            return null;
        }

        $selectedindex = (int)$data->intvalue;
        if ($selectedindex <= 0) {
            return null;
        }

        $config = json_decode($field->configdata, true);
        if (empty($config['options'])) {
            return null;
        }

        $options = explode("\n", $config['options']);
        $optionindex = $selectedindex - 1;

        if (!isset($options[$optionindex])) {
            return null;
        }

        $value = trim($options[$optionindex]);
        return $value !== '' ? $value : null;
    }

    /**
     * Get custom field definition with static cache.
     *
     * @param string $shortname Field shortname.
     * @param string $area Area name.
     * @return object|false
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
     * Get competency ids with at least one visible linked course.
     *
     * @param array<int> $competencyids Competency ids.
     * @return array<int, mixed>
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
     * Select 5 competencies centered on the last completed one.
     *
     * @param array<int, array<string, mixed>> $competencies Competency data.
     * @param int $lastcompletedindex Last completed index.
     * @return array<int, array<string, mixed>>
     */
    protected function select_trail_competencies(array $competencies, int $lastcompletedindex): array {
        $total = count($competencies);
        $maxitems = 5;

        if ($total <= $maxitems) {
            return $this->add_trail_positions($competencies);
        }

        $start = $this->get_trail_start_index($total, $lastcompletedindex, $maxitems);
        $selected = array_slice($competencies, $start, $maxitems);

        return $this->add_trail_positions($selected);
    }

    /**
     * Get trail start index.
     *
     * @param int $total Total items.
     * @param int $lastcompletedindex Last completed index.
     * @param int $maxitems Max items.
     * @return int
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

        if ($start + $maxitems > $total) {
            $start = $total - $maxitems;
        }

        return $start;
    }

    /**
     * Add position markers to trail data.
     *
     * @param array<int, array<string, mixed>> $competencies Competencies.
     * @return array<int, array<string, mixed>>
     */
    protected function add_trail_positions(array $competencies): array {
        $count = count($competencies);
        foreach ($competencies as $i => &$comp) {
            $comp['isfirst'] = ($i === 0);
            $comp['islast'] = ($i === $count - 1);
        }
        unset($comp);

        return $competencies;
    }
}
