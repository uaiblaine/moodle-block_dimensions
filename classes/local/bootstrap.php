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
 * Moodle 4.5 ships Bootstrap 4 and 5.0+ ship Bootstrap 5. 4.5's forward bridge
 * (theme/boost/scss/moodle/bs5-bridge.scss) covers only g-0, btn-close, the ms/me/ps/pe spacers
 * and float/text/border/rounded-start/end, so any other BS5 utility resolves to nothing on 4.5
 * and styles.css carries a polyfill for the ones the plugin uses. The polyfill must stay inert on
 * 5.x: plugin CSS loads after core's, so an ungated rule would outrank core's own definition.
 *
 * The gate is a class on the block's own root element (ROOT_CLASS_BS4, written by summary.mustache
 * when the summary renderable exports isbs4), where local_dimensions uses a body class. A block
 * cannot add a body class: its content is built while the theme layout renders, and by then
 * moodle_page::add_body_class() throws, which is why this class has no mark_page(). The root class
 * is enough because the block renders nothing outside its own subtree (no core/modal, nothing
 * appended to document.body).
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
