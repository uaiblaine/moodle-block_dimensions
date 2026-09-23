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
 * Block Dimensions main file.
 *
 * @package    block_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Learner-facing block listing the user's learning plans and competencies as cards.
 *
 * The block itself renders only a shell; amd/src/filters.js fetches the cards from the
 * block_dimensions_get_block_dataset web service and renders them in the browser.
 *
 * @package    block_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_dimensions extends block_base {
    /**
     * Applicable formats.
     *
     * @return array
     */
    public function applicable_formats() {
        return ['site' => true, 'course' => true, 'my' => true];
    }

    /**
     * Init.
     *
     * @return void
     */
    public function init() {
        $this->title = get_string('pluginname', 'block_dimensions');
    }

    /**
     * Hide the block title when the hide_block_title setting is on.
     *
     * @return void
     */
    public function specialization() {
        if (get_config('block_dimensions', 'hide_block_title')) {
            $this->title = '';
        }
    }

    /**
     * Get content.
     *
     * @return stdClass
     */
    public function get_content() {
        if (isset($this->content)) {
            return $this->content;
        }
        $this->content = new stdClass();

        if (!get_config('core_competency', 'enabled')) {
            return $this->content;
        }

        // Block needs a valid, non-guest user to be logged-in in order to display the user's learning plans.
        if (isloggedin() && !isguestuser()) {
            $summary = new \block_dimensions\output\summary();
            /* Like block_lp, render nothing when the user holds no plan the block can show (see
               summary::has_content()). Core drops an empty block from the page, except in editing
               mode, where it keeps its controls so it can still be moved or removed. */
            if (!$summary->has_content()) {
                return $this->content;
            }

            $renderer = $this->page->get_renderer('block_dimensions');
            $this->content->text = $renderer->render($summary);
            $this->content->footer = '';
        }

        return $this->content;
    }

    /**
     * This block has a global settings page.
     *
     * @return bool
     */
    public function has_config() {
        return true;
    }

    /**
     * This block shouldn't be added to a page if the competencies advanced feature is disabled.
     *
     * @param moodle_page $page
     * @return bool
     */
    public function can_block_be_added(moodle_page $page): bool {
        return get_config('core_competency', 'enabled');
    }
}
