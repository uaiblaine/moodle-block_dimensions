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
use local_dimensions\template_metadata_cache;
use local_dimensions\competency_metadata_cache;
use local_dimensions\plan_trail_cache;
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

    /** @var array Static cache of formatted custom field names (null when undefined), keyed "name_<shortname>_<area>". */
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

    /** @var string The plans the learner is working on. */
    public const BUCKET_ACTIVE = 'active';

    /** @var string Plans waiting for a review and plans a reviewer has already opened. */
    public const BUCKET_REVIEW = 'review';

    /** @var string Plans that have been completed. */
    public const BUCKET_COMPLETE = 'complete';

    /**
     * @var array The statuses each status bucket carries, keyed by bucket name.
     *
     * A plain draft belongs to no bucket on purpose: nothing would render it, so a learner whose
     * only plan is a draft would open a block with nothing in it. The two review statuses share one
     * bucket; core counts both as draft statuses ({@see \core_competency\plan::get_draft_statuses()}).
     */
    protected const BUCKET_STATUSES = [
        self::BUCKET_ACTIVE => [plan::STATUS_ACTIVE],
        self::BUCKET_REVIEW => [plan::STATUS_WAITING_FOR_REVIEW, plan::STATUS_IN_REVIEW],
        self::BUCKET_COMPLETE => [plan::STATUS_COMPLETE],
    ];

    /**
     * The bucket a plan status belongs to, or null when the block has nowhere to show it.
     *
     * @param int $status A plan status constant.
     * @return string|null
     */
    protected static function bucket_of(int $status): ?string {
        foreach (self::BUCKET_STATUSES as $bucket => $statuses) {
            if (in_array($status, $statuses, true)) {
                return $bucket;
            }
        }

        return null;
    }

    /**
     * Whether the given string names a status bucket.
     *
     * @param string $bucket Bucket name.
     * @return bool
     */
    public static function is_bucket(string $bucket): bool {
        return isset(self::BUCKET_STATUSES[$bucket]);
    }

    /**
     * The bucket the block opens on: the first one, in bucket order, that holds a plan.
     *
     * A learner whose plans have all been completed lands on them rather than on an empty Active
     * bucket. With no displayable plan the answer is the active bucket, whose empty state is the
     * right one, although {@see \block_dimensions\output\summary::has_content()} renders no shell
     * for such a learner in the first place.
     *
     * @return string One of the BUCKET_* constants.
     */
    public function opening_bucket(): string {
        $counts = $this->count_plans_by_bucket();
        foreach (array_keys(self::BUCKET_STATUSES) as $bucket) {
            if ($counts[$bucket] > 0) {
                return $bucket;
            }
        }

        return self::BUCKET_ACTIVE;
    }

    /**
     * Whether the user holds a plan this block can show, in any of its status buckets.
     *
     * @return bool
     */
    public function has_displayable_plans(): bool {
        foreach ($this->plans as $plan) {
            if (self::bucket_of((int) $plan->get('status')) !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * How many plans the learner holds in each status bucket.
     *
     * Works from the plan list the constructor already read, plus the cached template metadata of
     * active plans, so the first response can carry every bucket's count while building only one
     * bucket's cards.
     *
     * @return array{active: int, review: int, complete: int}
     */
    public function count_plans_by_bucket(): array {
        $counts = [
            self::BUCKET_ACTIVE => 0,
            self::BUCKET_REVIEW => 0,
            self::BUCKET_COMPLETE => 0,
        ];

        foreach ($this->plans as $plan) {
            $bucket = self::bucket_of((int) $plan->get('status'));
            if ($bucket === null) {
                continue;
            }

            /* In the active bucket a competencies-mode template becomes competency cards in the
               section below, never a plan card, so counting it here would promise the grid a card
               it does not show - and the count would disagree with the "Show all" pill beside it.
               Every other bucket renders every plan as a plan card. */
            if ($bucket === self::BUCKET_ACTIVE) {
                [, $displaymode] = $this->resolve_plan_display_context($plan->get('templateid'));
                if ($displaymode != constants::DISPLAYMODE_PLAN) {
                    continue;
                }
            }

            $counts[$bucket]++;
        }

        return $counts;
    }

    /**
     * Build the dataset for frontend rendering.
     *
     * When $favouritesonly is true, only cards that are in the user's favourites
     * are fully built, but totals are always counted so the frontend can display
     * accurate pill counts and decide when to load the full dataset.
     *
     * When $loadgroup is set to 'plan' or 'competency', only cards for that
     * group are built. This enables per-group Phase 2 loading so the frontend
     * can fetch the missing group without re-processing the group that was
     * already loaded with favourites in Phase 1.
     *
     * $planstatus names the status bucket to build cards for. Only the active bucket is loaded
     * with the page; the others are built when the learner asks for them, which is what keeps a
     * finished plan's images, metadata and trail off the first render. Outside the active bucket
     * every plan renders as a plan card whatever its template's display mode, and no competency
     * cards are built: the competency section is about work in progress.
     *
     * @param bool $favouritesonly If true, only return favourited cards.
     * @param string $loadgroup Limit card building: 'plan', 'competency', or '' for both.
     * @param string $planstatus Status bucket to build, or '' to open on the first one with plans.
     * @return array<string, mixed>
     */
    public function get_dataset(
        bool $favouritesonly = false,
        string $loadgroup = '',
        string $planstatus = ''
    ): array {
        global $USER;

        if (!self::is_bucket($planstatus)) {
            $planstatus = $this->opening_bucket();
        }
        $isactivebucket = $planstatus === self::BUCKET_ACTIVE;
        $bucketplans = $this->get_plans_in_bucket($planstatus);
        $plancounts = $this->count_plans_by_bucket();

        // Pre-load favourite IDs if the feature is enabled.
        $favouritesenabled = self::is_favourites_enabled();
        $planfavids = [];
        $compfavids = [];
        if ($favouritesenabled) {
            [$planfavids, $compfavids] = $this->preload_favourite_ids((int) $USER->id);
        }

        $competencycards = [];
        $plancards = [];
        $seencompetencies = [];
        $totalplans = 0;
        $totalcompetencies = 0;

        foreach ($bucketplans as $plan) {
            $planid = $plan->get('id');
            $templateid = $plan->get('templateid');

            [$templatemetadata, $displaymode] = $this->resolve_plan_display_context($templateid);

            if (!$isactivebucket || $displaymode == constants::DISPLAYMODE_PLAN) {
                $totalplans++;

                // Skip building plan cards when only loading competencies.
                if ($loadgroup === 'competency') {
                    continue;
                }

                $card = $this->build_plan_dataset_card(
                    $plan,
                    $templateid,
                    $planid,
                    $templatemetadata,
                    $favouritesonly && $isactivebucket,
                    $isactivebucket ? $planfavids : []
                );
                if (!empty($card)) {
                    $plancards[] = $card;
                }
                continue;
            }

            // Skip processing competencies when only loading plans.
            if ($loadgroup === 'plan') {
                continue;
            }

            $result = $this->process_plan_competencies(
                $planid,
                $plan,
                $favouritesonly,
                $compfavids,
                $seencompetencies
            );

            $totalcompetencies += $result['counted'];
            foreach ($result['cards'] as $card) {
                $competencycards[] = $card;
            }
        }

        return [
            'planstatus' => $planstatus,
            'plancounts' => $plancounts,
            'hasactiveplans' => $plancounts[self::BUCKET_ACTIVE] > 0,
            'hasplancards' => !empty($plancards),
            'hascompetencies' => !empty($competencycards),
            'plancards' => $plancards,
            'competencycards' => $competencycards,
            'totalplans' => $totalplans,
            'totalcompetencies' => $totalcompetencies,
            'hasnonfavouriteplans' => ($favouritesonly && count($plancards) < $totalplans),
            'hasnonfavouritecompetencies' => ($favouritesonly && count($competencycards) < $totalcompetencies),
        ];
    }

    /**
     * Get the plans of one status bucket from the current plan list.
     *
     * @param string $bucket One of the BUCKET_* constants.
     * @return array<int, \core_competency\plan>
     */
    protected function get_plans_in_bucket(string $bucket): array {
        $plans = [];
        foreach ($this->plans as $plan) {
            if (self::bucket_of((int) $plan->get('status')) === $bucket) {
                $plans[] = $plan;
            }
        }

        return $plans;
    }

    /**
     * Pre-load user favourite IDs for plans and competencies.
     *
     * @param int $userid User id.
     * @return array{0: array<int, bool>, 1: array<int, bool>}
     */
    protected function preload_favourite_ids(int $userid): array {
        $planfavids = [];
        $compfavids = [];

        $usercontext = \context_user::instance($userid);
        $ufservice = \core_favourites\service_factory::get_service_for_user_context($usercontext);

        $planfavs = $ufservice->find_favourites_by_type('block_dimensions', 'plan');
        foreach ($planfavs as $fav) {
            $planfavids[$fav->itemid] = true;
        }

        $compfavs = $ufservice->find_favourites_by_type('block_dimensions', 'competency');
        foreach ($compfavs as $fav) {
            $compfavids[$fav->itemid] = true;
        }

        return [$planfavids, $compfavids];
    }

    /**
     * Resolve template metadata and resulting display mode for a plan.
     *
     * A plan without a template is always a plan card; a template whose metadata sets no display
     * mode defaults to competencies mode.
     *
     * @param int|null $templateid Template id.
     * @return array{0: array<string, mixed>, 1: mixed}
     */
    protected function resolve_plan_display_context(?int $templateid): array {
        if (!$templateid) {
            return [[], constants::DISPLAYMODE_PLAN];
        }

        $templatemetadata = template_metadata_cache::get_template_metadata($templateid);
        $displaymode = $templatemetadata['displaymode'] ?? constants::DISPLAYMODE_COMPETENCIES;

        return [$templatemetadata, $displaymode];
    }

    /**
     * Build plan card payload for dataset considering favourites-only mode.
     *
     * @param \core_competency\plan $plan Plan object.
     * @param int|null $templateid Template id.
     * @param int $planid Plan id.
     * @param array $templatemetadata Template metadata.
     * @param bool $favouritesonly Whether favourites-only mode is active.
     * @param array $planfavids Plan favourites map.
     * @return array|null Null when favourites-only mode skips a plan that is not a favourite.
     */
    protected function build_plan_dataset_card(
        \core_competency\plan $plan,
        ?int $templateid,
        int $planid,
        array $templatemetadata,
        bool $favouritesonly,
        array $planfavids
    ): ?array {
        // In favourites-only mode, skip non-favourite plans.
        if ($favouritesonly && !isset($planfavids[$planid])) {
            return null;
        }

        $card = $this->build_plan_card($plan, $templateid, $planid, $templatemetadata);
        $isfavourite = isset($planfavids[$planid]);
        $card['isfavourite'] = $isfavourite;
        $card['favouritearialabel'] = $isfavourite
            ? get_string('removefromfavourites', 'block_dimensions')
            : get_string('addtofavourites', 'block_dimensions');
        $card['favouritetitle'] = $card['favouritearialabel'];

        return $card;
    }

    /**
     * Fetch bulk competency metadata from cache.
     *
     * Extracted to allow overriding in tests without requiring local_dimensions.
     *
     * @param array $competencyids Competency ids.
     * @return array
     */
    protected function fetch_bulk_competency_metadata(array $competencyids): array {
        return competency_metadata_cache::get_many($competencyids);
    }

    /**
     * Fetch competencies for a plan from the Moodle API.
     *
     * Extracted to allow overriding in tests without a real database.
     *
     * @param \core_competency\plan $plan Plan object.
     * @return array Plan competencies payload.
     * @throws \Exception If the API call fails.
     */
    protected function fetch_plan_competencies_api(\core_competency\plan $plan): array {
        return api::list_plan_competencies($plan);
    }

    /**
     * Process all competencies for a given plan and return card/count results.
     *
     * @param int $planid Plan id.
     * @param \core_competency\plan $plan Plan object.
     * @param bool $favouritesonly Whether favourites-only mode is active.
     * @param array $compfavids Favourite competency ids map.
     * @param array $seencompetencies Seen competency ids map (updated by reference).
     * @return array ['counted' => int, 'cards' => array]; 0 and [] when the plan's competencies cannot be read.
     */
    protected function process_plan_competencies(
        int $planid,
        \core_competency\plan $plan,
        bool $favouritesonly,
        array $compfavids,
        array &$seencompetencies
    ): array {
        try {
            $competencies = $this->fetch_plan_competencies_api($plan);
        } catch (\Exception $e) {
            return ['counted' => 0, 'cards' => []];
        }

        $allcompids = array_map(fn($c) => $c->competency->get('id'), $competencies);
        $competencieswithcourses = $this->get_competencies_with_courses($allcompids);

        $eligibleids = $this->get_eligible_competency_ids($competencies, $seencompetencies, $competencieswithcourses);
        $idstoprocess = $this->get_ids_to_process($eligibleids, $favouritesonly, $compfavids);
        $bulkmetadata = !empty($idstoprocess) ? $this->fetch_bulk_competency_metadata($idstoprocess) : [];

        $counted = 0;
        $cards = [];

        foreach ($competencies as $compdata) {
            $processed = $this->process_competency_dataset_item(
                $planid,
                $compdata->competency,
                $favouritesonly,
                $compfavids,
                $competencieswithcourses,
                $bulkmetadata,
                $seencompetencies
            );

            if ($processed['counted']) {
                $counted++;
            }

            if (!empty($processed['card'])) {
                $cards[] = $processed['card'];
            }
        }

        return ['counted' => $counted, 'cards' => $cards];
    }

    /**
     * Get competency IDs eligible for card processing.
     *
     * @param array $competencies Plan competencies payload.
     * @param array $seencompetencies Competency ids already seen.
     * @param array $competencieswithcourses Competencies linked to visible courses.
     * @return array
     */
    protected function get_eligible_competency_ids(
        array $competencies,
        array $seencompetencies,
        array $competencieswithcourses
    ): array {
        $eligibleids = [];
        foreach ($competencies as $compdata) {
            $cid = (int) $compdata->competency->get('id');
            if (!isset($seencompetencies[$cid]) && isset($competencieswithcourses[$cid])) {
                $eligibleids[] = $cid;
            }
        }

        return $eligibleids;
    }

    /**
     * Get competency IDs that must be fully processed.
     *
     * @param array $eligibleids Eligible competency ids.
     * @param bool $favouritesonly Whether favourites-only mode is active.
     * @param array $compfavids Favourite competency ids map.
     * @return array
     */
    protected function get_ids_to_process(array $eligibleids, bool $favouritesonly, array $compfavids): array {
        if (!$favouritesonly) {
            return $eligibleids;
        }

        return array_values(array_filter($eligibleids, fn($id) => isset($compfavids[$id])));
    }

    /**
     * Decide whether a competency is skipped: already listed by an earlier plan, or linked to no
     * visible course (which also marks it as seen).
     *
     * @param int $competencyid Competency id.
     * @param array $seencompetencies Seen competency ids (updated by reference).
     * @param array $competencieswithcourses Competency ids linked to visible courses.
     * @return bool
     */
    protected function skip_competency_for_visibility(
        int $competencyid,
        array &$seencompetencies,
        array $competencieswithcourses
    ): bool {
        if (isset($seencompetencies[$competencyid])) {
            return true;
        }

        if (!isset($competencieswithcourses[$competencyid])) {
            $seencompetencies[$competencyid] = true;
            return true;
        }

        return false;
    }

    /**
     * Decide whether a competency should be skipped for favourites-only view.
     *
     * @param bool $favouritesonly Whether favourites-only mode is active.
     * @param int $competencyid Competency id.
     * @param array $compfavids Favourite competency ids map.
     * @return bool
     */
    protected function skip_competency_for_favourites(bool $favouritesonly, int $competencyid, array $compfavids): bool {
        return ($favouritesonly && !isset($compfavids[$competencyid]));
    }

    /**
     * Process one competency item for dataset output.
     *
     * @param int $planid Plan id.
     * @param object $competency Competency object.
     * @param bool $favouritesonly Whether favourites-only mode is active.
     * @param array $compfavids Favourite competency ids map.
     * @param array $competencieswithcourses Competency ids linked to visible courses.
     * @param array $bulkmetadata Cached metadata by competency id.
     * @param array $seencompetencies Seen competency ids map (updated by reference).
     * @return array
     */
    protected function process_competency_dataset_item(
        int $planid,
        object $competency,
        bool $favouritesonly,
        array $compfavids,
        array $competencieswithcourses,
        array $bulkmetadata,
        array &$seencompetencies
    ): array {
        $competencyid = (int) $competency->get('id');

        if ($this->skip_competency_for_visibility($competencyid, $seencompetencies, $competencieswithcourses)) {
            return ['counted' => false, 'card' => null];
        }

        $seencompetencies[$competencyid] = true;

        if ($this->skip_competency_for_favourites($favouritesonly, $competencyid, $compfavids)) {
            return ['counted' => true, 'card' => null];
        }

        $metadata = $bulkmetadata[$competencyid] ?? null;
        $card = $this->build_competency_card($planid, $competencyid, $competency, $metadata);
        $isfavourite = isset($compfavids[$competencyid]);
        $card['isfavourite'] = $isfavourite;
        $card['favouritearialabel'] = $isfavourite
            ? get_string('removefromfavourites', 'block_dimensions')
            : get_string('addtofavourites', 'block_dimensions');
        $card['favouritetitle'] = $card['favouritearialabel'];

        return ['counted' => true, 'card' => $card];
    }

    /**
     * Check whether the favourites feature is enabled.
     *
     * Use this rather than reading the setting, so the card stars and toggle_favourite agree on a
     * site where the setting was never saved.
     *
     * @return bool
     */
    public static function is_favourites_enabled(): bool {
        $val = get_config('block_dimensions', 'enable_favourites');
        // Default to true when the config has never been set.
        return ($val === false) ? true : (bool) $val;
    }

    /**
     * Read UI config flags from plugin settings.
     *
     * @return array<string, mixed>
     */
    public static function get_ui_config(): array {
        $showheadingraw = get_config('block_dimensions', 'show_heading');
        // Default to true when the config has never been set.
        $showheading = ($showheadingraw === false) ? true : (bool) $showheadingraw;

        return [
            'showheading' => $showheading,
            'showsearch' => (bool) get_config('block_dimensions', 'enable_search'),
            'showsectionheaders' => (bool) get_config('block_dimensions', 'enable_section_headers'),
            'trailclickable' => (bool) get_config('block_dimensions', 'enable_trail_links'),
            'favouritesenabled' => self::is_favourites_enabled(),
            'plancardlayout' => get_config('block_dimensions', 'plancard_layout') ?: 'vertical',
            'filtersettings' => [
                'plan' => [
                    'tag1enabled' => (bool) get_config('block_dimensions', 'enable_plan_tag1_filter'),
                    'tag2enabled' => (bool) get_config('block_dimensions', 'enable_plan_tag2_filter'),
                    'tag1displaymode' => get_config('block_dimensions', 'plan_tag1_displaymode') ?: 'tabs',
                    'tag2displaymode' => get_config('block_dimensions', 'plan_tag2_displaymode') ?: 'tabs',
                    'tag1label' => self::get_customfield_name(constants::CFIELD_TAG1, 'lp'),
                    'tag2label' => self::get_customfield_name(constants::CFIELD_TAG2, 'lp'),
                ],
                'competency' => [
                    'tag1enabled' => (bool) get_config('block_dimensions', 'enable_competency_tag1_filter'),
                    'tag2enabled' => (bool) get_config('block_dimensions', 'enable_competency_tag2_filter'),
                    'tag1displaymode' => get_config('block_dimensions', 'competency_tag1_displaymode') ?: 'tabs',
                    'tag2displaymode' => get_config('block_dimensions', 'competency_tag2_displaymode') ?: 'tabs',
                    'tag1label' => self::get_customfield_name(constants::CFIELD_TAG1, 'competency'),
                    'tag2label' => self::get_customfield_name(constants::CFIELD_TAG2, 'competency'),
                ],
            ],
        ];
    }

    /**
     * Resolve the user-facing display name of a custom field by shortname/area.
     *
     * Used to give filter radiogroups and dropdowns a meaningful aria-label
     * (e.g. "Year" / "Category") instead of the internal slug "tag1" / "tag2"
     * (WCAG 2.4.6 Headings and Labels). Returns null when the field is not
     * defined (admin hasn't configured the custom field) so the caller can
     * fall back to a generic localized label.
     *
     * @param string $shortname Custom field shortname.
     * @param string $area Custom field area ('lp' for plan templates, 'competency' for competencies).
     * @return string|null format_string'd display name, or null if not found.
     */
    public static function get_customfield_name(string $shortname, string $area): ?string {
        global $DB;
        $key = "name_{$shortname}_{$area}";
        if (!array_key_exists($key, self::$fieldcache)) {
            $sql = "SELECT f.name
                      FROM {customfield_field} f
                      JOIN {customfield_category} c ON c.id = f.categoryid
                     WHERE f.shortname = :shortname
                       AND c.component = :component
                       AND c.area = :area";
            $name = $DB->get_field_sql($sql, [
                'shortname' => $shortname,
                'component' => 'local_dimensions',
                'area' => $area,
            ]);
            self::$fieldcache[$key] = ($name !== false && $name !== null && $name !== '')
                ? format_string($name, true, ['context' => \context_system::instance()])
                : null;
        }
        return self::$fieldcache[$key];
    }

    /**
     * Validate a colour value destined for an inline style attribute.
     *
     * Defence in depth: metadata caches in local_dimensions already restrict
     * these values to hex colours, but this plugin renders them into a CSS
     * context, so it must not rely on a sibling plugin's validation alone.
     *
     * @param string|null $color Raw colour value from cached metadata.
     * @return string|null The colour when it is a safe hex token, null otherwise.
     */
    protected function sanitize_color(?string $color): ?string {
        if (!is_string($color)) {
            return null;
        }
        $color = trim($color);

        return preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6}|[0-9a-f]{8})$/i', $color) ? $color : null;
    }

    /**
     * Validate an image URL destined for an inline background-image style.
     *
     * @param string|null $url Raw URL from cached metadata.
     * @return string|null The URL when it passes PARAM_URL cleaning, null otherwise.
     */
    protected function sanitize_image_url(?string $url): ?string {
        if ($url === null || $url === '') {
            return null;
        }
        $clean = clean_param($url, PARAM_URL);

        return $clean !== '' ? $clean : null;
    }

    /**
     * Build a single plan card payload.
     *
     * @param \core_competency\plan $plan Plan object.
     * @param int|null $templateid Template id.
     * @param int $planid Plan id.
     * @param array $templatemetadata Pre-fetched template metadata from cache.
     * @return array<string, mixed>
     */
    protected function build_plan_card(
        \core_competency\plan $plan,
        ?int $templateid,
        int $planid,
        array $templatemetadata = []
    ): array {
        $viewurl = new moodle_url('/local/dimensions/view-plan.php', ['id' => $planid]);

        $competencytypesuffix = get_string('competency_count_suffix', 'block_dimensions');

        if ($templateid) {
            $customtype = $templatemetadata['type'] ?? null;
            if (!empty($customtype)) {
                $competencytypesuffix = format_string(trim($customtype), true, ['context' => \context_system::instance()]);
            }
        }

        $imageurl = $templateid ? ($templatemetadata['templatecardimageurl'] ?? null) : null;
        $bgcolor = $this->sanitize_color($templateid ? ($templatemetadata['bgcolor'] ?? null) : null);
        $textcolor = $this->sanitize_color($templateid ? ($templatemetadata['textcolor'] ?? null) : null);
        $tags = [
            'tag1' => $templateid ? ($templatemetadata['tag1'] ?? null) : null,
            'tag2' => $templateid ? ($templatemetadata['tag2'] ?? null) : null,
        ];
        $normtags = [];
        foreach ($tags as $value) {
            if (!empty($value)) {
                $normtags[] = ['value' => $value];
            }
        }

        $status = (int) $plan->get('status');
        $bucket = self::bucket_of($status);
        $iscomplete = $status === plan::STATUS_COMPLETE;
        $trailpayload = $this->build_plan_trail_payload($planid, $templateid, $imageurl, $iscomplete);
        $imageurl = $this->sanitize_image_url($trailpayload['imageurl']);
        $totalcompetencies = $trailpayload['totalcompetencies'];
        $hasitemsbeforetrail = $trailpayload['hasitemsbeforetrail'];
        $hasitemsaftertrail = $trailpayload['hasitemsaftertrail'];
        $competencytrail = $trailpayload['competencytrail'];

        $haspartialtrail = $this->has_partial_trail($competencytrail);

        $planname = format_string($plan->get('name'), true, ['context' => \context_system::instance()]);
        $buttondata = $this->get_plan_button_data($planname, $haspartialtrail, $bucket);
        $statuslabel = $this->get_plan_status_label($plan, $status);
        $buttonlabel = $buttondata['buttonlabel'];
        $buttonarialabel = $buttondata['buttonarialabel'];

        $layoutmode = get_config('block_dimensions', 'plancard_layout') ?: 'vertical';

        return [
            'id' => $planid,
            'name' => $planname,
            'statuslabel' => $statuslabel,
            'hasstatuslabel' => $statuslabel !== '',
            'iscompleteplan' => $iscomplete,
            'isreviewplan' => $bucket === self::BUCKET_REVIEW,
            'showfavourite' => $bucket === self::BUCKET_ACTIVE,
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
            'tags' => $normtags,
            'hastags' => !empty($normtags),
            'showcardtitle' => true,
            'buttonlabel' => $buttonlabel,
            'buttonarialabel' => $buttonarialabel,
            'ishorizontal' => ($layoutmode === 'horizontal'),
            'isvertical' => ($layoutmode !== 'horizontal'),
        ];
    }

    /**
     * The chip a card shows for a plan that is not simply active.
     *
     * A completed plan carries the date it closed. Core refuses to edit a completed plan
     * (api::update_plan throws 'Completed plan cannot be edited'), so its timemodified is the
     * moment it was completed - reopening and completing it again moves the date to the later
     * completion, which is still the truth the chip states.
     *
     * @param \core_competency\plan $plan Plan object.
     * @param int $status The plan's status.
     * @return string Empty for an active plan, which needs no chip.
     */
    protected function get_plan_status_label(\core_competency\plan $plan, int $status): string {
        switch ($status) {
            case plan::STATUS_COMPLETE:
                $completedon = userdate((int) $plan->get('timemodified'), get_string('strftimedatefullshort'));
                return get_string('planstatuscompleted', 'block_dimensions', $completedon);
            case plan::STATUS_WAITING_FOR_REVIEW:
                return get_string('planstatuswaitingreview', 'block_dimensions');
            case plan::STATUS_IN_REVIEW:
                return get_string('planstatusinreview', 'block_dimensions');
            default:
                return '';
        }
    }

    /**
     * Build trail-related payload for a plan card.
     *
     * A completed plan's trail shows the ratings core froze at completion, not the learner's
     * current ones; {@see \local_dimensions\plan_trail_cache::get_trail_data()} makes that switch
     * when $iscomplete is true.
     *
     * @param int $planid Plan id.
     * @param int|null $templateid Template id.
     * @param string|null $imageurl Current image url.
     * @param bool $iscomplete Whether the plan's status is complete.
     * @return array
     */
    protected function build_plan_trail_payload(
        int $planid,
        ?int $templateid,
        ?string $imageurl,
        bool $iscomplete = false
    ): array {
        $payload = [
            'imageurl' => $imageurl,
            'totalcompetencies' => 0,
            'hasitemsbeforetrail' => false,
            'hasitemsaftertrail' => false,
            'competencytrail' => [],
        ];

        try {
            $traildata = plan_trail_cache::get_trail_data($planid, $this->userid, $templateid, $iscomplete);
            $payload['totalcompetencies'] = $traildata['total'];

            if ($payload['totalcompetencies'] <= 0) {
                return $payload;
            }

            // Image fallback: use first competency's cached image if template has none.
            if (!$payload['imageurl']) {
                $firstcompid = (int)$traildata['competencies'][0]['id'];
                $firstmeta = competency_metadata_cache::get_competency_metadata($firstcompid);
                $payload['imageurl'] = $firstmeta['cardimageurl'] ?? null;
            }

            [$competencydata, $lastcompletedindex] = $this->build_trail_competency_data(
                $planid,
                $traildata['competencies']
            );

            $trailstartindex = $this->get_trail_start_index(count($competencydata), $lastcompletedindex);
            $trail = $this->select_trail_competencies($competencydata, $lastcompletedindex);

            $payload['competencytrail'] = $trail;
            $payload['hasitemsbeforetrail'] = ($trailstartindex > 0);
            $payload['hasitemsaftertrail'] = (($trailstartindex + count($trail)) < count($competencydata));
        } catch (\Exception $e) {
            debugging('Error processing competencies for trail: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }

        return $payload;
    }

    /**
     * Build normalized trail competency data and last completed index.
     *
     * @param int $planid Plan id.
     * @param array $trailcompetencies Raw trail competency rows.
     * @return array
     */
    protected function build_trail_competency_data(int $planid, array $trailcompetencies): array {
        $competencydata = [];
        $lastcompletedindex = -1;
        $index = 0;

        foreach ($trailcompetencies as $row) {
            $competencyid = (int)$row['id'];
            $isproficient = !empty($row['proficiency']);

            if ($isproficient) {
                $lastcompletedindex = $index;
            }

            $competencydata[] = [
                'id' => $competencyid,
                'planid' => $planid,
                'shortname' => format_string($row['shortname'], true, ['context' => \context_system::instance()]),
                'iscompleted' => $isproficient,
                'index' => $index,
                'url' => (new moodle_url('/local/dimensions/view-competency.php', [
                    'id' => $planid,
                    'competencyid' => $competencyid,
                ]))->out(false),
            ];
            $index++;
        }

        return [$competencydata, $lastcompletedindex];
    }

    /**
     * Whether a trail has both completed and pending items.
     *
     * @param array $competencytrail Trail items.
     * @return bool
     */
    protected function has_partial_trail(array $competencytrail): bool {
        if (empty($competencytrail)) {
            return false;
        }

        $completedcount = 0;
        foreach ($competencytrail as $item) {
            if (!empty($item['iscompleted'])) {
                $completedcount++;
            }
        }

        $totaltrail = count($competencytrail);
        return ($completedcount > 0 && $completedcount < $totaltrail);
    }

    /**
     * Get button labels for plan card according to trail state.
     *
     * @param string $planname Formatted plan name.
     * @param bool $haspartialtrail Whether trail is partially completed.
     * @param string|null $bucket The plan's status bucket, or null for the active wording.
     * @return array
     */
    protected function get_plan_button_data(string $planname, bool $haspartialtrail, ?string $bucket = null): array {
        if ($bucket !== null && $bucket !== self::BUCKET_ACTIVE) {
            /* Outside the active bucket there is nothing to continue: the plan is finished or in
               someone else's hands, and the card opens it to be read. */
            return [
                'buttonlabel' => get_string('viewplancard', 'block_dimensions'),
                'buttonarialabel' => get_string('viewplancardaria', 'block_dimensions', $planname),
            ];
        }

        if ($haspartialtrail) {
            return [
                'buttonlabel' => get_string('continuecard', 'block_dimensions'),
                'buttonarialabel' => get_string('continuecardaria', 'block_dimensions', $planname),
            ];
        }

        return [
            'buttonlabel' => get_string('accesscard', 'block_dimensions'),
            'buttonarialabel' => get_string('accesscardaria', 'block_dimensions', $planname),
        ];
    }

    /**
     * Build a single competency card payload.
     *
     * @param int $planid Plan id.
     * @param int $competencyid Competency id.
     * @param \core_competency\competency $competency Competency object.
     * @param array|null $metadata Pre-fetched cached metadata, or null to fetch on demand.
     * @return array<string, mixed>
     */
    protected function build_competency_card(
        int $planid,
        int $competencyid,
        \core_competency\competency $competency,
        ?array $metadata = null
    ): array {
        $viewurl = new moodle_url('/local/dimensions/view-competency.php', [
            'id' => $planid,
            'competencyid' => $competencyid,
        ]);

        if ($metadata === null) {
            $metadata = competency_metadata_cache::get_competency_metadata($competencyid);
        }

        $imageurl = $this->sanitize_image_url($metadata['cardimageurl'] ?? null);
        $tag1 = $metadata['tag1'] ?? null;
        $tag2 = $metadata['tag2'] ?? null;
        $bgcolor = $this->sanitize_color($metadata['bgcolor'] ?? null);
        $textcolor = $this->sanitize_color($metadata['textcolor'] ?? null);
        $tags = [];
        foreach ([$tag1, $tag2] as $value) {
            if (!empty($value)) {
                $tags[] = ['value' => $value];
            }
        }

        $compname = format_string($competency->get('shortname'), true, ['context' => \context_system::instance()]);

        return [
            'id' => $competencyid,
            'name' => $compname,
            'url' => $viewurl->out(false),
            'imageurl' => $imageurl,
            'hasimage' => !empty($imageurl),
            'tag1' => $tag1,
            'hastag1' => !empty($tag1),
            'tag2' => $tag2,
            'hastag2' => !empty($tag2),
            'tags' => $tags,
            'hastags' => !empty($tags),
            'bgcolor' => $bgcolor,
            'hasbgcolor' => !empty($bgcolor),
            'textcolor' => $textcolor,
            'hastextcolor' => !empty($textcolor),
            'showcardtitle' => true,
            'buttonlabel' => get_string('accesscard', 'block_dimensions'),
            'buttonarialabel' => get_string('accesscardaria', 'block_dimensions', $compname),
        ];
    }

    /**
     * Get competency ids with at least one visible linked course.
     *
     * @param array $competencyids Competency ids.
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
     * @param array $competencies Competency data.
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
     * Index of the first trail item shown.
     *
     * 0 when everything fits or nothing is completed; otherwise the window of $maxitems centred on
     * the last completed item, clamped so it never runs past the end.
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
     * @param array $competencies Competencies.
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
