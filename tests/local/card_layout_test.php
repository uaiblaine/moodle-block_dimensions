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

namespace block_dimensions\local;

/**
 * Layout and cascade invariants of styles.css.
 *
 * None is visible to phpcs, the mustache lint or stylelint: they read syntax, and what breaks
 * here is geometry, or a rule that is valid CSS and never takes effect.
 *
 * @package    block_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_dimensions\local\bootstrap
 */
final class card_layout_test extends \basic_testcase {
    /** @var string The custom property every card grid reads its track floor from. */
    private const TRACK_FLOOR = '--dims-card-track-min';

    /**
     * @var array Media feature => the values Media Queries Level 5 defines for it.
     *
     * A value outside the list is not an error to the browser, which evaluates the query as false,
     * so a block written against it never applies.
     */
    private const MEDIA_FEATURE_VALUES = [
        'prefers-contrast' => ['no-preference', 'more', 'less', 'custom'],
        'prefers-reduced-motion' => ['no-preference', 'reduce'],
        'prefers-color-scheme' => ['light', 'dark'],
        'forced-colors' => ['none', 'active'],
    ];

    /** @var string Pattern matching the preference at-rule preludes, whose rules override the base styles. */
    private const PREFERENCE_PRELUDE = '/prefers-contrast|prefers-reduced-motion:\s*reduce|forced-colors/';

    /** @var string Pattern matching the print at-rule prelude, whose rules override the base styles too. */
    private const PRINT_PRELUDE = '/(?<![\w-])print(?![\w-])/';

    /** @var string Pattern matching a user-action pseudo-class, which puts a selector in a state. */
    private const STATE_PSEUDO = '/:(?:hover|active|focus(?:-visible|-within)?)(?![\w-])/';

    /**
     * Read the plugin stylesheet with its comments blanked, line numbers preserved.
     *
     * Comments are removed before any rule matching, or a comment naming a selector such as
     * .dimension-tags is read as part of the selector of the rule below it.
     *
     * @return string
     */
    protected function styles(): string {
        $css = (string) file_get_contents(__DIR__ . '/../../styles.css');

        return (string) preg_replace_callback('~/\*.*?\*/~s', static function (array $m): string {
            return str_repeat("\n", substr_count($m[0], "\n"));
        }, $css);
    }

    /**
     * Every rule whose selector list mentions the given selector, in source order.
     *
     * @param string $selector Selector to look for, e.g. ".dimension-tags".
     * @return array Each entry is the rule's declaration block.
     */
    protected function rules_for(string $selector): array {
        $blocks = [];
        if (preg_match_all('/([^{}]+)\{([^{}]*)\}/', $this->styles(), $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                if (strpos($match[1], $selector) !== false) {
                    $blocks[] = $match[2];
                }
            }
        }

        return $blocks;
    }

    /**
     * Every style rule of the stylesheet, at-rule wrappers unwrapped, in source order.
     *
     * @return array List of arrays with keys line (where the selector starts), selector
     *               (whitespace collapsed), body and at (the enclosing at-rule preludes).
     */
    protected function flat_rules(): array {
        $css = $this->styles();
        $rules = [];
        $stack = [];
        $start = 0;
        $length = strlen($css);
        for ($i = 0; $i < $length; $i++) {
            if ($css[$i] === '{') {
                $prelude = substr($css, $start, $i - $start);
                $line = substr_count(substr($css, 0, $start), "\n") + substr_count($prelude, "\n")
                    - substr_count(ltrim($prelude), "\n") + 1;
                $stack[] = [trim(preg_replace('/\s+/', ' ', $prelude)), $i, $line];
                $start = $i + 1;
            } else if ($css[$i] === '}') {
                if ($stack) {
                    [$selector, $open, $line] = array_pop($stack);
                    $body = substr($css, $open + 1, $i - $open - 1);
                    if (!str_contains($body, '{')) {
                        $rules[] = [
                            'line' => $line,
                            'selector' => $selector,
                            'body' => $body,
                            'at' => implode(' ', array_column($stack, 0)),
                        ];
                    }
                }
                $start = $i + 1;
            }
        }

        return $rules;
    }

    /**
     * The declarations of one rule body.
     *
     * @param string $body The text between a rule's braces.
     * @return array Lower-case property name => value, whitespace collapsed.
     */
    protected function declarations(string $body): array {
        $found = [];
        foreach (explode(';', $body) as $declaration) {
            if (!str_contains($declaration, ':')) {
                continue;
            }
            [$property, $value] = explode(':', $declaration, 2);
            $property = strtolower(trim($property));
            if ($property !== '') {
                $found[$property] = trim(preg_replace('/\s+/', ' ', $value));
            }
        }

        return $found;
    }

    /**
     * The card grids lay out on auto-fill tracks, so a lone last card keeps its column width.
     *
     * With a flex row, the last card of an incomplete row grows to the full width, so one screen
     * shows the same card in two sizes. The item rule must not make the card a growing flex item
     * either.
     *
     * @return void
     */
    public function test_card_grids_use_auto_fill_tracks(): void {
        $gridrules = $this->rules_for('[data-cards-type]');
        $this->assertNotEmpty($gridrules);

        $container = null;
        $item = null;
        foreach ($gridrules as $block) {
            if (strpos($block, 'grid-template-columns') !== false && $container === null) {
                $container = $block;
            }
        }
        foreach ($this->rules_for('[data-cards-type] > li') as $block) {
            $item = $block;
        }

        $this->assertNotNull($container, 'the card list must declare its own tracks');
        $this->assertMatchesRegularExpression('/display:\s*grid/', $container);
        $this->assertMatchesRegularExpression('/repeat\(\s*auto-fill\s*,\s*minmax\(/', $container);

        $this->assertNotNull($item, 'the card item must neutralise the Bootstrap column widths');
        $this->assertDoesNotMatchRegularExpression(
            '/flex:\s*1\s+1/',
            $item,
            'a growing flex item stretches the last card of a row to the full width'
        );
    }

    /**
     * No card track is ever wider than the list that holds it.
     *
     * Every grid-template-columns in the file takes its minmax() floor from TRACK_FLOOR, and every
     * declaration of TRACK_FLOOR is min(<width>, 100%). The width sets the column count in a wide
     * region; the 100% cap keeps a single track inside a block narrower than that width (Boost's
     * block drawer, a phone), where a fixed floor overflows the block sideways. The rule that
     * reads the floor must also declare it, so the grid never reads an unset property.
     *
     * Changes that must make it fail: write minmax(360px, 1fr) into any grid rule; declare the
     * competency floor as a bare 360px; drop the floor from the grid rule that reads it.
     *
     * @return void
     */
    public function test_card_track_floor_never_exceeds_the_block(): void {
        $offenders = [];
        $grids = 0;
        $floors = 0;
        foreach ($this->flat_rules() as $rule) {
            $declarations = $this->declarations($rule['body']);
            $where = 'styles.css:' . $rule['line'] . ' (' . $rule['selector'] . ')';
            if (isset($declarations['grid-template-columns'])) {
                $grids++;
                $expected = 'repeat(auto-fill, minmax(var(' . self::TRACK_FLOOR . '), 1fr))';
                if ($declarations['grid-template-columns'] !== $expected) {
                    $offenders[] = $where . ' lays out ' . $declarations['grid-template-columns']
                        . ' instead of ' . $expected;
                }
                if (!isset($declarations[self::TRACK_FLOOR])) {
                    $offenders[] = $where . ' reads ' . self::TRACK_FLOOR . ' without declaring it';
                }
            }
            if (isset($declarations[self::TRACK_FLOOR])) {
                $floors++;
                if (!preg_match('/^min\(\s*[0-9.]+(?:px|rem|em)\s*,\s*100%\s*\)$/', $declarations[self::TRACK_FLOOR])) {
                    $offenders[] = $where . ' declares ' . self::TRACK_FLOOR . ': '
                        . $declarations[self::TRACK_FLOOR] . ', which is not capped at 100%';
                }
            }
        }

        $this->assertGreaterThan(0, $grids, 'No grid-template-columns was found, so this test checks nothing.');
        $this->assertGreaterThan(0, $floors, 'No track floor was found, so this test checks nothing.');
        $this->assertSame(
            [],
            $offenders,
            'A card track wider than the block overflows it: ' . implode('; ', $offenders)
        );
    }

    /**
     * Every preference media query asks for a value its feature defines.
     *
     * The browser reads an undefined value as false, so a block written against one is valid CSS
     * that never applies. prefers-contrast: high is the case this exists for: the feature's
     * raised-contrast value is more.
     *
     * Change that must make it fail: write (prefers-contrast: high) in any block.
     *
     * @return void
     */
    public function test_preference_queries_use_defined_values(): void {
        $offenders = [];
        $checked = [];
        preg_match_all('/@media\s*([^{]+)\{/', $this->styles(), $preludes);
        foreach ($preludes[1] as $prelude) {
            preg_match_all('/\(\s*([a-z-]+)\s*:\s*([a-z-]+)\s*\)/', $prelude, $features, PREG_SET_ORDER);
            foreach ($features as [, $feature, $value]) {
                if (!isset(self::MEDIA_FEATURE_VALUES[$feature])) {
                    continue;
                }
                $checked[$feature] = true;
                if (!in_array($value, self::MEDIA_FEATURE_VALUES[$feature], true)) {
                    $offenders[] = '(' . $feature . ': ' . $value . ')';
                }
            }
        }

        $this->assertArrayHasKey(
            'prefers-contrast',
            $checked,
            'No prefers-contrast query was found, so the stylesheet has no raised-contrast styles to check.'
        );
        $this->assertSame(
            [],
            $offenders,
            'These media queries name a value their feature does not define, so they never match: '
                . implode(', ', array_unique($offenders))
        );
    }

    /**
     * The style rules the cascade tests compare, keyframes left out.
     *
     * @return array flat_rules() entries with these keys added: order (source position),
     *               preference (inside a preference block), override (inside a preference or print
     *               block), parts (the selector list) and families (from families()).
     */
    protected function cascade_rules(): array {
        $rules = [];
        foreach ($this->flat_rules() as $order => $rule) {
            if (str_contains($rule['at'], '@keyframes')) {
                continue;
            }
            $rule['order'] = $order;
            $rule['preference'] = (bool) preg_match(self::PREFERENCE_PRELUDE, $rule['at']);
            $rule['override'] = $rule['preference'] || preg_match(self::PRINT_PRELUDE, $rule['at']);
            $rule['parts'] = array_map('trim', explode(',', $rule['selector']));
            $rule['families'] = $this->families($this->declarations($rule['body']));
            $rules[] = $rule;
        }

        return $rules;
    }

    /**
     * Whether one selector part beats another in the cascade.
     *
     * @param string $part The selector part that should win.
     * @param int $order Its rule's source position.
     * @param string $rivalpart The selector part it competes with.
     * @param int $rivalorder That rule's source position.
     * @return bool True when $part is more specific, or equally specific and later.
     */
    private function outranks(string $part, int $order, string $rivalpart, int $rivalorder): bool {
        $rank = $this->specificity($part) <=> $this->specificity($rivalpart);

        return $rank > 0 || ($rank === 0 && $order > $rivalorder);
    }

    /**
     * Whether the overlapping parts of one override rule win against a base selector part.
     *
     * A rival naming no simple selector the base part lacks, in any of its compounds, matches every
     * element the base part matches, so when such broad rivals exist one of them has to win. A
     * narrower rival matches only some of those elements, so it cannot settle the pair for the
     * rest; without a broad rival, every narrow one has to win on the elements it does match.
     *
     * @param array $rivals Parts of the override rule, from overlapping_parts().
     * @param int $order The override rule's source position.
     * @param string $basepart The base selector part.
     * @param int $baseorder The base rule's source position.
     * @return bool True when the override applies wherever it competes with the base part.
     */
    private function rivals_win(array $rivals, int $order, string $basepart, int $baseorder): bool {
        $basetokens = $this->simple_selectors($basepart);
        $broad = [];
        $narrow = [];
        foreach ($rivals as $part) {
            if (array_diff($this->simple_selectors($part), $basetokens)) {
                $narrow[] = $part;
            } else {
                $broad[] = $part;
            }
        }
        foreach ($broad as $part) {
            if ($this->outranks($part, $order, $basepart, $baseorder)) {
                return true;
            }
        }
        if ($broad) {
            return false;
        }
        foreach ($narrow as $part) {
            if (!$this->outranks($part, $order, $basepart, $baseorder)) {
                return false;
            }
        }

        return (bool) $narrow;
    }

    /**
     * A rule in a preference or print block wins over the base rule it overrides.
     *
     * A preference block (prefers-contrast, prefers-reduced-motion: reduce, forced-colors) or a
     * print block is usually written after the rule it overrides and relies on source order, which
     * only decides between equal specificities: a base rule written under an extra ancestor class
     * outranks it wherever it comes. The comparison pairs an override with every base rule
     * (keyframes aside) that sets the same property family to a different value on the same
     * subject compound (one set of simple selectors containing the other) in the same user-action
     * state, and reports each pair the base rule wins. Base rules in another state are left alone
     * here: a hover that changes a border is a design choice, not a lost override. A property
     * switched off to none is the exception, and test_switched_off_properties_stay_off_in_every_state()
     * covers it.
     *
     * Changes that must make it fail: write the raised-contrast tab border, or the reduced-motion
     * tab transition, as .block_dimensions .dims-filter-tab again; drop the hidden paddle from its
     * reduced-motion rule; move the plan card's reduced-motion rule, or the card shells'
     * raised-contrast or print block, above the plan card's own rules;
     * drop the .block_dimensions ancestor from the plain gradient parts of the print fill, which
     * the .has-custom-bg parts beside them must not be taken to cover.
     *
     * @return void
     */
    public function test_preference_overrides_are_not_outranked(): void {
        $rules = $this->cascade_rules();

        $offenders = [];
        $overrides = 0;
        $compared = 0;
        foreach ($rules as $override) {
            if (!$override['override']) {
                continue;
            }
            $overrides++;
            foreach ($rules as $base) {
                if ($base['override']) {
                    continue;
                }
                foreach ($override['families'] as $family => $values) {
                    if (!isset($base['families'][$family]) || $base['families'][$family] === $values) {
                        continue;
                    }
                    foreach ($base['parts'] as $basepart) {
                        $rivals = $this->overlapping_parts($override['parts'], $basepart);
                        if (!$rivals) {
                            continue;
                        }
                        $compared++;
                        if (!$this->rivals_win($rivals, $override['order'], $basepart, $base['order'])) {
                            $offenders[] = 'styles.css:' . $override['line'] . ' (' . implode(', ', $rivals) . ', '
                                . $override['at'] . ') loses ' . $family . ' to styles.css:' . $base['line']
                                . ' (' . $basepart . ')';
                        }
                    }
                }
            }
        }

        $this->assertGreaterThan(0, $overrides, 'No preference block was found, so this test checks nothing.');
        $this->assertGreaterThan(0, $compared, 'No override was compared with a base rule, so this test checks nothing.');
        sort($offenders);
        $this->assertSame(
            [],
            $offenders,
            'A preference override that loses on specificity never applies: ' . implode('; ', $offenders)
        );
    }

    /**
     * A property a preference block switches off stays off in every state.
     *
     * test_preference_overrides_are_not_outranked() compares rules in the same user-action state,
     * because a hover that restyles a control under a preference is usually a design choice.
     * Setting a property to none is not: the preference asks for the thing to be gone, and a hover
     * or focus rule that puts it back on the same element undoes the preference for as long as the
     * state lasts. So for every override family whose values are all none, each base rule that
     * sets the family on the same subject compound, in a state that includes the override's own,
     * must be answered by a rule in the same preference block and in the base rule's state that
     * sets the same none and wins the cascade. Print is left out, since a page is not hovered on
     * paper.
     *
     * Changes that must make it fail: delete the access pill's raised-contrast rule that follows
     * ACCESS PILL; move that rule into a reduced-motion block; drop any one of its six selectors.
     *
     * @return void
     */
    public function test_switched_off_properties_stay_off_in_every_state(): void {
        $rules = $this->cascade_rules();
        $offenders = [];
        $compared = 0;
        foreach ($rules as $override) {
            if (!$override['preference']) {
                continue;
            }
            foreach ($override['families'] as $family => $values) {
                if (array_diff($values, ['none'])) {
                    continue;
                }
                foreach ($override['parts'] as $part) {
                    [$state, $element, $tokens] = $this->subject($part);
                    foreach ($rules as $base) {
                        if ($base['override'] || !isset($base['families'][$family]) || $base['families'][$family] === $values) {
                            continue;
                        }
                        foreach ($base['parts'] as $basepart) {
                            [$basestate, $baseelement, $basetokens] = $this->subject($basepart);
                            if ($basestate === $state || $baseelement !== $element) {
                                continue;
                            }
                            if (array_diff($this->states($state), $this->states($basestate))) {
                                continue;
                            }
                            if (array_diff($tokens, $basetokens) && array_diff($basetokens, $tokens)) {
                                continue;
                            }
                            $compared++;
                            if (!$this->switched_off_in($rules, $override['at'], $family, $values, $base, $basepart)) {
                                $offenders[] = 'styles.css:' . $base['line'] . ' (' . $basepart . ') puts ' . $family
                                    . ' back on, which styles.css:' . $override['line'] . ' (' . $part . ', '
                                    . $override['at'] . ') switches off';
                            }
                        }
                    }
                }
            }
        }

        $this->assertGreaterThan(
            0,
            $compared,
            'No switched-off property met a base rule in another state, so this test checks nothing.'
        );
        $offenders = array_values(array_unique($offenders));
        sort($offenders);
        $this->assertSame(
            [],
            $offenders,
            'A preference that switches a property off must keep it off while the element is hovered or '
                . 'focused: ' . implode('; ', $offenders)
        );
    }

    /**
     * Whether a preference block switches a property off again in a base rule's own state.
     *
     * The answering part has to name no simple selector, in any compound, that the base part does
     * not: .plan-card:hover .dims-card-access-btn shares its subject with the competency card's
     * hover part but reaches none of the elements that part styles.
     *
     * @param array $rules Entries from cascade_rules().
     * @param string $at The preference block the switch-off belongs to.
     * @param string $family The property family switched off.
     * @param array $values The switched-off values, property => none.
     * @param array $base The base rule that sets the family, from cascade_rules().
     * @param string $basepart The selector part of that rule.
     * @return bool True when a rule of that block, in that state, sets the same values on every
     *              element the base part matches and wins.
     */
    private function switched_off_in(
        array $rules,
        string $at,
        string $family,
        array $values,
        array $base,
        string $basepart
    ): bool {
        [$basestate, $baseelement] = $this->subject($basepart);
        $basetokens = $this->simple_selectors($basepart);
        foreach ($rules as $answer) {
            if (!$answer['preference'] || $answer['at'] !== $at || ($answer['families'][$family] ?? null) !== $values) {
                continue;
            }
            foreach ($answer['parts'] as $part) {
                [$state, $element] = $this->subject($part);
                if ($state !== $basestate || $element !== $baseelement) {
                    continue;
                }
                if (array_diff($this->simple_selectors($part), $basetokens)) {
                    continue;
                }
                if ($this->outranks($part, $answer['order'], $basepart, $base['order'])) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Every simple selector of a selector part, in all of its compounds.
     *
     * User-action pseudo-classes and the pseudo-element are left out, as subject() leaves them out
     * of the subject compound.
     *
     * @param string $part One selector part.
     * @return array The distinct simple selectors, sorted.
     */
    private function simple_selectors(string $part): array {
        $tokens = [];
        foreach (preg_split('/\s*[>+~]\s*|\s+/', preg_replace('/\s+/', ' ', trim($part))) as $compound) {
            $compound = (string) preg_replace([self::STATE_PSEUDO, '/::[\w-]+/'], '', $compound);
            preg_match_all('/[a-z][\w-]*|[#.][\w-]+|\[[^\]]*\]|:[\w-]+(?:\([^()]*\))?/i', $compound, $simple);
            $tokens = array_merge($tokens, $simple[0]);
        }
        $tokens = array_values(array_unique($tokens));
        sort($tokens);

        return $tokens;
    }

    /**
     * The user-action pseudo-classes of a state as subject() returns it.
     *
     * @param string $state The concatenated pseudo-classes, e.g. ":focus-within:hover".
     * @return array One entry per pseudo-class.
     */
    private function states(string $state): array {
        preg_match_all(self::STATE_PSEUDO, $state, $matches);

        return $matches[0];
    }

    /**
     * The competency card and the plan card take the same preference and print treatment.
     *
     * They are one component in two layouts, and a remedy written for one shell is easily left off
     * the other. For every selector part in a preference or print block that names one card's
     * classes, the same part written for the other card must appear in a rule of the same block
     * with the same declarations. Parts naming the horizontal plan card are left alone, since the
     * competency card has no horizontal layout.
     *
     * Changes that must make it fail: drop .block_dimensions .plan-card from the raised-contrast
     * border rule; drop the plan card's title from the raised-contrast title rule; drop the plan
     * card's gradient from the print fill; drop the competency card's hover part from the access
     * pill's raised-contrast rule.
     *
     * @return void
     */
    public function test_card_shells_share_their_preference_treatment(): void {
        $swap = ['competency-card' => 'plan-card', 'plan-card' => 'competency-card'];
        $written = [];
        $parts = [];
        foreach ($this->cascade_rules() as $rule) {
            if (!$rule['override']) {
                continue;
            }
            $declarations = $this->declarations($rule['body']);
            ksort($declarations);
            $key = $rule['at'] . ' ' . json_encode($declarations);
            foreach ($rule['parts'] as $part) {
                $written[$key . ' ' . $part] = true;
                if (preg_match('/(?:competency|plan)-card/', $part) && !str_contains($part, 'horizontal')) {
                    $parts[] = [$rule['line'], $rule['at'], $key, $part];
                }
            }
        }

        $offenders = [];
        foreach ($parts as [$line, $at, $key, $part]) {
            $twin = strtr($part, $swap);
            if (!isset($written[$key . ' ' . $twin])) {
                $offenders[] = 'styles.css:' . $line . ' (' . $part . ', ' . $at . ') has no ' . $twin;
            }
        }

        $this->assertNotEmpty($parts, 'No preference or print rule names a card shell, so this test checks nothing.');
        $this->assertSame(
            [],
            $offenders,
            'The two card shells must be treated alike under every preference and in print: '
                . implode('; ', $offenders)
        );
    }

    /**
     * The pill classes amd/src/filters.js draws with a count badge.
     *
     * @return array Class names without the leading dot, e.g. dims-status-filter-btn.
     */
    private function badge_pills(): array {
        $js = (string) file_get_contents(__DIR__ . '/../../amd/src/filters.js');
        preg_match_all('/class="dims-filter-tab (dims-[a-z]+-filter-btn)(?![\w-])/', $js, $matches);
        $pills = array_values(array_unique($matches[1]));
        $this->assertNotEmpty($pills, 'No pill class was found in amd/src/filters.js, so this test checks nothing.');

        return $pills;
    }

    /**
     * The background a top-level rule with exactly this selector paints.
     *
     * @param string $selector The whole selector of the rule, whitespace collapsed.
     * @return string|null The background value, or null when no such rule paints one.
     */
    private function ground(string $selector): ?string {
        $fill = null;
        foreach ($this->flat_rules() as $rule) {
            if ($rule['at'] === '' && $rule['selector'] === $selector) {
                $declarations = $this->declarations($rule['body']);
                $fill = $declarations['background-color'] ?? $declarations['background'] ?? $fill;
            }
        }

        return $fill;
    }

    /**
     * The fill and border that win the cascade on one pill's count badge in one state.
     *
     * A selector part applies to the badge when it ends in .dims-filter-count, is in no user-action
     * state, every class and attribute outside :not() belongs to the badge, to the pill in that
     * state (.dims-filter-tab, the pill class, role="radio", and .active with aria-checked="true"
     * when checked or aria-checked="false" when not) or to the block's own containers, and no
     * :not() excludes something the badge or the pill carries. The border is resolved per
     * component, so a longhand in a rule that wins overrides that part of the shorthand, and a
     * shorthand resets whatever it leaves out.
     *
     * @param string $pill The pill class, without the leading dot.
     * @param bool $checked True for a checked pill, false for an unchecked one.
     * @return array With keys fill, style, width and color, each the winning value or null.
     */
    private function winning_badge_style(string $pill, bool $checked): array {
        $carried = ['.dims-filter-count', '.dims-filter-tab', '.' . $pill, '[role="radio"]'];
        $carried = array_merge($carried, $checked ? ['.active', '[aria-checked="true"]'] : ['[aria-checked="false"]']);
        $containers = [
            '.block_dimensions', '.block-dimensions-content', '.dims-filter-tabs', '.dims-filter-tabs-mask',
            '.dims-filter-tabs-items',
        ];
        $allowed = array_merge($carried, $containers);
        $winners = [];
        foreach ($this->flat_rules() as $order => $rule) {
            if ($rule['at'] !== '') {
                continue;
            }
            foreach (array_map('trim', explode(',', $rule['selector'])) as $part) {
                if (preg_match(self::STATE_PSEUDO, $part) || !preg_match('/\.dims-filter-count$/', $part)) {
                    continue;
                }
                preg_match_all('/:not\(([^()]*)\)/', $part, $negations);
                preg_match_all('/[#.][\w-]+|\[[^\]]*\]/', implode(' ', $negations[1]), $negated);
                preg_match_all('/[#.][\w-]+|\[[^\]]*\]/', (string) preg_replace('/:not\([^()]*\)/', '', $part), $simple);
                if (array_diff($simple[0], $allowed) || array_intersect($negated[0], $carried)) {
                    continue;
                }
                foreach ($this->declarations($rule['body']) as $property => $value) {
                    foreach ($this->badge_components($property, $value) as $component => $componentvalue) {
                        $current = $winners[$component] ?? null;
                        $samerule = $current !== null && $current[1] === $part && $current[2] === $order;
                        if ($current === null || $samerule || $this->outranks($part, $order, $current[1], $current[2])) {
                            $winners[$component] = [$componentvalue, $part, $order];
                        }
                    }
                }
            }
        }
        $style = [];
        foreach (['fill', 'style', 'width', 'color'] as $component) {
            $style[$component] = $winners[$component][0] ?? null;
        }

        return $style;
    }

    /**
     * The badge components one declaration sets.
     *
     * @param string $property Lower-case property name.
     * @param string $value Its value.
     * @return array Component (fill, style, width or color) => value. A border shorthand sets all
     *               three border components, each it leaves out at its initial value.
     */
    private function badge_components(string $property, string $value): array {
        if ($property === 'background' || $property === 'background-color') {
            return ['fill' => $value];
        }
        if (in_array($property, ['border-style', 'border-width', 'border-color'], true)) {
            return [substr($property, 7) => $value];
        }
        if ($property !== 'border') {
            return [];
        }
        $components = ['style' => 'none', 'width' => 'medium', 'color' => 'currentcolor'];
        preg_match_all('/[^\s(]+(?:\([^()]*\))?/', $value, $words);
        foreach ($words[0] as $word) {
            if (preg_match('/^(?:none|hidden|dotted|dashed|solid|double|groove|ridge|inset|outset)$/i', $word)) {
                $components['style'] = $word;
            } else if (preg_match('/^(?:[0-9.]|thin$|medium$|thick$)/i', $word)) {
                $components['width'] = $word;
            } else {
                $components['color'] = $word;
            }
        }

        return $components;
    }

    /**
     * Why a badge's winning style gives it no shape on its ground, if it does not.
     *
     * @param array $style From winning_badge_style().
     * @param string $ground The ground's background value.
     * @return array Messages, empty when the fill differs from the ground and the border draws a
     *               line in a plugin token other than the ground's.
     */
    private function shapeless(array $style, string $ground): array {
        $problems = [];
        if ($style['fill'] === null || $style['fill'] === $ground) {
            $problems[] = 'fill ' . ($style['fill'] ?? 'not set');
        }
        $drawn = $style['style'] !== null
            && !preg_match('/^(?:none|hidden)$/i', $style['style'])
            && !preg_match('/^0(?:[a-z]*)$/i', (string) $style['width'])
            && preg_match('/^var\(--block-dimensions-[a-z0-9-]+\)$/', (string) $style['color'])
            && $style['color'] !== $ground;
        if (!$drawn) {
            $problems[] = 'border ' . implode(' ', [$style['style'] ?? 'none', $style['width'] ?? '-', $style['color'] ?? '-']);
        }

        return $problems;
    }

    /**
     * A checked pill's count badge keeps a shape against the indicator it sits on.
     *
     * A checked pill with no fill of its own sits on the sliding indicator, which paints what the
     * badge paints at rest, so a badge keeping its rest fill there has no shape. For every pill
     * class amd/src/filters.js draws, the badge fill that wins the cascade while the pill is checked
     * must differ from the indicator's. The fill differs only faintly (brand-tint on surface), so
     * the border that wins must also draw a line, in a plugin token other than the indicator's
     * fill; colour_tokens_test measures that token. winning_badge_style() says which rules apply.
     *
     * Changes that must make it fail: narrow the checked-badge rule to the favourite and show-all
     * pills again; give it the surface token; delete it; delete its border together with the
     * resting badge's; draw its border in the surface token; give the status pill's checked badge
     * border: none, or border-style: none, in a rule of its own. Deleting only its border does not:
     * the resting badge's border then edges it, in a token colour_tokens_test pins against the
     * indicator too.
     *
     * @return void
     */
    public function test_checked_pill_badge_stands_off_the_indicator(): void {
        $indicator = $this->ground('.block_dimensions .dims-filter-tabs-indicator');
        $this->assertNotNull($indicator, 'The indicator paints no background, so there is nothing to stand off from.');

        $offenders = [];
        foreach ($this->badge_pills() as $pill) {
            foreach ($this->shapeless($this->winning_badge_style($pill, true), $indicator) as $problem) {
                $offenders[] = $pill . ' (' . $problem . ')';
            }
        }

        $this->assertSame(
            [],
            $offenders,
            'A checked pill\'s count badge must not take the indicator\'s fill (' . $indicator . '), and must be '
                . 'edged in a plugin token other than it, or it has almost no shape: ' . implode('; ', $offenders)
        );
    }

    /**
     * An unchecked pill's count badge keeps a shape against the platter it sits on.
     *
     * An unchecked pill paints no fill, so its badge sits on the filter platter. The badge's
     * surface fill differs from the platter's surface-inset by 1.19:1 in light and 1.41:1 in dark,
     * so, as on a checked pill, the border that wins must draw a line in a plugin token other than
     * the platter's fill; colour_tokens_test measures that token against the platter.
     *
     * Changes that must make it fail: delete the resting badge's border; draw it in the
     * surface-inset token; give the fill surface-inset; give an unchecked status pill's badge
     * border-style: none in a rule of its own.
     *
     * @return void
     */
    public function test_resting_pill_badge_stands_off_the_platter(): void {
        $platter = $this->ground('.block_dimensions .block-dimensions-content .dims-filter-tabs');
        $this->assertNotNull($platter, 'The platter paints no background, so there is nothing to stand off from.');

        $offenders = [];
        foreach ($this->badge_pills() as $pill) {
            foreach ($this->shapeless($this->winning_badge_style($pill, false), $platter) as $problem) {
                $offenders[] = $pill . ' (' . $problem . ')';
            }
        }

        $this->assertSame(
            [],
            $offenders,
            'An unchecked pill\'s count badge must not take the platter\'s fill (' . $platter . '), and must be '
                . 'edged in a plugin token other than it, or it has almost no shape: ' . implode('; ', $offenders)
        );
    }

    /**
     * Every transform in the stylesheet is switched off under prefers-reduced-motion: reduce.
     *
     * The hover and press transforms are motion started by interaction (WCAG 2.3.3). Each selector
     * part of a rule that sets a transform other than none needs a reset: a part of a rule in a
     * prefers-reduced-motion: reduce block that sets transform: none in the same user-action state,
     * whose subject compound names no simple selector the part does not, and which wins the cascade
     * against the part. Print, keyframes and prefers-reduced-motion: no-preference blocks are
     * skipped, since none of them applies when reduced motion is asked for.
     *
     * Changes that must make it fail: delete the plan card's reduced-motion rule; move it above the
     * plan card's own rules; drop .dims-fav-btn:active from the favourite star's reset; drop the
     * clickable trail marker from its reduced-motion rule.
     *
     * @return void
     */
    public function test_every_transform_has_a_reduced_motion_reset(): void {
        $moving = [];
        $resets = [];
        foreach ($this->flat_rules() as $order => $rule) {
            if (preg_match('/print|@keyframes|prefers-reduced-motion:\s*no-preference/', $rule['at'])) {
                continue;
            }
            $transform = $this->declarations($rule['body'])['transform'] ?? null;
            if ($transform === null) {
                continue;
            }
            $isreset = (bool) preg_match('/prefers-reduced-motion:\s*reduce/', $rule['at']);
            foreach (array_map('trim', explode(',', $rule['selector'])) as $part) {
                $entry = ['order' => $order, 'line' => $rule['line'], 'part' => $part];
                if ($isreset && $transform === 'none') {
                    $resets[] = $entry;
                } else if (!$isreset && $transform !== 'none') {
                    $moving[] = $entry;
                }
            }
        }

        $offenders = [];
        foreach ($moving as $base) {
            [$state, $element, $tokens] = $this->subject($base['part']);
            $reset = false;
            foreach ($resets as $candidate) {
                [$resetstate, $resetelement, $resettokens] = $this->subject($candidate['part']);
                if ($resetstate !== $state || $resetelement !== $element || array_diff($resettokens, $tokens)) {
                    continue;
                }
                $rank = $this->specificity($candidate['part']) <=> $this->specificity($base['part']);
                if ($rank > 0 || ($rank === 0 && $candidate['order'] > $base['order'])) {
                    $reset = true;
                }
            }
            if (!$reset) {
                $offenders[] = 'styles.css:' . $base['line'] . ' (' . $base['part'] . ')';
            }
        }

        $this->assertNotEmpty($moving, 'No transform was found, so this test checks nothing.');
        $this->assertSame(
            [],
            $offenders,
            'These transforms still move for a user who asked for reduced motion, because no reset '
                . 'of theirs wins the cascade: ' . implode('; ', $offenders)
        );
    }

    /**
     * Group declarations by the shorthand family their property belongs to.
     *
     * border-color and border both set the border colour, so they compete; border-radius is not
     * part of the border shorthand and stands alone.
     *
     * @param array $declarations Property => value, from declarations().
     * @return array Family => (property => value), properties sorted.
     */
    private function families(array $declarations): array {
        $families = [];
        foreach ($declarations as $property => $value) {
            $family = $property;
            if (preg_match('/^(border|outline|background|transition|animation)(?:-|$)/', $property, $m)) {
                $family = $m[1];
            }
            if (in_array($property, ['border-radius', 'border-collapse', 'border-spacing', 'outline-offset'], true)) {
                $family = $property;
            }
            $families[$family][$property] = $value;
        }
        foreach ($families as $family => $values) {
            ksort($values);
            $families[$family] = $values;
        }

        return $families;
    }

    /**
     * The parts of an override selector that target the same elements as a base selector part.
     *
     * Two parts overlap when they are in the same user-action state, name the same pseudo-element,
     * the simple selectors of one subject compound include those of the other, and so do the simple
     * selectors of the whole parts: then every element the longer part matches, the shorter one
     * matches too. Parts that each name something the other lacks (.a button and button.b) may never
     * meet on one element, and nothing here can tell, so they are not compared. A universal subject
     * (.x *) names no simple selector, so it would include every other; its ancestors alone decide
     * what it matches, so it is left out too.
     *
     * @param array $parts The override rule's selector parts.
     * @param string $basepart One selector part of the base rule.
     * @return array The overlapping override parts.
     */
    private function overlapping_parts(array $parts, string $basepart): array {
        [$basestate, $baseelement, $basetokens] = $this->subject($basepart);
        $basefull = $this->simple_selectors($basepart);
        $overlapping = [];
        foreach ($parts as $part) {
            [$state, $element, $tokens] = $this->subject($part);
            if ($state !== $basestate || $element !== $baseelement || !$tokens || !$basetokens) {
                continue;
            }
            $full = $this->simple_selectors($part);
            $subjects = !array_diff($tokens, $basetokens) || !array_diff($basetokens, $tokens);
            $wholes = !array_diff($full, $basefull) || !array_diff($basefull, $full);
            if ($subjects && $wholes) {
                $overlapping[] = $part;
            }
        }

        return $overlapping;
    }

    /**
     * Describe the element a selector part targets.
     *
     * @param string $part One selector part, e.g. ".block_dimensions .dims-filter-tab:hover".
     * @return array [state, pseudo-element, simple selectors]: the sorted user-action pseudo-classes
     *               anywhere in the part, the subject's pseudo-element (or ''), and the sorted
     *               simple selectors of the subject compound with both of those removed.
     */
    private function subject(string $part): array {
        preg_match_all(self::STATE_PSEUDO, $part, $states);
        $state = array_unique($states[0]);
        sort($state);
        $compound = preg_split('/\s*[>+~]\s*|\s+/', preg_replace('/\s+/', ' ', trim($part)));
        $compound = (string) end($compound);
        $compound = (string) preg_replace(self::STATE_PSEUDO, '', $compound);
        $element = preg_match('/::[\w-]+/', $compound, $m) ? $m[0] : '';
        $compound = str_replace($element, '', $compound);
        preg_match_all('/[a-z][\w-]*|[#.][\w-]+|\[[^\]]*\]|:[\w-]+(?:\([^()]*\))?/i', $compound, $simple);
        $tokens = array_values(array_unique($simple[0]));
        sort($tokens);

        return [implode('', $state), $element, $tokens];
    }

    /**
     * Selector specificity, for the selectors this stylesheet writes.
     *
     * :not(), :is() and :has() count their argument, :where() counts nothing, and an attribute
     * selector counts as a class.
     *
     * @param string $part One selector part.
     * @return array [ids, classes, types].
     */
    private function specificity(string $part): array {
        $ids = 0;
        $classes = 0;
        $types = 0;
        while (preg_match('/:(?:not|is|has)\(([^()]*)\)/', $part, $m, PREG_OFFSET_CAPTURE)) {
            [$innerids, $innerclasses, $innertypes] = $this->specificity($m[1][0]);
            $ids += $innerids;
            $classes += $innerclasses;
            $types += $innertypes;
            $part = substr_replace($part, ' ', $m[0][1], strlen($m[0][0]));
        }
        $part = (string) preg_replace('/:where\([^()]*\)/', ' ', $part);
        $classes += preg_match_all('/\[[^\]]*\]/', $part);
        $part = (string) preg_replace('/\[[^\]]*\]/', ' ', $part);
        $ids += preg_match_all('/#[\w-]+/', $part);
        $classes += preg_match_all('/\.[\w-]+/', $part);
        $classes += preg_match_all('/(?<!:):[\w-]+/', $part);
        $types += preg_match_all('/::[\w-]+/', $part);
        $types += preg_match_all('/(?:^|[\s>+~])[a-z][\w-]*/i', $part);

        return [$ids, $classes, $types];
    }

    /**
     * The tag strip stays absolutely positioned over the card image.
     *
     * A rule lifting it above the stretched-link overlay must set only its stacking: re-declaring
     * `position: relative` puts the strip back in the flow, where the image wrapper's
     * overflow: hidden cuts it in half on the horizontal card.
     *
     * @return void
     */
    public function test_tag_strip_stays_absolutely_positioned(): void {
        $positions = [];
        foreach ($this->rules_for('.dimension-tags') as $block) {
            if (preg_match('/(?<![-\w])position:\s*([a-z]+)/', $block, $match)) {
                $positions[] = $match[1];
            }
        }

        $this->assertNotEmpty($positions, 'the strip must declare a position somewhere');
        $this->assertSame(
            'absolute',
            end($positions),
            'the last position declaration wins, and the strip must stay over the image'
        );
        $this->assertSame(
            ['absolute'],
            array_values(array_unique($positions)),
            'a second position on the strip is how it was knocked back into the flow'
        );
    }
}
