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
 * The plan cards of a user, for a plugin that draws them outside the block.
 *
 * @package    block_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_dimensions;

use block_dimensions\local\dataset_provider;
use core\exception\coding_exception;
use core_competency\api;

/**
 * The plugin's API for other plugins: the plan cards of one user, the layout and the list item classes.
 *
 * The three methods and the four constants are a contract other plugins compile against. A card is
 * drawn by rendering {@see self::TEMPLATE} from PHP with one element of {@see self::for_user()} as its
 * context, inside an element that carries {@see self::HOST_CLASS}.
 *
 * @package    block_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class plan_cards {
    /** @var string Cards drawn as a column of image above text. */
    public const string LAYOUT_VERTICAL = 'vertical';

    /** @var string Cards drawn as a row of image beside text. */
    public const string LAYOUT_HORIZONTAL = 'horizontal';

    /** @var string The class an ancestor of the cards carries so the block's stylesheet draws them. */
    public const string HOST_CLASS = 'block_dimensions';

    /** @var string The card's template; its context is one element of for_user()'s answer. */
    public const string TEMPLATE = 'block_dimensions/plan_card';

    /**
     * The plan cards of a user, one per plan core lets the current viewer read.
     *
     * Access is core's: {@see \core_competency\api::list_user_plans()} runs against the current user, so
     * drafts appear only to a viewer core lets read them, and a viewer core refuses gets an empty
     * answer. No card offers the favourite star.
     *
     * @param int $userid The user whose plans to list.
     * @param string $layout '' for the site's setting, or one of the LAYOUT_* constants for this call.
     * @return array The cards keyed by plan id, in the order core lists the plans.
     * @throws coding_exception When $layout is none of the above.
     */
    public static function for_user(int $userid, string $layout = ''): array {
        $layout = self::resolve_layout($layout);
        if (!api::is_enabled()) {
            return [];
        }

        return (new dataset_provider($userid))->get_plan_cards($layout);
    }

    /**
     * The layout the site setting asks for.
     *
     * @return string LAYOUT_VERTICAL or LAYOUT_HORIZONTAL.
     */
    public static function site_layout(): string {
        return get_config('block_dimensions', 'plancard_layout') === self::LAYOUT_HORIZONTAL
            ? self::LAYOUT_HORIZONTAL
            : self::LAYOUT_VERTICAL;
    }

    /**
     * The class list of a card's list item, as the block's own script writes it.
     *
     * @param string $layout '' for the site's setting, or one of the LAYOUT_* constants.
     * @return string
     * @throws coding_exception When $layout is none of the above.
     */
    public static function item_classes(string $layout): string {
        $common = 'dims-plan-item mb-3 dims-card-item ';
        if (self::resolve_layout($layout) === self::LAYOUT_HORIZONTAL) {
            return $common . 'dims-plan-item-horizontal col-12 col-lg-6 col-xl-4';
        }

        return $common . 'dims-plan-item-vertical col-12 col-sm-6 col-lg-4';
    }

    /**
     * Turn the argument of a public method into a layout.
     *
     * @param string $layout '' or one of the LAYOUT_* constants.
     * @return string LAYOUT_VERTICAL or LAYOUT_HORIZONTAL.
     * @throws coding_exception When $layout is none of the above.
     */
    private static function resolve_layout(string $layout): string {
        if ($layout === '') {
            return self::site_layout();
        }
        if ($layout !== self::LAYOUT_VERTICAL && $layout !== self::LAYOUT_HORIZONTAL) {
            throw new coding_exception('Unknown plan card layout: ' . $layout);
        }

        return $layout;
    }
}
