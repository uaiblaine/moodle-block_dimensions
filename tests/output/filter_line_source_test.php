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
 * Tests for the filter line's client, by its source.
 *
 * @package    block_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_dimensions\output;

/**
 * Pins the rules of amd/src/filter_line.js, the filter line of each section of the block, and of the
 * parts of amd/src/filters.js that arm it.
 *
 * moodle-plugin-ci has no JavaScript test runner, so these are assertions about spelling, each scoped
 * to the function it is about; the JS harness (tests/jsharness) and the Behat feature are what run the
 * modules. The rules are the fleet contract's (moodle-dev/docs/filter-line/CONTRACT.md, sections 2 to
 * 4 and 8): the line measured on its own box, the steps tried in order with every scope cell drawn and
 * no transition, the search growing over the line and closing back to its opener without opening
 * again, the suggestions matched accent- and case-insensitively, every floating layer placed under the
 * line's measured bottom, the bottom sheet, the chips row and the slash key; and each section armed
 * with a line of its own, its own search and its own star.
 *
 * @coversNothing
 */
final class filter_line_source_test extends \basic_testcase {
    /** @var string The module under test. */
    private const MODULE = 'filter_line';

    /**
     * Absolute path to the plugin root.
     *
     * @return string
     */
    private function plugin_root(): string {
        return dirname(__DIR__, 2);
    }

    /**
     * One module's source.
     *
     * @param string $module The module's name.
     * @return string
     */
    private function source(string $module = self::MODULE): string {
        $path = $this->plugin_root() . '/amd/src/' . $module . '.js';
        $this->assertFileExists($path);
        return (string) file_get_contents($path);
    }

    /**
     * A module's source without its comments: what runs.
     *
     * @param string $module The module's name.
     * @return string
     */
    private function code(string $module = self::MODULE): string {
        $code = (string) preg_replace('~/\*.*?\*/~s', '', $this->source($module));
        return (string) preg_replace('~^\s*//.*$~m', '', $code);
    }

    /**
     * The body of one arrow function of the module, at whatever depth it is declared.
     *
     * @param string $name The function's name.
     * @param string $module The module's name.
     * @return string The body, without the signature or the closing brace.
     */
    private function body(string $name, string $module = self::MODULE): string {
        $pattern = '~\n( +)const ' . preg_quote($name, '~') . ' = [^\n]*\{\n(.*?)\n\1\};~s';
        $found = preg_match($pattern, $this->source($module), $matches);
        $this->assertSame(1, $found, "No function named {$name} in {$module}.");
        $this->assertNotSame('', trim($matches[2]), "The body of {$name} is empty.");
        return $matches[2];
    }

    /**
     * The body of a named function declaration of an AMD module, up to its closing brace at the same indent.
     *
     * @param string $module The module's name.
     * @param string $name The function's name.
     * @return string
     */
    private function declared(string $module, string $name): string {
        $pattern = '/^( *)function ' . preg_quote($name, '/') . '\([^)]*\) \{\n(.*?)\n\1\}$/ms';
        $this->assertSame(1, preg_match($pattern, $this->source($module), $match), "function {$name} not found in {$module}");
        return $match[2];
    }

    /**
     * The line is measured on its own box, never the viewport's, and refolds after the frame.
     *
     * A drawer opening changes the line's width and not the window's, so a viewport query or a
     * window resize listener would leave the line at a step that no longer fits it.
     *
     * Changes that must make it fail: the observer on the window, the document or the body; the
     * width read off innerWidth; a resize listener on the window; the refold outside a frame or not
     * held as pending JavaScript.
     *
     * @return void
     */
    public function test_the_line_measures_its_own_width(): void {
        $create = $this->body('create');
        $this->assertMatchesRegularExpression(
            '~new ResizeObserver\(\(entries\) => \{.*?\}\)\.observe\(line\);~s',
            $create,
            'The observer watches the line itself.'
        );
        $this->assertStringContainsString('const width = Math.round(entries[0].contentRect.width);', $create);
        $this->assertStringContainsString('if (width !== state.width) {', $create, 'A height change alone refolds nothing.');
        $code = $this->code();
        $this->assertStringNotContainsString('innerWidth', $code);
        $this->assertStringNotContainsString("addEventListener('resize'", $code);
        $this->assertStringNotContainsString('.observe(document', $code);
        $this->assertStringContainsString('window.requestAnimationFrame(', $this->body('refit'));
        $this->assertStringContainsString("new Pending('block_dimensions/filter_line:fit')", $this->body('refit'));
    }

    /**
     * The steps are tried in order, measured with every cell drawn and no transition, and written as classes.
     *
     * Changes that must make it fail: the measuring class not set or not taken off; the absent cells
     * not drawn for the measure or not hidden again; a step tried out of order; the last step not
     * taken whatever it measures; the step written anywhere but as a class on the line; the status
     * pills left first on the line at the two-line step.
     *
     * @return void
     */
    public function test_the_line_folds_at_the_first_step_that_fits(): void {
        $source = $this->source();
        $this->assertStringContainsString("const STEPS = ['s1', 's2', 's3', 's4'];", $source);
        $this->assertStringContainsString(
            "const TRIES = [['s1', false], ['s2', false], ['s3', true], ['s4', false], ['s4', true]];",
            $source
        );
        foreach (['s1', 's2', 's3', 's4'] as $step) {
            $this->assertStringContainsString("        {$step}: 'dims-line--{$step}',", $source);
        }
        $this->assertStringContainsString("measuring: 'dims-line--measuring',", $source);
        $this->assertStringContainsString("folded: 'dims-line--folded',", $source);
        $fit = $this->body('fit');
        $this->assertMatchesRegularExpression(
            '~line\.classList\.add\(CLASSES\.measuring\);.*?TRIES\.some\(.*?line\.classList\.remove\(CLASSES\.measuring\);~s',
            $fit,
            'Measured under the measuring class, taken off afterwards.'
        );
        $this->assertMatchesRegularExpression(
            "~querySelectorAll\(SELECTORS\.cell \+ '\[hidden\]'\).*?cell\.hidden = false;.*?TRIES\.some.*?cell\.hidden = true;~s",
            $fit,
            'Every cell of the scope is drawn for the measure and hidden again after it.'
        );
        $this->assertStringContainsString('return index === TRIES.length - 1 || !overflows();', $fit);
        $set = $this->body('setStep');
        $this->assertStringContainsString(
            'STEPS.forEach((name) => line.classList.toggle(STEP_CLASSES[name], name === step));',
            $set
        );
        $this->assertStringContainsString('placeScope(step === TWO_LINES);', $set);
        $scope = $this->body('placeScope');
        $this->assertStringContainsString('line.insertBefore(scope, last);', $scope);
        $this->assertStringContainsString('quietly(focused);', $scope);
        $over = $this->body('overflows');
        $this->assertStringContainsString('rect.right > right || rect.left < left', $over);
        $this->assertStringContainsString('if (child === parts.listbox) {', $over, 'A layer never counts.');
    }

    /**
     * The search grows over the line at the third and fourth steps, and closes back to its opener quietly.
     *
     * Changes that must make it fail: the morph at the first two steps; the close giving the focus to
     * nothing or opening the search again; the field's focus listener without its quiet guard or
     * opening on a focus the reader did not give; Escape left to the browser while the search is open.
     *
     * @return void
     */
    public function test_the_search_grows_over_the_line_and_closes_back_quietly(): void {
        $this->assertStringContainsString("const MORPH_STEPS = ['s3', 's4'];", $this->source());
        $this->assertStringContainsString("searching: 'dims-line--searching',", $this->source());
        $this->assertStringContainsString(
            'hassearch && MORPH_STEPS.indexOf(state.step) >= 0',
            $this->source(),
            'The morph needs a search, and the third or fourth step.'
        );
        $open = $this->body('openSearch');
        $this->assertMatchesRegularExpression(
            '~if \(morphs\(\) && !state\.searching\) \{\s*state\.searching = true;\s*state\.from = from \|\| field;'
                . '\s*line\.classList\.add\(CLASSES\.searching\);~',
            $open
        );
        $close = $this->body('closeSearch');
        $this->assertStringContainsString('line.classList.remove(CLASSES.searching);', $close);
        $this->assertStringContainsString(
            'quietly([from, field, parts.opener].find((control) => shown(control)) || null);',
            $close,
            'The focus goes back to the control that opened it, or the one drawn in its place.'
        );
        $quietly = $this->body('quietly');
        $this->assertMatchesRegularExpression(
            '~state\.quiet = true;\s*try \{\s*element\.focus\(\{preventScroll: true\}\);\s*\} finally \{\s*state\.quiet = false;~',
            $quietly
        );
        $listen = $this->body('listenSearch');
        $this->assertMatchesRegularExpression(
            "~field\.addEventListener\('focusin', \(\) => \{.*?if \(state\.quiet \|\| !intent\) \{\s*return;\s*\}\s*"
                . "openSearch\(field\);~s",
            $listen,
            'A quiet focus, or one the reader did not give, opens nothing.'
        );
        $this->assertStringContainsString('const intent = state.intent || Date.now() - state.tabat < TAB_INTENT;', $listen);
        $this->assertStringContainsString("parts.closer.addEventListener('click', () => closeSearch(true));", $listen);
        $keys = $this->body('onFieldKey');
        $this->assertMatchesRegularExpression(
            "~event\.key === 'Escape' && \(state\.open \|\| state\.searching\)\) \{.*?"
                . "event\.preventDefault\(\);\s*closeSearch\(true\);~s",
            $keys
        );
        $line = $this->body('onLineKey');
        $this->assertStringContainsString('closeSearch(true);', $line, 'Escape from the close button too.');
        $this->assertMatchesRegularExpression(
            '~if \(panelOpen\(\)\) \{\s*event\.preventDefault\(\);\s*setPanel\(false, true\);~',
            $line,
            'Escape closes the open panel back to its button first.'
        );
    }

    /**
     * Matching is accent- and case-insensitive, word starts first, then by count; the empty field offers five.
     *
     * Changes that must make it fail: the accents kept; the case kept; the label matched alone and the
     * group never; the count ranked before the word start; the start list not ordered by count; the
     * counts the suggestions offer taken with the typed text; a filter in force or one that would
     * leave nothing offered.
     *
     * @return void
     */
    public function test_the_suggestions_match_by_the_normalise_rule_and_rank_word_starts_first(): void {
        $source = $this->source();
        $this->assertStringContainsString(
            "const fold = (text) => String(text).normalize('NFD').replace(/[\\u0300-\\u036f]/g, '').toLowerCase();",
            $source
        );
        $this->assertStringContainsString("const normalise = (text) => fold(text || '').trim();", $source);
        $this->assertStringContainsString('const MIN_TERM = 1;', $source, 'The block searches from the first character.');
        $this->assertStringContainsString('const START_COUNT = 5;', $source);
        $rank = $this->body('rankOf');
        $this->assertStringContainsString('const label = matchOf(normalise(item.label), term);', $rank);
        $this->assertStringContainsString('const group = matchOf(normalise(item.group), term);', $rank);
        $this->assertLessThan(strpos($rank, 'if (group === 0)'), strpos($rank, 'if (label === 0)'), 'The label first.');
        $this->assertStringContainsString("if (at === 0 || !/[a-z0-9]/.test(text.charAt(at - 1))) {", $this->body('matchOf'));
        $suggest = $this->body('suggest');
        $this->assertStringContainsString('const term = normalise(text);', $suggest);
        $this->assertStringContainsString(
            '.sort((a, b) => count(b) - count(a) || a.order - b.order).slice(0, START_COUNT);',
            $suggest,
            'With less than the floor, the highest counts.'
        );
        $this->assertStringContainsString(
            '.sort((a, b) => a.rank - b.rank || count(b.item) - count(a.item) || a.item.order - b.item.order)',
            $suggest,
            'Word starts first, then the count.'
        );
        $render = $this->body('render');
        $this->assertStringContainsString('state.items = offered.concat(items);', $render);
        $this->assertStringContainsString(
            'const words = typed ? listbox.dataset.labelFilters : listbox.dataset.labelStart;',
            $render
        );
        $this->assertStringContainsString('listbox.dataset.labelItems', $render);
        $this->assertStringNotContainsString('get_string', $this->code());
        $this->assertStringNotContainsString('core/str', $this->code());
        // The filters offered: the counts without the typed text, none in force, none that leaves nothing.
        $arm = $this->declared('filters', 'armLines');
        $this->assertStringContainsString("pill.getAttribute('aria-checked') !== 'true'", $arm);
        $this->assertStringContainsString("star.getAttribute('aria-pressed') !== 'true'", $arm);
        $this->assertStringContainsString('if (state.activeFilters[group.field] === value) {', $arm);
        $this->assertSame(2, substr_count($arm, ', false);'), 'The star and every value count without the text.');
        $this->assertSame(2, substr_count($arm, 'if (count > 0) {'), 'Nothing that would leave nothing.');
        $this->assertStringContainsString(
            "normalizedSearch: withSearch ? state.normalizedSearch : {plan: '', competency: ''}",
            $this->declared('state', 'countWith')
        );
    }

    /**
     * A suggestion applies its filter after the text is dropped; a card is the module's to open.
     *
     * Changes that must make it fail: the text dropped after the filter is applied; the search closed
     * after a pick; the field not given its combobox role; the line making a request.
     *
     * @return void
     */
    public function test_a_suggestion_applies_its_filter_after_the_text_is_dropped(): void {
        $choose = $this->body('choose');
        $this->assertMatchesRegularExpression(
            "~field\.value = '';\s*paintEraser\(\);\s*hooks\.dropTerm\(\);\s*item\.apply\(\);\s*render\(\);~",
            $choose
        );
        $this->assertMatchesRegularExpression(
            "~if \(item\.kind === 'item'\) \{\s*closeSearch\(false\);\s*hooks\.openItem\(item\.element\);~",
            $choose,
            'A card is filters.js\'s to open.'
        );
        $code = $this->code();
        $this->assertStringNotContainsString('core/ajax', $code, 'The line makes no request.');
        $this->assertStringNotContainsString('methodname', $code);
        $this->assertStringContainsString(
            "parts.listbox.addEventListener('mousedown', (event) => event.preventDefault());",
            $this->body('listenSearch')
        );
        $this->assertStringContainsString(
            "(event.key === 'Enter' || event.key === ' ') && state.active >= 0",
            $this->body('onFieldKey')
        );
        $create = $this->body('create');
        foreach (
            [
                "parts.listbox.setAttribute('role', 'listbox');",
                "field.setAttribute('role', 'combobox');",
                "field.setAttribute('aria-autocomplete', 'list');",
                "field.setAttribute('aria-controls', parts.listbox.id);",
            ] as $line
        ) {
            $this->assertStringContainsString($line, $create);
        }
        $this->assertStringContainsString(
            "field.setAttribute('aria-activedescendant', options[state.active].id);",
            $this->body('activate')
        );
    }

    /**
     * Every floating layer is placed under the line's real bottom, measured; on the two-line step the panel is a sheet.
     *
     * Changes that must make it fail: a fixed offset; the top measured from the line's top; the
     * sheet given a top; the sheet's class not written while the panel is open at the two-line step;
     * the panel's button not saying whether it is open.
     *
     * @return void
     */
    public function test_every_layer_is_placed_under_the_lines_measured_bottom(): void {
        $this->assertStringContainsString('bottom = Math.max(bottom, rect.bottom);', $this->body('lineBottom'));
        $place = $this->body('place');
        $this->assertStringContainsString("layer.style.top = Math.round(lineBottom() - top + GAP) + 'px';", $place);
        $this->assertStringContainsString('const top = parent.getBoundingClientRect().top + parent.clientTop;', $place);
        $this->assertMatchesRegularExpression(
            "~if \(layer === parts\.panel && state\.step === TWO_LINES\) \{\s*layer\.style\.top = '';~",
            $place,
            'The sheet is bottom-anchored and takes no top.'
        );
        $this->assertStringContainsString('place(listbox);', $this->body('placeList'));
        $this->assertStringContainsString('place(parts.panel);', $this->body('fit'));
        $this->assertStringContainsString(
            'toolbar.classList.toggle(CLASSES.sheet, state.step === TWO_LINES && panelOpen());',
            $this->body('sheet')
        );
        $this->assertStringContainsString("sheet: 'dims-line-toolbar--sheet',", $this->source());
        $panel = $this->body('setPanel');
        $this->assertStringContainsString("parts.toggle.setAttribute('aria-expanded', open ? 'true' : 'false');", $panel);
        $this->assertMatchesRegularExpression('~if \(open\) \{.*?place\(parts\.panel\);~s', $panel, 'Placed as it opens.');
        $this->assertStringContainsString('sheet();', $panel);
    }

    /**
     * The chips row: a chip per tag filter in force, the text's chip only while the field is folded away,
     * "Clear all" from two; the row reveals a half-hidden chip and fades a hidden side.
     *
     * Changes that must make it fail: the text's chip drawn beside a visible field; "Clear all" from
     * one; a chip without its cross; the reveal on pointerdown or missing on focus; the fades not
     * following the scroll.
     *
     * @return void
     */
    public function test_the_chips_row_follows_the_filters(): void {
        $paint = $this->body('paintChips');
        $this->assertStringContainsString("if (term !== '' && state.folded && !state.searching) {", $paint);
        $this->assertStringContainsString('(hooks.chips() || []).forEach((entry) => {', $paint);
        $this->assertStringContainsString('parts.clearall.hidden = chips.length < 2;', $paint);
        $this->assertStringContainsString('parts.chips.hidden = chips.length === 0;', $paint);
        $this->assertStringContainsString("cross.className = 'fa fa-times';", $this->body('chipOf'), 'A chip has a cross.');
        $rows = $this->body('listenRows');
        $this->assertStringContainsString("row.addEventListener('scroll', () => fades(row), {passive: true});", $rows);
        $this->assertStringContainsString("row.addEventListener('focusin', (event) => reveal(row, itemOf(event.target)));", $rows);
        $this->assertStringNotContainsString('pointerdown', $rows);
        $this->assertStringContainsString(
            "scroller.scrollBy({left: dx, behavior: still() ? 'auto' : 'smooth'});",
            $this->body('reveal')
        );
        $fades = $this->body('fades');
        $this->assertStringContainsString('scroller.classList.toggle(CLASSES.fadeleft, max > 1 && left > 2);', $fades);
        $this->assertStringContainsString('scroller.classList.toggle(CLASSES.faderight, max > 1 && right > 2);', $fades);
        // The chips are the panel's tag filters in force, each released alone.
        $arm = $this->declared('filters', 'armLines');
        $this->assertStringContainsString('.filter((group) => !!state.activeFilters[group.field])', $arm);
    }

    /**
     * The slash key opens the search while the line is on screen and the focus is in no field.
     *
     * Changes that must make it fail: the key taken inside a field; taken while the line is off screen;
     * taken by a second line once the first has it; the field opened directly where it is folded.
     *
     * @return void
     */
    public function test_the_slash_key_opens_the_search(): void {
        $this->assertStringContainsString(
            "event.key === '/' && !event.ctrlKey && !event.metaKey && !event.altKey\n"
                . "                        && !event.defaultPrevented",
            $this->body('listenLayers')
        );
        $slash = $this->body('onSlash');
        $this->assertStringContainsString("active.closest('input, textarea, select')", $slash);
        $this->assertStringContainsString('if (rect.bottom <= 0 || rect.top >= window.innerHeight || !rect.width) {', $slash);
        $this->assertStringContainsString('event.preventDefault();', $slash, 'The next line sees the key taken.');
        $this->assertStringContainsString('openSearch(state.folded && !state.searching ? parts.opener : field);', $slash);
    }

    /**
     * Each section has its own line, its own search and its own star; the header search and the sliders toggle are gone.
     *
     * Changes that must make it fail: one search filtering both sections; a section's search dropping
     * the other section's star; the star toggled anywhere but on its own section; a line shared by the
     * two sections; the old pills, the header field or the mobile toggle back.
     *
     * @return void
     */
    public function test_each_section_has_its_own_line_search_and_star(): void {
        $state = $this->code('state');
        $this->assertStringContainsString("normalizedSearch: {plan: '', competency: ''},", $state);
        $this->assertStringContainsString('const term = state.normalizedSearch[type];', $this->declared('state', 'isCardVisible'));
        $run = $this->declared('filters', 'runSearch');
        $this->assertMatchesRegularExpression(
            '~if \(state\.normalizedSearch\[type\] && state\.normalizedSearch\[type\] !== before\[type\]\) \{\s*'
                . 'state\.favouriteFilterActive\[type\] = false;~',
            $run,
            'A section\'s new text drops that section\'s star alone.'
        );
        $this->assertStringContainsString('const needPlans = !!state.normalizedSearch.plan && searchNeedsPlans(state);', $run);
        $star = $this->declared('filters', 'pressStar');
        $this->assertStringContainsString(
            'state.favouriteFilterActive[favType] = !state.favouriteFilterActive[favType];',
            $star
        );
        $sync = $this->declared('filters', 'syncStar');
        $this->assertStringContainsString(
            "star.hidden = !state.favouritesEnabled || favCount <= 0 || (type === 'plan' && state.planStatus !== 'active');",
            $sync
        );
        $this->assertStringContainsString("star.setAttribute('aria-pressed', pressed ? 'true' : 'false');", $sync);
        $arm = $this->declared('filters', 'armLines');
        $this->assertStringContainsString('lines[type] = FilterLine.create(toolbar, {', $arm);
        $this->assertStringContainsString('LINES.set(container, lines);', $arm);
        $init = $this->declared('filters', 'init');
        $this->assertMatchesRegularExpression(
            '~armLines\(container, state, safeOptions\);\s*loadData\(container, state, safeOptions\);~',
            $init,
            'Armed before the first load can render.'
        );
        $code = $this->code('filters');
        foreach (['dims-fav-filter-btn', 'dims-all-filter-btn', 'dims-block-search-input', 'dims-filter-toggle-btn'] as $gone) {
            $this->assertStringNotContainsString($gone, $code);
        }
    }

    /**
     * The status pills are the scope: every bucket written, the ones that are not a choice hidden; the
     * keys skip a hidden pill.
     *
     * Changes that must make it fail: a bucket that is not a choice left out (the line would measure a
     * narrower scope than it can draw); a hidden pill reached by the arrow keys.
     *
     * @return void
     */
    public function test_the_scope_writes_every_bucket_and_hides_what_is_no_choice(): void {
        $pills = $this->declared('filters', 'renderStatusPills');
        $this->assertStringContainsString("+ (offered.indexOf(bucket) < 0 ? ' hidden' : '') + '>'", $pills);
        $this->assertStringContainsString('return offered.length >= 2;', $pills);
        $this->assertStringContainsString('scope.hidden = !offered;', $this->declared('filters', 'renderFilterControls'));
        $this->assertMatchesRegularExpression(
            "~querySelectorAll\('\.dims-filter-tab'\)\)\.filter\(function\(tab\) \{\s*return !tab\.hidden;~",
            $this->source('filter_tabs_nav')
        );
    }

    /**
     * The stylesheet draws the steps, the morph and the sheet the module writes.
     *
     * Changes that must make it fail: the measured line allowed to wrap; the folded field still drawn;
     * a control slid out left visible (and so in the focus order); the slide not gated on the reader's
     * motion preference; the sheet not fixed; the Filters word gone from the document instead of the
     * picture.
     *
     * @return void
     */
    public function test_the_stylesheet_draws_the_steps_morph_and_sheet(): void {
        $css = (string) preg_replace('~/\*.*?\*/~s', '', (string) file_get_contents($this->plugin_root() . '/styles.css'));
        $rule = function (string $selector) use ($css): string {
            $found = preg_match('~(?:^|\})\s*' . preg_quote($selector, '~') . '\s*\{([^}]*)\}~', $css, $matches);
            $this->assertSame(1, $found, "No rule for {$selector}.");
            return $matches[1];
        };
        $this->assertStringContainsString('flex-wrap: nowrap;', $rule('.block_dimensions .dims-line.dims-line--measuring'));
        $this->assertStringContainsString('display: none;', $rule('.block_dimensions .dims-line--folded > .dims-line-search'));
        $this->assertStringContainsString(
            'display: inline-flex;',
            $rule('.block_dimensions .dims-line.dims-line--folded > .dims-line-searchopen')
        );
        $slid = $rule(".block_dimensions .dims-line--searching.dims-line--s3 > .dims-line-slides,\n"
            . ".block_dimensions .dims-line--searching.dims-line--s3 > .dims-line-scope,\n"
            . '.block_dimensions .dims-line--searching.dims-line--s4 > .dims-line-slides');
        $this->assertStringContainsString('visibility: hidden;', $slid);
        $this->assertStringContainsString('max-width: 0;', $slid);
        $this->assertMatchesRegularExpression(
            '~@media \(prefers-reduced-motion: no-preference\) \{\s*\.block_dimensions \.dims-line\.dims-line--s3,~',
            $css,
            'The slide is for readers who welcome motion.'
        );
        $this->assertStringContainsString('position: fixed;', $rule('.block_dimensions .dims-line--s4 .dims-line-panel'));
        $label = $rule(".block_dimensions .dims-line--s2 .dims-line-label,\n.block_dimensions .dims-line--s3 .dims-line-label,\n"
            . '.block_dimensions .dims-line--s4 .dims-line-label');
        $this->assertStringContainsString('clip-path: inset(50%);', $label);
        $this->assertStringNotContainsString('display: none', $label);
        $gones = ['dims-search-row', 'dims-block-search', 'dims-filter-toggle-btn', 'dims-fav-filter-btn', 'dims-filters-open'];
        foreach ($gones as $gone) {
            $this->assertStringNotContainsString($gone, $css);
        }
    }

    /**
     * The committed build of every module the line touches was made from the committed source.
     *
     * Changes that must make it fail: an edit of amd/src left unbuilt; a module imported across plugins.
     *
     * @return void
     */
    public function test_the_builds_are_current(): void {
        foreach (['filter_line', 'filters', 'state', 'filter_tabs_nav'] as $module) {
            $map = $this->plugin_root() . '/amd/build/' . $module . '.min.js.map';
            $this->assertFileExists($map);
            $decoded = json_decode((string) file_get_contents($map), true);
            $this->assertSame(
                $this->source($module),
                (string) ($decoded['sourcesContent'][0] ?? ''),
                "amd/build/{$module} is not this source: rebuild it with grunt amd and commit the build."
            );
        }
        $this->assertStringStartsWith(
            "define(['core/pending'], function(Pending) {",
            (string) strstr($this->source(), "define(['core/pending']"),
            'It loads core\'s pending tracker and nothing else.'
        );
        $this->assertStringNotContainsString('local_dimensions/', $this->code());
    }
}
