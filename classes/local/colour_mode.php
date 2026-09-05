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
 * Attribute names in the family's dark-mode activation contract.
 *
 * @package    block_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_dimensions\local;

/**
 * Attribute names in the family's dark-mode activation contract.
 *
 * Constants only. This class deliberately exposes no is_dark()-style helper: whether the host
 * page is dark is not server-knowable - core's own 5.3 mechanism resolves it from a per-user
 * preference, a cookie and a synchronous head script reading matchMedia - so any PHP guess would
 * eventually be wrong, and a wrong guess is the exact defect this design exists to prevent: the
 * plugin dark while the page is light.
 *
 * The sibling local_dimensions ships the identical class under its own namespace with identical
 * literal values, and colour_tokens_test compares the two.
 */
class colour_mode {
    /** @var string Bootstrap's own colour-mode attribute, set by the HOST on the html element. */
    public const HOST_ATTRIBUTE = 'data-bs-theme';

    /** @var string The value of HOST_ATTRIBUTE that activates the plugin's dark layer. */
    public const DARK = 'dark';

    /** @var string The value of HOST_ATTRIBUTE that pins light. */
    public const LIGHT = 'light';

    /** @var string Gate on the inert OS-preference block. Written by nothing; T6 proves it. */
    public const MEDIA_OPTIN_ATTRIBUTE = 'data-dimensions-media-optin';
}
