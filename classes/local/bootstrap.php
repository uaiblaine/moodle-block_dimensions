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
 * Bootstrap major version marker for the block's own root element.
 *
 * @package    block_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_dimensions\local;

/**
 * Tells the stylesheet which Bootstrap major version the current site runs.
 *
 * Moodle 4.5 ships Bootstrap 4 and 5.0+ ship Bootstrap 5, and the bridging between them is
 * asymmetric: 4.5's forward bridge (theme/boost/scss/moodle/bs5-bridge.scss) is 116 lines
 * covering only g-0, btn-close, the ms/me/ps/pe spacers and float/text/border/rounded-start/end,
 * while 5.x's backward bridge (bs4-compat.scss) is over a thousand lines. A BS5 utility outside
 * that short list resolves to nothing on 4.5, so styles.css carries a polyfill for the families
 * the plugin uses.
 *
 * That polyfill must not reach 5.x. Plugin CSS loads after core's, so a rule scoped to a plugin
 * surface would outrank core's own definition and freeze 4.5's metrics onto the newer branch.
 * Gating the block on a marker only 4.5 receives is what keeps it inert there.
 *
 * ONE FORCED DIVERGENCE FROM THE SIBLING, AND IT IS THE PLUGIN TYPE THAT FORCES IT.
 * local_dimensions gates its polyfill on a BODY class added by a mark_page() helper its three
 * page entry points call. A block cannot do that: theme/boost/layout/columns2.php calls
 * $OUTPUT->body_attributes() on line 33 and $OUTPUT->blocks('side-pre') on line 34, and it is
 * the latter that invokes block_base::get_content() - so the body tag's attributes are already
 * computed by the time this plugin is asked for any content at all. The marker therefore rides
 * the block's own root element, emitted by the summary renderable. The contract is identical (a
 * class present only when $CFG->branch < 500, gating a bannered polyfill block, pinned by a test
 * of the same shape); only the attachment point differs. It is safe here specifically because
 * block_dimensions renders nothing outside its own subtree - no core/modal, no document.body
 * append - so there is no node the body class would have reached and the root class does not.
 *
 * This class therefore has no mark_page(). Do not add one: there is no point in the block's
 * lifecycle at which it could still work, and a helper that silently does nothing is worse than
 * an absent one.
 */
class bootstrap {
    /** @var string Class added to the block's own root on sites running Bootstrap 4 (Moodle 4.5). */
    public const ROOT_CLASS_BS4 = 'block-dimensions-bs4';

    /** @var int First Moodle branch that ships Bootstrap 5. */
    private const FIRST_BS5_BRANCH = 500;

    /**
     * Whether the current site runs Bootstrap 4 rather than Bootstrap 5.
     *
     * @return bool True on Moodle 4.5 and older, false from Moodle 5.0 on.
     */
    public static function is_bs4(): bool {
        global $CFG;

        return (int) $CFG->branch < self::FIRST_BS5_BRANCH;
    }
}
