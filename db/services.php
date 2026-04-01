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
 * External services definition for block_dimensions.
 *
 * @package    block_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'block_dimensions_get_block_dataset' => [
        'classname' => 'block_dimensions\\external\\get_block_dataset',
        'methodname' => 'execute',
        'classpath' => 'blocks/dimensions/classes/external/get_block_dataset.php',
        'description' => 'Returns the complete non-paginated dataset for block_dimensions cards and filters.',
        'type' => 'read',
        'ajax' => true,
    ],
    'block_dimensions_toggle_favourite' => [
        'classname' => 'block_dimensions\\external\\toggle_favourite',
        'methodname' => 'execute',
        'classpath' => 'blocks/dimensions/classes/external/toggle_favourite.php',
        'description' => 'Toggles a plan or competency as a favourite for the current user.',
        'type' => 'write',
        'ajax' => true,
    ],
];
