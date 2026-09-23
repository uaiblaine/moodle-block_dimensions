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
 * Attribute names of the dark-mode contract shared with local_dimensions.
 *
 * @package    block_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_dimensions\local;

/**
 * Attribute names of the dark-mode contract shared with local_dimensions.
 *
 * Constants only, and deliberately no is_dark()-style helper: whether the host page is dark is not
 * known on the server. Moodle 5.3 resolves it from a user preference, a cookie and a head script
 * reading matchMedia, so a PHP guess would sooner or later render the plugin dark on a light page.
 *
 * {@see \local_dimensions\local\colour_mode} declares the same values; keep the two in step.
 */
class colour_mode {
    /** @var string Bootstrap's colour-mode attribute. The host page sets it on html (core) or body (e.g. theme_moove). */
    public const HOST_ATTRIBUTE = 'data-bs-theme';

    /** @var string The value of HOST_ATTRIBUTE that activates the plugin's dark layer. */
    public const DARK = 'dark';

    /** @var string The value of HOST_ATTRIBUTE that pins light. */
    public const LIGHT = 'light';

    /** @var string Gate on the inert OS-preference block in styles.css; nothing writes it. */
    public const MEDIA_OPTIN_ATTRIBUTE = 'data-dimensions-media-optin';
}
