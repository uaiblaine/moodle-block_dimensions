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
 * Settings for block_dimensions.
 *
 * @package    block_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    // Display mode options for filters.
    $displaymodeoptions = [
        'dropdown' => get_string('displaymode_dropdown', 'block_dimensions'),
        'tabs' => get_string('displaymode_tabs', 'block_dimensions'),
    ];

    // -----------------------------------------------------------------------
    // 1. Display Settings — Visibility.
    // -----------------------------------------------------------------------
    $settings->add(new admin_setting_heading(
        'block_dimensions/displayheading',
        get_string('displayheading', 'block_dimensions'),
        get_string('displayheading_desc', 'block_dimensions')
    ));

    // Show heading "My Competencies".
    $settings->add(new admin_setting_configcheckbox(
        'block_dimensions/show_heading',
        get_string('show_heading', 'block_dimensions'),
        get_string('show_heading_desc', 'block_dimensions'),
        1
    ));

    // Hide block title ("Dimensions").
    $settings->add(new admin_setting_configcheckbox(
        'block_dimensions/hide_block_title',
        get_string('hide_block_title', 'block_dimensions'),
        get_string('hide_block_title_desc', 'block_dimensions'),
        0
    ));

    // Enable search field.
    $settings->add(new admin_setting_configcheckbox(
        'block_dimensions/enable_search',
        get_string('enable_search', 'block_dimensions'),
        get_string('enable_search_desc', 'block_dimensions'),
        0
    ));

    // Show section headers above card groups.
    $settings->add(new admin_setting_configcheckbox(
        'block_dimensions/enable_section_headers',
        get_string('enable_section_headers', 'block_dimensions'),
        get_string('enable_section_headers_desc', 'block_dimensions'),
        0
    ));

    // Enable clickable trail competencies in plan cards.
    $settings->add(new admin_setting_configcheckbox(
        'block_dimensions/enable_trail_links',
        get_string('enable_trail_links', 'block_dimensions'),
        get_string('enable_trail_links_desc', 'block_dimensions'),
        0
    ));

    // Enable favourites (star icon on cards + filter).
    $settings->add(new admin_setting_configcheckbox(
        'block_dimensions/enable_favourites',
        get_string('enable_favourites', 'block_dimensions'),
        get_string('enable_favourites_desc', 'block_dimensions'),
        1
    ));

    // -----------------------------------------------------------------------
    // 2. Competency Filters.
    // -----------------------------------------------------------------------
    $settings->add(new admin_setting_heading(
        'block_dimensions/competencyfiltersheading',
        get_string('competencyfiltersheading', 'block_dimensions'),
        get_string('competencyfiltersheading_desc', 'block_dimensions')
    ));

    // Enable competency tag1 filter.
    $settings->add(new admin_setting_configcheckbox(
        'block_dimensions/enable_competency_tag1_filter',
        get_string('enable_competency_tag1_filter', 'block_dimensions'),
        get_string('enable_competency_tag1_filter_desc', 'block_dimensions'),
        0
    ));

    // Competency tag1 display mode.
    $settings->add(new admin_setting_configselect(
        'block_dimensions/competency_tag1_displaymode',
        get_string('competency_tag1_displaymode', 'block_dimensions'),
        get_string('competency_tag1_displaymode_desc', 'block_dimensions'),
        'tabs',
        $displaymodeoptions
    ));

    // Enable competency tag2 filter.
    $settings->add(new admin_setting_configcheckbox(
        'block_dimensions/enable_competency_tag2_filter',
        get_string('enable_competency_tag2_filter', 'block_dimensions'),
        get_string('enable_competency_tag2_filter_desc', 'block_dimensions'),
        0
    ));

    // Competency tag2 display mode.
    $settings->add(new admin_setting_configselect(
        'block_dimensions/competency_tag2_displaymode',
        get_string('competency_tag2_displaymode', 'block_dimensions'),
        get_string('competency_tag2_displaymode_desc', 'block_dimensions'),
        'tabs',
        $displaymodeoptions
    ));

    // -----------------------------------------------------------------------
    // 3. Plan Filters.
    // -----------------------------------------------------------------------
    $settings->add(new admin_setting_heading(
        'block_dimensions/planfiltersheading',
        get_string('planfiltersheading', 'block_dimensions'),
        get_string('planfiltersheading_desc', 'block_dimensions')
    ));

    // Enable plan tag1 filter.
    $settings->add(new admin_setting_configcheckbox(
        'block_dimensions/enable_plan_tag1_filter',
        get_string('enable_plan_tag1_filter', 'block_dimensions'),
        get_string('enable_plan_tag1_filter_desc', 'block_dimensions'),
        0
    ));

    // Plan tag1 display mode.
    $settings->add(new admin_setting_configselect(
        'block_dimensions/plan_tag1_displaymode',
        get_string('plan_tag1_displaymode', 'block_dimensions'),
        get_string('plan_tag1_displaymode_desc', 'block_dimensions'),
        'tabs',
        $displaymodeoptions
    ));

    // Enable plan tag2 filter.
    $settings->add(new admin_setting_configcheckbox(
        'block_dimensions/enable_plan_tag2_filter',
        get_string('enable_plan_tag2_filter', 'block_dimensions'),
        get_string('enable_plan_tag2_filter_desc', 'block_dimensions'),
        0
    ));

    // Plan tag2 display mode.
    $settings->add(new admin_setting_configselect(
        'block_dimensions/plan_tag2_displaymode',
        get_string('plan_tag2_displaymode', 'block_dimensions'),
        get_string('plan_tag2_displaymode_desc', 'block_dimensions'),
        'tabs',
        $displaymodeoptions
    ));

    // -----------------------------------------------------------------------
    // 4. Plan Card Layout.
    // -----------------------------------------------------------------------
    $settings->add(new admin_setting_heading(
        'block_dimensions/plancardlayoutheading',
        get_string('plancardlayoutheading', 'block_dimensions'),
        get_string('plancardlayoutheading_desc', 'block_dimensions')
    ));

    // Plan card layout mode.
    $layoutoptions = [
        'vertical' => get_string('plancard_layout_vertical', 'block_dimensions'),
        'horizontal' => get_string('plancard_layout_horizontal', 'block_dimensions'),
    ];
    $settings->add(new admin_setting_configselect(
        'block_dimensions/plancard_layout',
        get_string('plancard_layout', 'block_dimensions'),
        get_string('plancard_layout_desc', 'block_dimensions'),
        'vertical',
        $layoutoptions
    ));
}
