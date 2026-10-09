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
 * The block's own page renderable.
 *
 * @package    block_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_dimensions\output;

/**
 * The block's content on its own page, /blocks/dimensions/index.php.
 *
 * The same shell as the block's: renderer::render_page() renders it through the block_dimensions/page
 * template, a wrapper carrying block_dimensions - the class core puts on the block's section and the
 * one styles.css scopes every rule to - around the summary template as a partial, without core's block
 * card chrome, which needs a block instance behind it. On the page the shell's "My competencies" h2
 * sits under the theme's h1, one rung down, as it should.
 *
 * Unlike the block, the page renders the shell whether or not the viewer holds a plan: a start page
 * cannot vanish the way an empty block does, so a learner without one sees the client's own "no
 * active plans" notice.
 *
 * @package    block_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class page extends summary {
    /** @var string The page's local path: the start page option's key and the URL index.php sets. */
    public const PATH = '/blocks/dimensions/index.php';

    /**
     * Whether the page is served: enable_page holds an explicit 1 and competencies are enabled.
     *
     * Off by default, so an upgrade changes nothing for a site that never asked for the page. The
     * competency switch is part of it because the block cannot be added while competencies are off
     * ({@see \block_dimensions::can_block_be_added()}) and its web service then returns no card: the
     * page would open empty, and the home page hook would offer a page that shows nothing.
     *
     * @return bool
     */
    public static function is_enabled(): bool {
        return (int) get_config('block_dimensions', 'enable_page') === 1
            && get_config('core_competency', 'enabled');
    }

    /**
     * The page's two gates, after require_login(): no guest, and no page while it is off.
     *
     * A guest is refused the way the block's web service refuses one. A page that is off redirects
     * to the Dashboard: a start page stored before the setting changed must land somewhere, and the
     * Dashboard is where the block already is.
     *
     * @return void
     * @throws \moodle_exception For a guest.
     */
    public static function require_access(): void {
        if (isguestuser()) {
            throw new \moodle_exception('noguest');
        }
        if (!self::is_enabled()) {
            redirect(new \moodle_url('/my/'));
        }
    }
}
