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
 * Hook callbacks of block_dimensions.
 *
 * @package    block_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_dimensions;

use block_dimensions\output\page;
use core_user\hook\extend_default_homepage;

/**
 * The block's hook callbacks (db/hooks.php).
 *
 * The hook manager enumerates db/hooks.php off disk with no installed-plugin filter and dispatch()
 * has no try/catch, so a copy deployed before its upgrade must not throw out of the admin tree or
 * the preferences form: the callback catches \Throwable and returns.
 *
 * @package    block_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {
    /**
     * Offer the block's own page as a start page, while the page is enabled.
     *
     * The option's key is the page's local path, which core validates as a local URL on every read
     * (get_default_home_page_url()); its label is the block's name. Core dispatches this hook where
     * it builds the start page choices - the "Start page for users" setting, each user's own
     * preference form, the site registration form - and where it builds the preference registry,
     * which validates every user preference write; the callback costs two config reads.
     *
     * @param extend_default_homepage $hook The hook.
     * @return void
     */
    public static function extend_default_homepage(extend_default_homepage $hook): void {
        try {
            if (during_initial_install() || !page::is_enabled()) {
                return;
            }
            $hook->add_option(new \core\url(page::PATH), new \core\lang_string('pluginname', 'block_dimensions'));
        } catch (\Throwable $e) {
            // A throw here would take the whole settings page or preference write with it; without
            // the option the choices are simply core's.
            return;
        }
    }
}
