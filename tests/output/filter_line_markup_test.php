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
 * Tests for the filter line's markup, as the block's shell draws it.
 *
 * @package    block_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_dimensions\output;

use advanced_testcase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The filter line of each section (templates/filter_line.mustache) as summary.mustache draws it: one
 * line per section, under its heading, in the contract's slot order, with the hooks
 * amd/src/filter_line.js and amd/src/filters.js read.
 */
#[CoversClass(\block_dimensions\output\summary::class)]
#[CoversClass(\block_dimensions\output\renderer::class)]
final class filter_line_markup_test extends advanced_testcase {
    /**
     * The block's shell for a fresh user, as the renderer draws it.
     *
     * @return \DOMXPath
     */
    private function shell(): \DOMXPath {
        global $PAGE;
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $PAGE->set_url('/my/');
        $html = $PAGE->get_renderer('block_dimensions')->render(new summary($user));
        $document = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8"?><div id="root">' . $html . '</div>');
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        return new \DOMXPath($document);
    }

    /**
     * The one element an XPath expression finds.
     *
     * @param \DOMXPath $xpath The shell.
     * @param string $expression The expression.
     * @param \DOMNode|null $context Where to look from.
     * @return \DOMElement
     */
    private function one(\DOMXPath $xpath, string $expression, ?\DOMNode $context = null): \DOMElement {
        $found = $xpath->query($expression, $context);
        $this->assertSame(1, $found->length, "Expected one match for {$expression}.");
        $element = $found->item(0);
        $this->assertInstanceOf(\DOMElement::class, $element);
        return $element;
    }

    /**
     * The XPath test for a class name.
     *
     * @param string $class The class.
     * @return string
     */
    private function hasclass(string $class): string {
        return "contains(concat(' ', normalize-space(@class), ' '), ' {$class} ')";
    }

    /**
     * The section's toolbar.
     *
     * @param \DOMXPath $xpath The shell.
     * @param string $type plan or competency.
     * @return \DOMElement
     */
    private function toolbar(\DOMXPath $xpath, string $type): \DOMElement {
        return $this->one($xpath, "//div[@data-region='dims-line-toolbar'][@data-line-type='{$type}']");
    }

    /**
     * The data-region, data-action or class of each control of a line, in document order.
     *
     * @param \DOMXPath $xpath The shell.
     * @param \DOMElement $toolbar The section's toolbar.
     * @return array The slot names.
     */
    private function slots(\DOMXPath $xpath, \DOMElement $toolbar): array {
        $line = $this->one($xpath, ".//div[@data-region='dims-line']", $toolbar);
        $slots = [];
        foreach ($line->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }
            $slots[] = $child->getAttribute('data-region') ?: $child->getAttribute('data-action');
        }
        return $slots;
    }

    /**
     * Each section draws a line of its own, in the contract's slot order, with the chips row under it.
     *
     * Changes that must make it fail: one line for the block; the star before the search or after
     * Filters; the close button anywhere but last; the scope on the competencies; the eraser a cross;
     * the listbox shown before the module fills it; two lines writing the same id.
     *
     * @return void
     */
    public function test_each_section_draws_its_line_in_the_contracts_order(): void {
        $this->resetAfterTest();
        set_config('enable_search', 1, 'block_dimensions');
        $xpath = $this->shell();

        $this->assertSame(2, $xpath->query("//div[@data-region='dims-line-toolbar']")->length);
        $plan = $this->toolbar($xpath, 'plan');
        $this->assertSame(
            ['dims-line-scope', 'dims-line-search', 'dims-search-open', 'dims-line-star', 'dims-line-filters',
                'dims-search-close', 'dims-line-suggest'],
            $this->slots($xpath, $plan)
        );
        $competency = $this->toolbar($xpath, 'competency');
        $this->assertSame(
            ['dims-line-search', 'dims-search-open', 'dims-line-star', 'dims-line-filters', 'dims-search-close',
                'dims-line-suggest'],
            $this->slots($xpath, $competency),
            'The competencies have no scope.'
        );

        foreach (['plan' => $plan, 'competency' => $competency] as $type => $toolbar) {
            $this->assertTrue($toolbar->hasAttribute('hidden'), 'Hidden until the section has something to show.');
            $eraser = $this->one($xpath, ".//button[@data-action='dims-search-clear']", $toolbar);
            $this->one($xpath, ".//i[" . $this->hasclass('fa-eraser') . "]", $eraser);
            $this->assertSame(0, $xpath->query(".//i[" . $this->hasclass('fa-times') . "]", $eraser)->length);
            $listbox = $this->one($xpath, ".//div[@data-region='dims-line-suggest']", $toolbar);
            $this->assertTrue($listbox->hasAttribute('hidden'));
            $star = $this->one($xpath, ".//button[@data-region='dims-line-star']", $toolbar);
            $this->assertSame('false', $star->getAttribute('aria-pressed'));
            $this->assertSame($type, $star->getAttribute('data-fav-star'));
            $toggle = $this->one($xpath, ".//button[@data-action='dims-filters-toggle']", $toolbar);
            $this->assertSame('false', $toggle->getAttribute('aria-expanded'));
            $panel = $this->one($xpath, ".//div[@data-region='dims-line-panel']", $toolbar);
            $this->assertSame($panel->getAttribute('id'), $toggle->getAttribute('aria-controls'));
            $this->assertTrue($panel->hasAttribute('hidden'));
            $this->one($xpath, ".//div[" . $this->hasclass('dims-filters-host') . "][@data-filters-type='{$type}']", $panel);
            $this->one($xpath, ".//button[" . $this->hasclass('dims-clear-filters-btn') . "][@data-clear-type='{$type}']", $panel);
            $this->one($xpath, ".//button[@data-action='dims-panel-show']", $panel);
            $chips = $this->one($xpath, "./div[@data-region='dims-line-chips']", $toolbar);
            $this->assertTrue($chips->hasAttribute('hidden'));
            $field = $this->one($xpath, ".//input[@type='search'][@data-search-type='{$type}']", $toolbar);
            $this->one($xpath, ".//label[@for='" . $field->getAttribute('id') . "']", $toolbar);
        }

        $ids = [];
        foreach ($xpath->query('//*[@id]') as $element) {
            $ids[] = $element->getAttribute('id');
        }
        $this->assertSame(array_unique($ids), $ids, 'Two lines never write the same id.');
    }

    /**
     * Each line sits under its section's heading, or first in its section when the headings are off.
     *
     * Changes that must make it fail: a line above its heading; both lines at the head of the block;
     * a line after its grid.
     *
     * @return void
     */
    public function test_each_line_sits_between_its_heading_and_its_grid(): void {
        $this->resetAfterTest();
        set_config('enable_section_headers', 1, 'block_dimensions');
        $order = function (\DOMXPath $xpath): array {
            $marks = [];
            $nodes = $xpath->query("//h3[@data-section-header] | //div[@data-region='dims-line-toolbar'] | //ul[@data-cards-type]");
            foreach ($nodes as $node) {
                $marks[] = $node->getAttribute('data-section-header') ? 'heading-' . $node->getAttribute('data-section-header')
                    : ($node->getAttribute('data-line-type') ? 'line-' . $node->getAttribute('data-line-type')
                    : 'grid-' . $node->getAttribute('data-cards-type'));
            }
            return $marks;
        };
        $this->assertSame(
            ['heading-plan', 'line-plan', 'grid-plan', 'heading-competency', 'line-competency', 'grid-competency'],
            $order($this->shell())
        );

        set_config('enable_section_headers', 0, 'block_dimensions');
        $this->assertSame(['line-plan', 'grid-plan', 'line-competency', 'grid-competency'], $order($this->shell()));
    }

    /**
     * With the search off no line draws a search; the star and Filters stay.
     *
     * The control is the search on, in the first test.
     *
     * Changes that must make it fail: enable_search ignored; the star or Filters drawn only beside a search.
     *
     * @return void
     */
    public function test_the_search_is_left_out_while_it_is_off(): void {
        $this->resetAfterTest();
        set_config('enable_search', 0, 'block_dimensions');
        $xpath = $this->shell();

        $this->assertSame(0, $xpath->query("//input[@type='search']")->length);
        foreach (['plan', 'competency'] as $type) {
            $toolbar = $this->toolbar($xpath, $type);
            foreach (['dims-search-open', 'dims-search-close', 'dims-search-clear'] as $action) {
                $this->assertSame(0, $xpath->query(".//*[@data-action='{$action}']", $toolbar)->length, $action);
            }
            $this->assertSame(0, $xpath->query(".//*[@data-region='dims-line-suggest']", $toolbar)->length);
            $this->one($xpath, ".//button[@data-region='dims-line-star']", $toolbar);
            $this->one($xpath, ".//button[@data-action='dims-filters-toggle']", $toolbar);
        }
    }

    /**
     * The line says the contract's words, each section its own things.
     *
     * Changes that must make it fail: a placeholder naming the wrong things; the eraser, the close
     * button or Filters without their names; the suggestion headings missing from the listbox.
     *
     * @return void
     */
    public function test_the_line_says_the_contracts_words(): void {
        $this->resetAfterTest();
        set_config('enable_search', 1, 'block_dimensions');
        $xpath = $this->shell();

        $words = [
            'plan' => ['line_search_plans_placeholder', 'line_search_plans', 'landmark_plans'],
            'competency' => ['line_search_competencies_placeholder', 'line_search_competencies', 'landmark_competencies'],
        ];
        foreach ($words as $type => [$placeholder, $label, $items]) {
            $toolbar = $this->toolbar($xpath, $type);
            $field = $this->one($xpath, ".//input[@type='search']", $toolbar);
            $this->assertSame(get_string($placeholder, 'block_dimensions'), $field->getAttribute('placeholder'));
            $this->assertSame(
                get_string($label, 'block_dimensions'),
                trim($this->one($xpath, ".//label[@for='" . $field->getAttribute('id') . "']", $toolbar)->textContent)
            );
            $eraser = $this->one($xpath, ".//button[@data-action='dims-search-clear']", $toolbar);
            $this->assertSame(get_string('line_clear_text', 'block_dimensions'), $eraser->getAttribute('aria-label'));
            $close = $this->one($xpath, ".//button[@data-action='dims-search-close']", $toolbar);
            $this->assertSame(get_string('line_close_search', 'block_dimensions'), $close->getAttribute('aria-label'));
            $toggle = $this->one($xpath, ".//button[@data-action='dims-filters-toggle']", $toolbar);
            $this->assertStringContainsString(get_string('line_filters', 'block_dimensions'), $toggle->textContent);
            $star = $this->one($xpath, ".//button[@data-region='dims-line-star']", $toolbar);
            $this->assertSame(get_string('line_favourites', 'block_dimensions'), $star->getAttribute('title'));
            $listbox = $this->one($xpath, ".//div[@data-region='dims-line-suggest']", $toolbar);
            $this->assertSame(get_string('line_suggest_filters', 'block_dimensions'), $listbox->getAttribute('data-label-filters'));
            $this->assertSame(get_string('line_suggest_start', 'block_dimensions'), $listbox->getAttribute('data-label-start'));
            $this->assertSame(get_string($items, 'block_dimensions'), $listbox->getAttribute('data-label-items'));
            $clearall = $this->one($xpath, ".//button[@data-action='dims-clear-all']", $toolbar);
            $this->assertSame(get_string('line_clear_all', 'block_dimensions'), trim($clearall->textContent));
            $chips = $this->one($xpath, ".//div[@data-region='dims-line-chips']", $toolbar);
            $this->assertSame(get_string('line_remove_filter', 'block_dimensions'), $chips->getAttribute('data-label-remove'));
        }
    }

    /**
     * The header's search and the phone's sliders toggle are gone: each line holds its own search, and folding replaces the toggle.
     *
     * Changes that must make it fail: either brought back beside the lines.
     *
     * @return void
     */
    public function test_the_header_search_and_the_sliders_toggle_are_gone(): void {
        $this->resetAfterTest();
        set_config('enable_search', 1, 'block_dimensions');
        $xpath = $this->shell();

        $this->assertSame(0, $xpath->query("//*[" . $this->hasclass('dims-block-search-input') . "]")->length);
        $this->assertSame(0, $xpath->query("//*[" . $this->hasclass('dims-filter-toggle-btn') . "]")->length);
        $this->assertSame(0, $xpath->query("//*[" . $this->hasclass('dims-search-row') . "]")->length);
        // Control: the lines' own fields are there.
        $this->assertSame(2, $xpath->query("//input[@type='search']")->length);
    }
}
