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
 * Behat step definitions for block_dimensions.
 *
 * @package    block_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.
require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

use Behat\Mink\Exception\ExpectationException;
use Moodle\BehatExtension\Exception\SkippedException;

/**
 * Step definitions for block_dimensions Behat features.
 *
 * The colour-mode steps here drive the HOST's side of the dark-mode contract. The attribute they
 * set is the production signal - Bootstrap 5.3's own data-bs-theme, which Moodle 5.3's theme_boost
 * writes on the document element from its before_html_attributes listener and from the head script
 * that resolves "auto" with matchMedia. A step is needed only because no shipped theme in the
 * 4.05-5.02 range turns it on yet, so there is no way to ask the site for it.
 *
 * The plugin itself must never write that attribute, and
 * block_dimensions\local\colour_tokens_test::test_plugin_never_writes_the_host_signal fails the
 * build if any file outside this directory does. Whether the page is dark is not knowable
 * server-side - core's own answer needs a stored preference plus a client-side matchMedia
 * resolution - and a wrong guess is exactly the defect the design exists to prevent.
 *
 * executeScript for a DOM attribute follows core's own idiom in
 * lib/tests/behat/behat_navigation.php.
 *
 * THE STEP WORDING DIFFERS FROM THE SIBLING'S ON PURPOSE, AND IT IS NOT DRIFT. Behat step
 * definitions are site-global: Moodle loads every installed plugin's context into one suite, so
 * two contexts declaring the same regular expression is a hard failure - "Step ... is already
 * defined in ..." - that fails every scenario in BOTH plugins, not just the duplicate. Measured on
 * m502 with local_dimensions' identical steps present: 4 scenarios, 42 steps, all failed before a
 * single assertion ran. block_dimensions declares local_dimensions as a hard dependency in
 * version.php and its CI checks it out on every job, so the two contexts are always loaded
 * together and the collision is certain rather than hypothetical. These steps therefore say
 * "host" where the sibling says "page". The contract they assert is identical, and both files
 * remain greppable on "colour mode", "background colour" and "colour token should resolve to".
 *
 * @package    block_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_block_dimensions extends behat_base {
    /** @var string|null The page background colour recorded by the remember step. */
    protected $rememberedpagebackground = null;

    /**
     * The JavaScript expression that yields the page's own background colour.
     *
     * The body first, because that is what a reader means by "the page"; the document element
     * when the body is transparent, which is what a theme that paints html rather than body
     * produces.
     *
     * @return string A JavaScript expression returning a colour string.
     */
    protected function page_background_expression(): string {
        return "(function() {"
            . " var b = window.getComputedStyle(document.body).backgroundColor;"
            . " if (b && b !== 'rgba(0, 0, 0, 0)' && b !== 'transparent') { return b; }"
            . " return window.getComputedStyle(document.documentElement).backgroundColor;"
            . "})()";
    }

    /**
     * Reads one computed colour out of the browser.
     *
     * @param string $expression A JavaScript expression returning a colour string.
     * @return string The colour, whitespace-collapsed and lower-cased.
     */
    protected function evaluate_colour(string $expression): string {
        $value = $this->getSession()->evaluateScript('return ' . $expression . ';');

        return strtolower(trim(preg_replace('/\s+/', ' ', (string) $value)));
    }

    /**
     * Puts the page into the host's colour mode.
     *
     * @Given /^the host colour mode is "(?P<mode_string>light|dark)"$/
     * @param string $mode The colour mode to force.
     * @return void
     */
    public function the_host_colour_mode_is(string $mode): void {
        $attribute = \block_dimensions\local\colour_mode::HOST_ATTRIBUTE;
        $this->getSession()->executeScript(
            "document.documentElement.setAttribute('" . $attribute . "', '" . $mode . "');"
        );
    }

    /**
     * Records the page's own background colour so a later step can compare against it.
     *
     * @Given /^I remember the host page background colour$/
     * @return void
     */
    public function i_remember_the_host_page_background_colour(): void {
        $this->rememberedpagebackground = $this->evaluate_colour($this->page_background_expression());
    }

    /**
     * Asserts the plugin surface tracks the page, whichever way the page went.
     *
     * This is the whole of decision #1 written as an executable invariant, and it needs no branch
     * tag: the plugin's ground IS the page's ground, so the two cannot disagree whether or not the
     * host has a dark palette to move to. On Moodle 4.5, which ships none, nothing moves and the
     * two are still equal; on 5.1 and 5.2 both move together. That is why this reads as one
     * equality rather than as two branches - and the remembered value is reported alongside it, so
     * a passing run says which of the two cases it actually exercised.
     *
     * The transparency check is the anti-vacuity control. An element the plugin has stopped
     * painting computes rgba(0, 0, 0, 0), which would "match" nothing at all and let this scenario
     * pass over a surface that is no longer under test.
     *
     * @Then /^the "(?P<selector_string>[^"]*)" element background should still match the host page$/
     * @param string $selector A CSS selector for the element under test.
     * @return void
     */
    public function the_element_background_should_still_match_the_host_page(string $selector): void {
        $escaped = str_replace(["\\", "'"], ["\\\\", "\\'"], $selector);
        $element = $this->evaluate_colour(
            "(function() {"
            . " var e = document.querySelector('" . $escaped . "');"
            . " return e ? window.getComputedStyle(e).backgroundColor : 'no such element';"
            . "})()"
        );
        $page = $this->evaluate_colour($this->page_background_expression());
        $moved = $this->rememberedpagebackground !== null && $this->rememberedpagebackground !== $page;
        $context = $moved
            ? 'the host moved from ' . $this->rememberedpagebackground . ' to ' . $page
            : 'the host stayed at ' . $page . ' (this branch ships no dark palette)';
        if ($element === 'rgba(0, 0, 0, 0)' || $element === 'transparent') {
            throw new ExpectationException(
                'The "' . $selector . '" element paints no background at all, so this assertion would '
                    . 'pass over a surface nothing is testing. ' . $context,
                $this->getSession()
            );
        }
        if ($element !== $page) {
            throw new ExpectationException(
                'The "' . $selector . '" element is ' . $element . ' while the page is ' . $page
                    . '. The plugin ground is the page ground, so the two can never disagree; '
                    . $context,
                $this->getSession()
            );
        }
    }

    /**
     * Asserts a resolved colour token, so an assertion names a token rather than a pixel.
     *
     * @Then /^the "(?P<token_string>[^"]*)" colour token should resolve to "(?P<value_string>[^"]*)" on the host$/
     * @param string $token Token name without the leading dashes.
     * @param string $value Expected resolved value, normalised (lower-case, whitespace-collapsed).
     * @return void
     */
    public function the_colour_token_should_resolve_to(string $token, string $value): void {
        $escaped = str_replace(["\\", "'"], ["\\\\", "\\'"], $token);
        /* Read at body, which is where the token block is declared. Custom properties inherit
           DOWNWARDS only, so reading at documentElement returns the empty string for every one
           of them. body is also correct against the older contract, when the block sat on
           :root: the values were visible there by inheritance. */
        $actual = $this->evaluate_colour(
            "window.getComputedStyle(document.body).getPropertyValue('--" . $escaped . "')"
        );
        $expected = strtolower(trim(preg_replace('/\s+/', ' ', $value)));
        if ($actual !== $expected) {
            throw new ExpectationException(
                'The --' . $token . ' token resolves to "' . $actual . '" but "' . $expected
                    . '" was expected.',
                $this->getSession()
            );
        }
    }

    /**
     * Skips the scenario on a branch whose core ships no dark palette.
     *
     * Detected at RUNTIME rather than from the branch number: the step sets the attribute, reads
     * the page background back, restores the previous state, and skips if the value did not move.
     * Moodle 5.00 is a real CI leg for this plugin and nothing in this design has measured its
     * compiled sheet, so a branch-number guard would be an assumption where a measurement is
     * available.
     *
     * @Given /^the host ships a colour mode$/
     * @throws SkippedException
     * @return void
     */
    public function the_host_ships_a_colour_mode(): void {
        $attribute = \block_dimensions\local\colour_mode::HOST_ATTRIBUTE;
        $dark = \block_dimensions\local\colour_mode::DARK;
        $before = $this->evaluate_colour($this->page_background_expression());
        $previous = $this->getSession()->evaluateScript(
            "return document.documentElement.getAttribute('" . $attribute . "');"
        );
        $this->getSession()->executeScript(
            "document.documentElement.setAttribute('" . $attribute . "', '" . $dark . "');"
        );
        $after = $this->evaluate_colour($this->page_background_expression());
        if ($previous === null) {
            $this->getSession()->executeScript(
                "document.documentElement.removeAttribute('" . $attribute . "');"
            );
        } else {
            $this->getSession()->executeScript(
                "document.documentElement.setAttribute('" . $attribute . "', '" . $previous . "');"
            );
        }
        if ($before === $after) {
            throw new SkippedException(
                'This Moodle branch compiles no [' . $attribute . '="' . $dark . '"] palette, so there '
                    . 'is no host colour mode for the plugin\'s own decorative tokens to follow. The '
                    . 'relative invariant the other scenarios assert holds here and is not skipped.'
            );
        }
    }
}
