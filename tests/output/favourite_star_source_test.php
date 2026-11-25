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
 * Tests for the favourite star of a card drawn outside the block, by its source.
 *
 * @package    block_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_dimensions\output;

use block_dimensions\plan_cards;
use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Pins the rules of amd/src/favourite_star.js, the star of a plan card another plugin draws.
 *
 * moodle-plugin-ci has no JavaScript test runner, so these are assertions about spelling, each scoped
 * to the function it is about, in the manner of filter_line_source_test. The rules: the module the
 * API names is this file; one delegated listener that leaves the block's own stars to filters.js; a
 * press that stops at the star (the card is a stretched link) and waits for the answer it is pending
 * on; the button drawn from the server's answer with the template's own icon classes and the block's
 * strings; one spelling of the event other code listens for.
 */
#[CoversNothing]
final class favourite_star_source_test extends \basic_testcase {
    /** @var string The module under test. */
    private const MODULE = 'favourite_star';

    /** @var string The event a caller listens for, as the README documents it. */
    private const EVENT = 'block_dimensions/favourite:changed';

    /**
     * Absolute path to the plugin root.
     *
     * @return string
     */
    private function plugin_root(): string {
        return dirname(__DIR__, 2);
    }

    /**
     * The module's source.
     *
     * @return string
     */
    private function source(): string {
        $path = $this->plugin_root() . '/amd/src/' . self::MODULE . '.js';
        $this->assertFileExists($path);
        return (string) file_get_contents($path);
    }

    /**
     * The module's source without its comments: what runs.
     *
     * @return string
     */
    private function code(): string {
        $code = (string) preg_replace('~/\*.*?\*/~s', '', $this->source());
        return (string) preg_replace('~^\s*//.*$~m', '', $code);
    }

    /**
     * The body of one top-level arrow function of the module.
     *
     * @param string $name The function's name.
     * @return string The body, without the signature or the closing brace.
     */
    private function body(string $name): string {
        $pattern = '~^(export )?const ' . preg_quote($name, '~') . ' = [^\n]*\{\n(.*?)\n\};~ms';
        $found = preg_match($pattern, $this->code(), $matches);
        $this->assertSame(1, $found, "No function named {$name} in " . self::MODULE . '.');
        $this->assertNotSame('', trim($matches[2]), "The body of {$name} is empty.");
        return $matches[2];
    }

    /**
     * The API names this module, and the event has one spelling, dispatched with the answer.
     *
     * Changes that must make it fail: the constant naming another module; the event spelled twice or
     * differently; the event dispatched without bubbling, before the answer, or with the posted state.
     *
     * @return void
     */
    public function test_the_api_names_the_module_and_the_event_has_one_spelling(): void {
        $this->assertSame('block_dimensions/' . self::MODULE, plan_cards::STAR_MODULE);
        $code = $this->code();
        $this->assertStringContainsString("export const EVENT = '" . self::EVENT . "';", $code);
        $this->assertSame(1, substr_count($code, self::EVENT), 'The event is spelled once, in EVENT.');
        $this->assertStringContainsString(
            'new CustomEvent(EVENT, {bubbles: true, detail: {type, id, isfavourite}})',
            $this->body('toggle')
        );
        $this->assertStringContainsString('const isfavourite = Boolean(result.isfavourite);', $this->body('toggle'));
        $this->assertStringContainsString('const id = Number.parseInt(button.dataset.favId, 10);', $this->body('toggle'));
    }

    /**
     * One delegated listener, once, that leaves every star inside the block's root to filters.js.
     *
     * Changes that must make it fail: the root check dropped or pointed at a class the theme's host
     * also carries; the listener added on every call to init(); the root selector no longer the
     * block's own root in templates/summary.mustache.
     *
     * @return void
     */
    public function test_the_listener_leaves_the_blocks_own_stars_alone(): void {
        $code = $this->code();
        $this->assertStringContainsString("const BLOCK_ROOT = '.block-dimensions-content';", $code);
        $this->assertStringContainsString("const STAR = '.dims-fav-btn[data-fav-type][data-fav-id]';", $code);
        $this->assertNotSame('.' . plan_cards::HOST_CLASS, '.block-dimensions-content', 'The host class is the theme\'s too.');
        $summary = (string) file_get_contents($this->plugin_root() . '/templates/summary.mustache');
        $this->assertMatchesRegularExpression('~<div id="\{\{containerid\}\}" class="block-dimensions-content"~', $summary);

        $click = $this->body('onClick');
        $this->assertStringContainsString('if (!button || button.closest(BLOCK_ROOT)) {', $click);

        $init = $this->body('init');
        $this->assertStringContainsString('if (initialised) {', $init);
        $this->assertStringContainsString('initialised = true;', $init);
        $this->assertSame(1, substr_count($code, 'addEventListener('), 'One listener in the module.');
        $this->assertStringContainsString("document.addEventListener('click', onClick);", $init);
    }

    /**
     * A press stops at the star, and a second press waits for the first answer.
     *
     * Changes that must make it fail: the default action or the propagation left to the card's
     * stretched link; the pending guard dropped, set after the call, or never cleared; the call made
     * outside a core/pending token; another web service called.
     *
     * @return void
     */
    public function test_a_press_stops_at_the_star_and_waits_for_its_answer(): void {
        $click = $this->body('onClick');
        $this->assertStringContainsString('event.preventDefault();', $click);
        $this->assertStringContainsString('event.stopPropagation();', $click);

        $toggle = $this->body('toggle');
        $guard = strpos($toggle, 'if (button.dataset.favPending) {');
        $set = strpos($toggle, "button.dataset.favPending = '1';");
        $call = strpos($toggle, "methodname: 'block_dimensions_toggle_favourite'");
        $this->assertNotFalse($guard, 'A second press is refused while one is pending.');
        $this->assertNotFalse($set);
        $this->assertNotFalse($call);
        $this->assertLessThan($set, $guard);
        $this->assertLessThan($call, $set, 'The guard is set before the call is sent.');
        $this->assertStringContainsString('delete button.dataset.favPending;', $toggle);
        $this->assertStringContainsString("new Pending('block_dimensions/favourite_star:toggle')", $toggle);
        $this->assertStringContainsString('pending.resolve();', $toggle);

        $services = (string) file_get_contents($this->plugin_root() . '/db/services.php');
        $this->assertStringContainsString("'block_dimensions_toggle_favourite' => [", $services);
    }

    /**
     * The button is drawn from the answer, as the card's template draws both states, in the block's words.
     *
     * Changes that must make it fail: an icon class the template does not draw; aria-pressed, the
     * label or the title left out; a label hard-coded instead of read through core/str; a failure
     * that leaves no notification or changes the button.
     *
     * @return void
     */
    public function test_the_button_is_drawn_like_the_template_from_the_answer(): void {
        $template = (string) file_get_contents($this->plugin_root() . '/templates/plan_card.mustache');
        preg_match_all('~<i class="(fa fa-star[^"]*)" aria-hidden="true"></i>~', $template, $icons);
        $this->assertSame(
            ['fa fa-star dims-fav-icon-filled', 'fa fa-star-o dims-fav-icon-empty'],
            array_values(array_unique($icons[1]))
        );

        $draw = $this->body('draw');
        $this->assertStringContainsString(
            "icon.className = isfavourite ? 'fa fa-star dims-fav-icon-filled' : 'fa fa-star-o dims-fav-icon-empty';",
            $draw
        );
        $this->assertStringContainsString("button.setAttribute('aria-pressed', isfavourite ? 'true' : 'false');", $draw);
        $this->assertStringContainsString("button.setAttribute('aria-label', label);", $draw);
        $this->assertStringContainsString("button.setAttribute('title', label);", $draw);

        $code = $this->code();
        $this->assertStringContainsString("{key: 'addtofavourites', component: 'block_dimensions'}", $code);
        $this->assertStringContainsString("{key: 'removefromfavourites', component: 'block_dimensions'}", $code);
        $this->assertStringContainsString("import {getStrings} from 'core/str';", $code);
        $this->assertStringNotContainsString('Add to favourites', $code);
        $this->assertStringNotContainsString('Remove from favourites', $code);
        $this->assertStringNotContainsString('jquery', strtolower($code));

        $toggle = $this->body('toggle');
        $this->assertStringContainsString('Notification.exception(error);', $toggle);
        $this->assertLessThan(
            strpos($toggle, 'Notification.exception(error);'),
            strpos($toggle, 'draw(button, isfavourite, strings);'),
            'The button is drawn only in the success path.'
        );
    }

    /**
     * The committed build was made from the committed source.
     *
     * Changes that must make it fail: an edit of amd/src left unbuilt.
     *
     * @return void
     */
    public function test_the_build_is_current(): void {
        $map = $this->plugin_root() . '/amd/build/' . self::MODULE . '.min.js.map';
        $this->assertFileExists($map);
        $this->assertFileExists($this->plugin_root() . '/amd/build/' . self::MODULE . '.min.js');
        $decoded = json_decode((string) file_get_contents($map), true);
        $this->assertSame(
            $this->source(),
            (string) ($decoded['sourcesContent'][0] ?? ''),
            'amd/build/' . self::MODULE . ' is not this source: rebuild it with grunt amd and commit the build.'
        );
    }
}
