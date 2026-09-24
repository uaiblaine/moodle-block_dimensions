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
 * Build gate for the colour-token contract declared at the head of styles.css.
 *
 * phpcs, phpdoc, the mustache lint and stylelint check syntax, not colour roles, so each rule of
 * the contract is a test here: the exact token declarations and their parity with
 * local_dimensions, the dark activation rule and what it may assign, contrast floors in every
 * resolution, focus indicators and the admin-colour islands. Each test lists the changes that
 * must make it fail.
 *
 * @package    block_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_dimensions\local\colour_mode
 */
final class colour_tokens_test extends \basic_testcase {
    /** @var string This plugin's token namespace. The sibling's differs only in the frankenstyle part. */
    private const PREFIX = '--block-dimensions-';

    /** @var string The sentinel both plugins' prefixes are rewritten to before the blocks are compared. */
    private const SENTINEL = '--DIMENSIONS-';

    /** @var string The plugin whose token block must declare what this one does, prefix aside. */
    private const SIBLING = 'local_dimensions';

    /** @var string That sibling's own token namespace. */
    private const SIBLING_PREFIX = '--local-dimensions-';

    /**
     * @var array The 34 token suffixes, in declaration order.
     *
     * Kept identical to the list in local_dimensions' copy of this test. Checking against it needs
     * no sibling installed, so a suffix renamed in one plugin fails that plugin's own build.
     */
    private const SUFFIXES = [
        'surface', 'surface-alt', 'surface-inset', 'line',
        'ink', 'ink-muted', 'ink-faint', 'ink-strong',
        'accent', 'accent-hover',
        'brand-ink', 'brand-tint', 'brand-edge',
        'success-ink', 'success-tint', 'success-edge',
        'warning-ink', 'warning-tint', 'warning-edge',
        'danger-ink', 'danger-tint', 'danger-edge',
        'info-ink', 'info-tint', 'info-edge',
        'neutral-ink', 'neutral-tint', 'neutral-edge',
        'brand-fill', 'on-brand-fill',
        'focus-ring', 'shadow', 'scrim', 'favourite',
    ];

    /**
     * @var array Suffix => the exact declaration text the token block must carry.
     *
     * Compared by string equality; test_token_block_declares_exactly_the_contract() says why.
     */
    private const LIGHT = [
        'surface' => 'var(--bs-body-bg, var(--white, #fff))',
        'surface-alt' => 'var(--bs-tertiary-bg, var(--light, #f8f9fa))',
        'surface-inset' => 'var(--bs-secondary-bg, #e9ecef)',
        'line' => 'var(--bs-border-color, #dee2e6)',
        'ink' => 'var(--bs-body-color, #1d2125)',
        'ink-muted' => 'var(--bs-secondary-color, #495057)',
        'ink-faint' => 'var(--bs-tertiary-color, #6a737b)',
        'ink-strong' => 'var(--bs-emphasis-color, var(--gray-dark, #343a40))',
        'accent' => 'var(--bs-link-color, var(--primary, #0f6cbf))',
        'accent-hover' => 'var(--bs-link-hover-color, #0c5699)',
        'brand-ink' => 'var(--bs-primary-text-emphasis, #062b4c)',
        'brand-tint' => 'var(--bs-primary-bg-subtle, #cfe2f2)',
        'brand-edge' => 'var(--bs-primary-border-subtle, #9fc4e5)',
        'success-ink' => 'var(--bs-success-text-emphasis, #153114)',
        'success-tint' => 'var(--bs-success-bg-subtle, #d7e4d6)',
        'success-edge' => 'var(--bs-success-border-subtle, #aecaad)',
        'warning-ink' => 'var(--bs-warning-text-emphasis, #60451f)',
        'warning-tint' => 'var(--bs-warning-bg-subtle, #fcefdc)',
        'warning-edge' => 'var(--bs-warning-border-subtle, #f9deb8)',
        'danger-ink' => 'var(--bs-danger-text-emphasis, #51140d)',
        'danger-tint' => 'var(--bs-danger-bg-subtle, #f4d6d2)',
        'danger-edge' => 'var(--bs-danger-border-subtle, #eaada6)',
        'info-ink' => 'var(--bs-info-text-emphasis, #00343c)',
        'info-tint' => 'var(--bs-info-bg-subtle, #cce6ea)',
        'info-edge' => 'var(--bs-info-border-subtle, #99cdd5)',
        'neutral-ink' => 'var(--bs-secondary-text-emphasis, #525557)',
        'neutral-tint' => 'var(--bs-secondary-bg-subtle, #f5f6f8)',
        'neutral-edge' => 'var(--bs-secondary-border-subtle, #ebeef0)',
        'brand-fill' => 'var(--bs-primary, var(--primary, #0f6cbf))',
        'on-brand-fill' => '#fff',
        'focus-ring' => 'var(--bs-emphasis-color, var(--gray-dark, #343a40))',
        'shadow' => 'rgb(0 0 0 / 10%)',
        'scrim' => 'rgb(255 255 255 / 72%)',
        'favourite' => '#e8590c',
    ];

    /** @var array The only three tokens the mode layer may assign, and nothing else may join them. */
    private const DARK_OWNED = ['shadow', 'scrim', 'favourite'];

    /**
     * @var string The dark activation selector, both arms, exactly as the stylesheet writes it.
     *
     * Two arms because hosts write data-bs-theme in two places: core on the html element,
     * theme_moove on document.body. Both arms have body, the element the token block is declared
     * on, as their subject. A bare [data-bs-theme="dark"] would also match below any inner element
     * carrying the attribute, such as a dark navbar; with body as the subject the only possible
     * ancestor is html.
     */
    private const DARK_ACTIVATION_SELECTOR = 'body[' . colour_mode::HOST_ATTRIBUTE . '="'
        . colour_mode::DARK . '"], [' . colour_mode::HOST_ATTRIBUTE . '="'
        . colour_mode::DARK . '"] body';

    /**
     * @var array Core's own --bs-* values on the light page.
     *
     * Copied from the ':root, [data-bs-theme="light"]' block of Moodle 5.2's compiled Boost
     * stylesheet.
     */
    private const CORE_LIGHT = [
        '--bs-body-bg' => '#ffffff',
        '--bs-tertiary-bg' => '#f8f9fa',
        '--bs-secondary-bg' => '#e9ecef',
        '--bs-border-color' => '#dee2e6',
        '--bs-body-color' => '#1d2125',
        '--bs-secondary-color' => 'rgba(29, 33, 37, 0.75)',
        '--bs-tertiary-color' => 'rgba(29, 33, 37, 0.5)',
        '--bs-emphasis-color' => '#000000',
        '--bs-link-color' => '#0f6cbf',
        '--bs-link-hover-color' => '#0c5699',
        '--bs-focus-ring-color' => 'rgba(15, 108, 191, 0.25)',
        '--bs-primary' => '#0f6cbf',
        '--bs-primary-text-emphasis' => '#062b4c',
        '--bs-primary-bg-subtle' => '#cfe2f2',
        '--bs-primary-border-subtle' => '#9fc4e5',
        '--bs-success-text-emphasis' => '#153114',
        '--bs-success-bg-subtle' => '#d7e4d6',
        '--bs-success-border-subtle' => '#aecaad',
        '--bs-warning-text-emphasis' => '#60451f',
        '--bs-warning-bg-subtle' => '#fcefdc',
        '--bs-warning-border-subtle' => '#f9deb8',
        '--bs-danger-text-emphasis' => '#51140d',
        '--bs-danger-bg-subtle' => '#f4d6d2',
        '--bs-danger-border-subtle' => '#eaada6',
        '--bs-info-text-emphasis' => '#00343c',
        '--bs-info-bg-subtle' => '#cce6ea',
        '--bs-info-border-subtle' => '#99cdd5',
        '--bs-secondary-text-emphasis' => '#525557',
        '--bs-secondary-bg-subtle' => '#f5f6f8',
        '--bs-secondary-border-subtle' => '#ebeef0',
    ];

    /**
     * @var array Core's own --bs-* values on the dark page, from the same stylesheet.
     *
     * A name absent here is not redefined under [data-bs-theme="dark"], so its light value stands.
     * --bs-primary is one of them, which is why the brand is used only as a solid fill under
     * on-brand-fill and never as an ink. --bs-focus-ring-color is not redefined either and is
     * listed with its light value; that is why the plugin's ring chains --bs-emphasis-color.
     */
    private const CORE_DARK = [
        '--bs-body-bg' => '#1d2125',
        '--bs-tertiary-bg' => '#292e33',
        '--bs-secondary-bg' => '#343a40',
        '--bs-border-color' => '#495057',
        '--bs-body-color' => '#dee2e6',
        '--bs-secondary-color' => 'rgba(222, 226, 230, 0.75)',
        '--bs-tertiary-color' => 'rgba(222, 226, 230, 0.5)',
        '--bs-emphasis-color' => '#ffffff',
        '--bs-link-color' => '#6fa7d9',
        '--bs-link-hover-color' => '#8cb9e1',
        '--bs-focus-ring-color' => 'rgba(15, 108, 191, 0.25)',
        '--bs-primary-text-emphasis' => '#6fa7d9',
        '--bs-primary-bg-subtle' => '#031626',
        '--bs-primary-border-subtle' => '#094173',
        '--bs-success-text-emphasis' => '#86af84',
        '--bs-success-bg-subtle' => '#0b180a',
        '--bs-success-border-subtle' => '#20491e',
        '--bs-warning-text-emphasis' => '#f6ce95',
        '--bs-warning-bg-subtle' => '#302310',
        '--bs-warning-border-subtle' => '#90682f',
        '--bs-danger-text-emphasis' => '#df8379',
        '--bs-danger-bg-subtle' => '#280a06',
        '--bs-danger-border-subtle' => '#791d13',
        '--bs-info-text-emphasis' => '#66b3c0',
        '--bs-info-bg-subtle' => '#001a1e',
        '--bs-info-border-subtle' => '#004d5a',
        '--bs-secondary-text-emphasis' => '#e2e5e9',
        '--bs-secondary-bg-subtle' => '#292a2c',
        '--bs-secondary-border-subtle' => '#7c7f83',
    ];

    /**
     * @var array The Bootstrap 4 legacy names Moodle 4.5's Boost declares on :root.
     *
     * Moodle 4.5 declares no --bs-* name, so there a chain var(--bs-NEW, var(--BS4-OLD, #literal))
     * takes the value of its Bootstrap 4 rung when that name is listed here, and its literal only
     * otherwise. The bs4 resolution in resolve() follows the same rule, so the 4.5 contrast check
     * measures what the browser renders even if a literal drifts from core's 4.5 value. Today
     * every literal equals the value of the Bootstrap 4 rung it backs up.
     */
    private const CORE_BS4 = [
        '--white' => '#fff',
        '--light' => '#f8f9fa',
        '--gray' => '#6a737b',
        '--gray-dark' => '#343a40',
        '--primary' => '#0f6cbf',
        '--secondary' => '#ced4da',
    ];

    /**
     * @var array Foreground token, background token, WCAG floor.
     *
     * Every row is checked in three resolutions - 5.x light, 5.x dark and the Moodle 4.5 fallback -
     * so a chain that is right on one branch and wrong on another cannot pass.
     *
     * brand-ink on surface at 3:1 is the border of a checked pill's count badge against the
     * indicator it sits on; test_checked_pill_badge_edge_is_pinned() ties that rule to this row.
     * The ink-muted rows on surface-inset and surface also pin the resting badge's border, against
     * the platter and against the indicator (test_resting_pill_badge_edge_is_pinned() and the
     * checked test).
     *
     * Deliberately absent: accent, brand-ink and danger-ink as normal text on surface-inset, which
     * measure 4.495, 4.495 and 4.20 in dark with core's own values, so
     * test_no_low_contrast_ink_on_the_inset_surface() forbids the pairing instead; and ink-faint,
     * which measures 3.07:1 on surface-inset in light, so test_ink_faint_is_only_inactive_text()
     * limits it to inactive text.
     */
    private const PAIRS = [
        ['ink', 'surface', 4.5],
        ['ink', 'surface-alt', 4.5],
        ['ink', 'surface-inset', 4.5],
        ['ink-muted', 'surface', 4.5],
        ['ink-muted', 'surface-alt', 4.5],
        ['ink-muted', 'surface-inset', 4.5],
        ['ink-strong', 'surface', 4.5],
        ['ink-strong', 'surface-alt', 4.5],
        ['ink-strong', 'surface-inset', 4.5],
        ['accent', 'surface', 4.5],
        ['accent', 'surface-alt', 4.5],
        ['accent-hover', 'surface', 4.5],
        ['accent-hover', 'surface-alt', 4.5],
        ['accent-hover', 'surface-inset', 4.5],
        ['on-brand-fill', 'brand-fill', 4.5],
        ['focus-ring', 'surface', 3.0],
        ['focus-ring', 'surface-alt', 3.0],
        ['focus-ring', 'surface-inset', 3.0],
        ['favourite', 'surface', 3.0],
        ['favourite', 'surface-alt', 3.0],
        ['favourite', 'surface-inset', 3.0],
        ['brand-ink', 'surface', 3.0],
        ['brand-ink', 'brand-tint', 4.5],
        ['success-ink', 'success-tint', 4.5],
        ['warning-ink', 'warning-tint', 4.5],
        ['danger-ink', 'danger-tint', 4.5],
        ['info-ink', 'info-tint', 4.5],
        ['neutral-ink', 'neutral-tint', 4.5],
    ];

    /** @var array Tokens that may not be normal-size text on surface-inset (dark: 4.495, 4.495, 4.20). */
    private const DENIED_ON_INSET = ['accent', 'brand-ink', 'danger-ink'];

    /**
     * @var int Coloured-ink rules whose effective background the scanner cannot resolve.
     *
     * Asserted for equality, so both raising it and adding an unresolvable rule fail. Lower it only
     * together with the change that makes a rule resolvable.
     *
     * The two current cases are not defects. The active filter tab paints background: transparent
     * and its label reads over the sliding indicator pill, a sibling element that paints surface -
     * z-order, which a selector scan cannot model. The hovered trail label sits on the card body,
     * whose background is set on a selector that is not a textual prefix of the label's.
     */
    private const UNRESOLVED_BUDGET = 2;

    /**
     * @var array Rules painted with an admin-chosen colour, inside which no mode token may appear.
     *
     * See test_branded_islands_use_no_mode_token(). The card-tag chip is not listed: it renders the
     * admin's colour only when one is configured, and its unconfigured state is page furniture, so
     * its fallback slot is tokenised on purpose.
     */
    private const ISLAND_ROOTS = [
        '.competency-card-gradient',
        '.plan-card-gradient',
    ];

    /**
     * @var array Custom properties carrying admin instance data: the stylesheet reads, never declares.
     *
     * The --hero-* and --ld-plans-hdr-* names belong to local_dimensions, and
     * --block-dimensions-fab-color is this plugin's spelling of its --local-dimensions-fab-color.
     * They are listed so that a rule copied from that plugin fails here.
     */
    private const ADMIN_COLOUR_NAMES = [
        '--dimension-custombgcolor',
        '--dimension-customtextcolor',
        '--hero-bg-image',
        '--hero-overlay-color',
        '--ld-plans-hdr-0',
        '--ld-plans-hdr-48',
        '--ld-plans-hdr-100',
        '--block-dimensions-fab-color',
    ];

    /**
     * @var array The named literal exemptions, each of which must match at least one live literal.
     *
     * An entry is keyed by selector substring, property and the exact literal, so it exempts one
     * literal and not a family of them; 'why' is for the reader. The list is checked in both
     * directions by test_no_colour_literal_outside_the_token_block().
     */
    private const LITERAL_EXEMPTIONS = [
        [
            'selector' => '.competency-card-gradient::after',
            'property' => 'background-image',
            'value' => '#00000082',
            'why' => 'Halftone dots composite on the admin fill or on the decorative gradient, never on the page surface.',
        ],
        [
            'selector' => '.competency-card-gradient',
            'property' => 'background',
            'value' => '#6c757d',
            'why' => 'prefers-contrast: more flat fill of both card gradients, a middle value: 4.69:1 on the light card face, '
                . '3.45:1 on the dark one.',
        ],
        [
            'selector' => '.competency-card',
            'property' => 'border',
            'value' => '#000',
            'why' => 'Print, on both card shells. Paper is white whatever the screen is doing.',
        ],
        [
            'selector' => '.competency-card-gradient',
            'property' => 'background',
            'value' => '#ced4da',
            'why' => 'Print, on both card gradients. Paper is white whatever the screen is doing.',
        ],
        [
            'selector' => '.dims-section-header',
            'property' => 'color',
            'value' => '#000',
            'why' => 'Print. Paper is white whatever the screen is doing.',
        ],
        [
            'selector' => '.dims-filter-tabs',
            'property' => '--dims-tabs-mask-color-left',
            'value' => 'black',
            'why' => 'A mask-image alpha stop, not a colour: it decides where the platter fades, never what shade anything is.',
        ],
        [
            'selector' => '.dims-filter-tabs',
            'property' => '--dims-tabs-mask-color-right',
            'value' => 'black',
            'why' => 'A mask-image alpha stop, not a colour: it decides where the platter fades, never what shade anything is.',
        ],
    ];

    /** @var array Properties whose value is a colour, and which therefore may not carry a literal. */
    private const COLOUR_PROPERTIES = [
        'color', 'background', 'background-color', 'background-image', 'box-shadow', 'fill', 'stroke',
        'text-decoration-color', 'caret-color', 'text-shadow', 'column-rule-color', 'accent-color',
    ];

    /**
     * Absolute path to the plugin root.
     *
     * @return string Plugin directory without a trailing separator.
     */
    private function plugin_root(): string {
        return dirname(__DIR__, 2);
    }

    /**
     * Every stylesheet the plugin ships: styles.css and any styles_<theme>.css.
     *
     * Moodle loads a plugin's styles_<theme>.css for the active theme and its parents, but grunt's
     * stylelint covers only styles.css, so these files are checked here. There are none today.
     *
     * @param string|null $root Plugin directory, defaulting to this plugin's own.
     * @return array List of absolute stylesheet paths.
     */
    private function stylesheets(?string $root = null): array {
        $root = $root ?? $this->plugin_root();

        return array_merge([$root . '/styles.css'], glob($root . '/styles_*.css') ?: []);
    }

    /**
     * Every source file that can put a colour, a class or an attribute in front of a user.
     *
     * amd/build is excluded because it is generated from amd/src, and docs because .gitattributes
     * keeps it out of the release zip.
     *
     * @param string|null $root Plugin directory, defaulting to this plugin's own.
     * @return array List of absolute file paths.
     */
    private function source_files(?string $root = null): array {
        $root = $root ?? $this->plugin_root();
        $files = glob($root . '/*.php') ?: [];
        foreach (['templates', 'amd/src', 'classes', 'tests/behat'] as $relative) {
            $dir = $root . '/' . $relative;
            if (!is_dir($dir)) {
                continue;
            }
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
            foreach ($iterator as $file) {
                if (!$file->isFile()) {
                    continue;
                }
                if (!in_array($file->getExtension(), ['php', 'js', 'mustache', 'feature'], true)) {
                    continue;
                }
                $files[] = $file->getPathname();
            }
        }
        sort($files);

        return $files;
    }

    /**
     * A stylesheet with every comment blanked, line numbers preserved.
     *
     * @param string $path Absolute path to a stylesheet.
     * @return string The stylesheet text with comments replaced by their own newlines.
     */
    private function uncommented(string $path): string {
        return preg_replace_callback('~/\*.*?\*/~s', static function (array $m): string {
            return str_repeat("\n", substr_count($m[0], "\n"));
        }, file_get_contents($path));
    }

    /**
     * Split a stylesheet into flat rules, with comments removed.
     *
     * At-rule wrappers are unwrapped so the rules inside them are checked exactly like the rules
     * outside: a focus rule that only appears under a media query is still a focus rule. Each
     * entry carries the file, the 1-based line the selector starts on, the selector text and the
     * declaration body.
     *
     * @param string $path Absolute path to a stylesheet.
     * @return array List of arrays with keys file, line, selector, body and at (the enclosing
     *               at-rule preludes, so a rule can be told apart from a rule that merely shares
     *               its text with one).
     */
    private function rules(string $path): array {
        $css = $this->uncommented($path);
        $rules = [];
        $stack = [];
        $selectorstart = 0;
        $length = strlen($css);
        for ($i = 0; $i < $length; $i++) {
            $char = $css[$i];
            if ($char === '{') {
                $prelude = substr($css, $selectorstart, $i - $selectorstart);
                $selector = trim($prelude);
                /* The prelude starts right after the previous brace, so it opens with the line
                   breaks before the selector, and with those left by a blanked comment. */
                $selectoroffset = $selectorstart + strlen($prelude) - strlen(ltrim($prelude));
                $line = substr_count($css, "\n", 0, $selectoroffset) + 1;
                $stack[] = [$selector, $i, $line];
                $selectorstart = $i + 1;
            } else if ($char === '}') {
                if ($stack) {
                    [$selector, $open, $line] = array_pop($stack);
                    $body = substr($css, $open + 1, $i - $open - 1);
                    /* An at-rule wrapper contains rules, not declarations; its children are
                       already collected on their own. */
                    if (!str_contains($body, '{')) {
                        $enclosing = [];
                        foreach ($stack as [$outer]) {
                            $enclosing[] = trim(preg_replace('/\s+/', ' ', $outer));
                        }
                        $rules[] = [
                            'file' => basename($path),
                            'line' => $line,
                            'selector' => trim(preg_replace('/\s+/', ' ', $selector)),
                            'body' => $body,
                            'at' => implode(' ', $enclosing),
                        ];
                    }
                }
                $selectorstart = $i + 1;
            }
        }

        return $rules;
    }

    /**
     * The declarations of one rule body, as property => value pairs.
     *
     * Custom properties are included: a literal smuggled into a plugin-owned custom property is
     * exactly as frozen as one written on a colour property, and rather harder to spot.
     *
     * @param string $body The text between a rule's braces.
     * @return array Lower-case property name => trimmed value.
     */
    private function declarations(string $body): array {
        $found = [];
        foreach (explode(';', $body) as $declaration) {
            if (!str_contains($declaration, ':')) {
                continue;
            }
            [$property, $value] = explode(':', $declaration, 2);
            $property = strtolower(trim($property));
            if ($property === '') {
                continue;
            }
            $found[$property] = trim(preg_replace('/\s+/', ' ', $value));
        }

        return $found;
    }

    /**
     * The body text of every at-rule whose prelude contains the given string.
     *
     * @param string $path Absolute path to a stylesheet.
     * @param string $prelude Substring the at-rule's prelude must contain.
     * @return array List of at-rule body strings.
     */
    private function at_rule_bodies(string $path, string $prelude): array {
        $css = $this->uncommented($path);
        $bodies = [];
        $offset = 0;
        while (($start = strpos($css, $prelude, $offset)) !== false) {
            $open = strpos($css, '{', $start);
            if ($open === false) {
                break;
            }
            $depth = 0;
            for ($i = $open; $i < strlen($css); $i++) {
                if ($css[$i] === '{') {
                    $depth++;
                } else if ($css[$i] === '}') {
                    $depth--;
                    if ($depth === 0) {
                        $bodies[] = substr($css, $open + 1, $i - $open - 1);
                        $offset = $i;
                        break;
                    }
                }
            }
            if ($depth !== 0) {
                break;
            }
        }

        return $bodies;
    }

    /**
     * The exact selectors of the three rules that own the colour contract.
     *
     * The token block, the dark activation rule and the gated OS-preference rule.
     *
     * @return array List of selector strings.
     */
    private function contract_block_selectors(): array {
        return [
            'body',
            self::DARK_ACTIVATION_SELECTOR,
            ':root:not([' . colour_mode::HOST_ATTRIBUTE . '="' . colour_mode::LIGHT . '"]) body['
                . colour_mode::MEDIA_OPTIN_ATTRIBUTE . ']:not(['
                . colour_mode::HOST_ATTRIBUTE . '="' . colour_mode::LIGHT . '"])',
        ];
    }

    /**
     * The token block's declarations, read out of the stylesheet.
     *
     * @param string|null $root Plugin directory, defaulting to this plugin's own.
     * @param string|null $prefix Token namespace to look for, defaulting to this plugin's own.
     * @return array Full token name => declaration text.
     */
    private function token_block(?string $root = null, ?string $prefix = null): array {
        $prefix = $prefix ?? self::PREFIX;
        foreach ($this->rules(($root ?? $this->plugin_root()) . '/styles.css') as $rule) {
            if ($rule['selector'] !== 'body' || $rule['at'] !== '') {
                continue;
            }
            $declarations = $this->declarations($rule['body']);
            /* The stylesheet may carry other top-level body rules, so the colour block is identified
               by declaring the surface token rather than by its position in the file. */
            if (!isset($declarations[$prefix . 'surface'])) {
                continue;
            }
            return $declarations;
        }

        return [];
    }

    /**
     * The token block's declaration lines as written, prefix rewritten to the sentinel.
     *
     * Raw text, not the parsed declarations: an extra space inside a value is a difference between
     * the two files even though it is not a difference to a browser, and this comparison is what
     * keeps the two blocks the same block rather than merely equivalent ones. Only the leading
     * indentation and the line ending are normalised, because those are the file's, not the
     * contract's.
     *
     * @param string $root Plugin directory.
     * @param string $prefix That plugin's own token namespace.
     * @return string One declaration per line, sorted.
     */
    private function token_block_text(string $root, string $prefix): string {
        $lines = [];
        foreach ($this->rules($root . '/styles.css') as $rule) {
            if ($rule['selector'] !== 'body' || $rule['at'] !== '') {
                continue;
            }
            if (!str_contains($rule['body'], $prefix . 'surface')) {
                continue;
            }
            foreach (explode("\n", $rule['body']) as $line) {
                $line = trim($line);
                /* Every custom property in the block, not only the correctly prefixed ones: a
                   line collected on the strength of its own prefix could never fail the residue
                   check, which is the check that is supposed to catch a wrong prefix. */
                if (!str_starts_with($line, '--')) {
                    continue;
                }
                $lines[] = str_replace($prefix, self::SENTINEL, $line);
            }
        }
        sort($lines);

        return implode("\n", $lines);
    }

    /**
     * The dark activation block's declarations, read out of the stylesheet.
     *
     * @return array Full token name => declaration text.
     */
    private function activation_block(): array {
        $wanted = self::DARK_ACTIVATION_SELECTOR;
        foreach ($this->rules($this->plugin_root() . '/styles.css') as $rule) {
            if ($rule['selector'] !== $wanted) {
                continue;
            }
            return $this->declarations($rule['body']);
        }

        return [];
    }

    /* --------------------------------------------------------------------------------------- */
    /* Colour arithmetic. WCAG 2.x relative luminance, alpha compositing included.               */
    /* --------------------------------------------------------------------------------------- */

    /**
     * Parse a CSS colour into red, green, blue and alpha channels.
     *
     * Handles #rgb, #rrggbb, #rrggbbaa, rgb()/rgba() in both the comma and the space syntax, and
     * the two colour keywords this stylesheet family uses.
     *
     * @param string $colour A CSS colour value.
     * @return array|null [r, g, b, a] with r/g/b in 0-255 and a in 0-1, or null when unparseable.
     */
    private function parse_colour(string $colour): ?array {
        $colour = strtolower(trim($colour));
        if ($colour === 'white') {
            return [255, 255, 255, 1.0];
        }
        if ($colour === 'black') {
            return [0, 0, 0, 1.0];
        }
        if (preg_match('/^#([0-9a-f]{3,8})$/', $colour, $m)) {
            $hex = $m[1];
            if (strlen($hex) === 3 || strlen($hex) === 4) {
                $hex = preg_replace('/(.)/', '$1$1', $hex);
            }
            if (strlen($hex) !== 6 && strlen($hex) !== 8) {
                return null;
            }
            $alpha = strlen($hex) === 8 ? hexdec(substr($hex, 6, 2)) / 255 : 1.0;

            return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2)), $alpha];
        }
        if (preg_match('/^rgba?\(([^)]*)\)$/', $colour, $m)) {
            $parts = preg_split('~[,/\s]+~', trim($m[1]), -1, PREG_SPLIT_NO_EMPTY);
            if (count($parts) < 3) {
                return null;
            }
            $channels = [];
            foreach (array_slice($parts, 0, 3) as $part) {
                $channels[] = str_ends_with($part, '%') ? (float) $part * 2.55 : (float) $part;
            }
            $alpha = 1.0;
            if (isset($parts[3])) {
                $alpha = str_ends_with($parts[3], '%') ? (float) $parts[3] / 100 : (float) $parts[3];
            }

            return [$channels[0], $channels[1], $channels[2], $alpha];
        }

        return null;
    }

    /**
     * Composite a translucent colour over an opaque backdrop.
     *
     * @param array $foreground [r, g, b, a] as returned by parse_colour().
     * @param array $backdrop [r, g, b, a] with a assumed 1.
     * @return array The opaque result, [r, g, b, 1.0].
     */
    private function composite(array $foreground, array $backdrop): array {
        $alpha = $foreground[3];

        return [
            $foreground[0] * $alpha + $backdrop[0] * (1 - $alpha),
            $foreground[1] * $alpha + $backdrop[1] * (1 - $alpha),
            $foreground[2] * $alpha + $backdrop[2] * (1 - $alpha),
            1.0,
        ];
    }

    /**
     * WCAG 2.x relative luminance of an opaque colour.
     *
     * @param array $colour [r, g, b, a] with a assumed 1.
     * @return float Relative luminance in 0..1.
     */
    private function luminance(array $colour): float {
        $channels = [];
        foreach (array_slice($colour, 0, 3) as $value) {
            $value = $value / 255;
            $channels[] = $value <= 0.03928 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
        }

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }

    /**
     * WCAG 2.x contrast ratio between a foreground and an opaque background.
     *
     * @param string $foreground Foreground CSS colour, possibly translucent.
     * @param string $background Background CSS colour, which must be opaque.
     * @return float|null The ratio, or null when either colour could not be parsed.
     */
    private function contrast(string $foreground, string $background): ?float {
        $fore = $this->parse_colour($foreground);
        $back = $this->parse_colour($background);
        if ($fore === null || $back === null) {
            return null;
        }
        if ($fore[3] < 1) {
            $fore = $this->composite($fore, $back);
        }

        return $this->ratio($fore, $back);
    }

    /**
     * WCAG 2.x contrast ratio between two opaque colours.
     *
     * @param array $one [r, g, b, a] with a assumed 1.
     * @param array $two [r, g, b, a] with a assumed 1.
     * @return float The ratio, 1 or more.
     */
    private function ratio(array $one, array $two): float {
        $lighter = max($this->luminance($one), $this->luminance($two));
        $darker = min($this->luminance($one), $this->luminance($two));

        return ($lighter + 0.05) / ($darker + 0.05);
    }

    /**
     * Contrast of an ink drawn inside a layer that an opacity fades onto its ground.
     *
     * CSS opacity renders the element first and then composites the whole layer onto what lies
     * behind it, so the ink and any fill the layer paints fade together: an ink of alpha a inside a
     * layer at opacity o reaches the ground at alpha a * o. With a fill, the ink is read against
     * the faded fill; without one, against the ground itself.
     *
     * @param string $ink The ink's CSS colour, possibly translucent.
     * @param string $ground The opaque CSS colour behind the layer.
     * @param float $opacity The layer's opacity, the product of every opacity on its way to the ground.
     * @param string|null $fill The CSS colour the layer paints under the ink, or null when it paints none.
     * @return float|null The ratio, or null when a colour could not be parsed.
     */
    private function faded_contrast(string $ink, string $ground, float $opacity, ?string $fill = null): ?float {
        $text = $this->parse_colour($ink);
        $back = $this->parse_colour($ground);
        $face = $fill === null ? null : $this->parse_colour($fill);
        if ($text === null || $back === null || ($fill !== null && $face === null)) {
            return null;
        }
        $under = $face === null ? $back : $this->composite($face, $back);
        $drawn = $this->composite($text, $under);
        $fadedink = $this->composite([$drawn[0], $drawn[1], $drawn[2], $opacity], $back);
        $fadedground = $face === null ? $back : $this->composite([$under[0], $under[1], $under[2], $opacity], $back);

        return $this->ratio($fadedink, $fadedground);
    }

    /**
     * Resolve one token to a concrete colour in one of the three resolutions.
     *
     * The declaration is read out of the stylesheet. In light and dark mode the chain's first var()
     * name is looked up in CORE_LIGHT / CORE_DARK, except that dark mode first takes the activation
     * block's own value for the tokens it assigns. In bs4 mode the first name CORE_BS4 declares
     * wins, and the chain's last literal otherwise. A declaration that is not a var() chain is
     * returned as written.
     *
     * @param string $suffix Token suffix, e.g. ink-muted.
     * @param string $mode One of light, dark or bs4.
     * @return string|null The resolved CSS colour, or null when the token is not declared or its
     *                     chain names a core value the maps do not carry.
     */
    private function resolve(string $suffix, string $mode): ?string {
        $name = self::PREFIX . $suffix;
        if ($mode === 'dark') {
            $dark = $this->activation_block();
            if (isset($dark[$name])) {
                return $dark[$name];
            }
        }
        $declaration = $this->token_block()[$name] ?? null;
        if ($declaration === null) {
            return null;
        }
        if ($mode === 'bs4') {
            /*
             * Walk the chain the way the browser does on Moodle 4.5: the --bs-* rung is undefined
             * there, so the fallback is taken; the Bootstrap 4 rung behind it is defined, so that
             * is where the value comes from; and only a chain with no such rung reaches its
             * terminal literal.
             */
            preg_match_all('/--[a-z0-9-]+/i', $declaration, $names);
            foreach ($names[0] as $name) {
                if (isset(self::CORE_BS4[$name])) {
                    return self::CORE_BS4[$name];
                }
            }
            if (preg_match_all('/#[0-9a-f]{3,8}\b|\b(?:rgba?|hsla?)\([^)]*\)/i', $declaration, $matches)) {
                return end($matches[0]);
            }

            return null;
        }
        if (!preg_match('/^var\(\s*(--[a-z0-9-]+)/i', $declaration, $m)) {
            return $declaration;
        }
        $core = $m[1];
        if ($mode === 'dark') {
            return self::CORE_DARK[$core] ?? self::CORE_LIGHT[$core] ?? null;
        }

        return self::CORE_LIGHT[$core] ?? null;
    }

    /* --------------------------------------------------------------------------------------- */
    /* The literal ban and the declaration contract.                                           */
    /* --------------------------------------------------------------------------------------- */

    /**
     * Every colour literal in the stylesheets outside the contract rules and the Bootstrap 4 polyfill.
     *
     * A colour property is one in COLOUR_PROPERTIES, or any border*, outline*, background* or
     * custom property.
     *
     * @return array List of arrays with keys where, selector, property and value.
     */
    private function literal_findings(): array {
        $contractselectors = $this->contract_block_selectors();
        $gate = '.' . bootstrap::ROOT_CLASS_BS4;
        $findings = [];
        foreach ($this->stylesheets() as $path) {
            foreach ($this->rules($path) as $rule) {
                if (in_array($rule['selector'], $contractselectors, true)) {
                    continue;
                }
                if (str_contains($rule['selector'], $gate)) {
                    continue;
                }
                foreach ($this->declarations($rule['body']) as $property => $value) {
                    $iscolour = in_array($property, self::COLOUR_PROPERTIES, true)
                        || str_starts_with($property, 'border')
                        || str_starts_with($property, 'outline')
                        || str_starts_with($property, 'background')
                        || str_starts_with($property, '--');
                    if (!$iscolour) {
                        continue;
                    }
                    $pattern = '/#[0-9a-f]{3,8}\b|\b(?:rgba?|hsla?)\([^)]*\)|(?<![-\w])(?:white|black|red|green|'
                        . 'blue|silver|gray|grey|orange|yellow|purple|navy|teal|maroon|olive|lime|aqua|fuchsia)'
                        . '(?![-\w])/i';
                    if (!preg_match_all($pattern, $value, $matches)) {
                        continue;
                    }
                    foreach (array_unique($matches[0]) as $literal) {
                        $findings[] = [
                            'where' => $rule['file'] . ':' . $rule['line'],
                            'selector' => $rule['selector'],
                            'property' => $property,
                            'value' => $literal,
                        ];
                    }
                }
            }
        }

        return $findings;
    }

    /**
     * No colour literal may live outside the token block, the mode blocks or a named exemption.
     *
     * The Bootstrap 4 polyfill is also skipped. Checked in both directions: an unexempted literal
     * fails, and so does an exemption that no longer matches a live literal, so the allow-list
     * cannot outlive the rules it was written for.
     *
     * Changes that must make it fail: put color: #6c757d back on any component rule; delete an
     * exempted rule while leaving its LITERAL_EXEMPTIONS entry behind.
     *
     * @return void
     */
    public function test_no_colour_literal_outside_the_token_block(): void {
        $findings = $this->literal_findings();
        $offenders = [];
        $matched = [];
        foreach ($findings as $finding) {
            $exempt = false;
            foreach (self::LITERAL_EXEMPTIONS as $index => $exemption) {
                if (
                    str_contains($finding['selector'], $exemption['selector'])
                    && $finding['property'] === $exemption['property']
                    && strcasecmp($finding['value'], $exemption['value']) === 0
                ) {
                    $matched[$index] = true;
                    $exempt = true;
                }
            }
            if (!$exempt) {
                $offenders[] = $finding['where'] . ' ' . $finding['property'] . ': ' . $finding['value']
                    . ' (' . $finding['selector'] . ')';
            }
        }
        foreach (self::LITERAL_EXEMPTIONS as $index => $exemption) {
            if (!isset($matched[$index])) {
                $offenders[] = 'exemption ' . $index . ' (' . $exemption['selector'] . ' '
                    . $exemption['property'] . ': ' . $exemption['value'] . ') matches nothing any more';
            }
        }
        sort($offenders);
        $this->assertSame(
            [],
            $offenders,
            'Colour lives in the token block and nowhere else, and every exemption must still match a '
                . 'live literal: ' . implode('; ', $offenders)
        );
    }

    /**
     * The token block declares exactly the contract, with exactly the declared text.
     *
     * Equality on the declaration string, not a shape regex: a pattern can prove a chain exists
     * but not which literal it ends in, and on Moodle 4.5 a chain without a Bootstrap 4 rung
     * renders that literal.
     *
     * Changes that must make it fail: rewrite one chain as a bare literal; delete a token; add a
     * token to the CSS without adding it to LIGHT.
     *
     * @return void
     */
    public function test_token_block_declares_exactly_the_contract(): void {
        $declared = $this->token_block();
        $expected = [];
        foreach (self::LIGHT as $suffix => $value) {
            $expected[self::PREFIX . $suffix] = $value;
        }
        ksort($declared);
        ksort($expected);
        $offenders = [];
        foreach (array_diff(array_keys($declared), array_keys($expected)) as $extra) {
            $offenders[] = $extra . ' is declared but is not in the contract';
        }
        foreach (array_diff(array_keys($expected), array_keys($declared)) as $missing) {
            $offenders[] = $missing . ' is in the contract but is not declared';
        }
        foreach ($expected as $name => $value) {
            if (isset($declared[$name]) && $declared[$name] !== $value) {
                $offenders[] = $name . ' declares "' . $declared[$name] . '" but the contract is "' . $value . '"';
            }
        }
        sort($offenders);
        $this->assertSame(
            [],
            $offenders,
            'The body token block must declare exactly the ' . count(self::LIGHT) . ' contract tokens, '
                . 'each with its exact chain: ' . implode('; ', $offenders)
        );
    }

    /**
     * The declared suffixes are exactly SUFFIXES, the set shared with local_dimensions.
     *
     * Needs no sibling installed, so a suffix renamed in one plugin fails that plugin's own build
     * even where test_token_block_is_identical_to_the_sibling() skips.
     *
     * @return void
     */
    public function test_token_suffixes_match_the_family_contract(): void {
        $declared = [];
        foreach (array_keys($this->token_block()) as $name) {
            if (str_starts_with($name, self::PREFIX)) {
                $declared[] = substr($name, strlen(self::PREFIX));
            }
        }
        $expected = self::SUFFIXES;
        sort($declared);
        sort($expected);
        $this->assertSame(
            $expected,
            $declared,
            'The token suffix set is shared with ' . self::SIBLING . ' and is what makes the two plugins '
                . 'one system; only the frankenstyle prefix may differ.'
        );
    }

    /**
     * The two plugins' token blocks must be the same block under two prefixes.
     *
     * Only the declaration lines are compared (see token_block_text()), so the comments around them
     * may differ between the two stylesheets. After each plugin's own prefix is rewritten to
     * SENTINEL, neither prefix may survive in either block, so a name carrying the other plugin's
     * prefix cannot pass by looking the same on both sides.
     *
     * @return void
     */
    public function test_token_block_is_identical_to_the_sibling(): void {
        $siblingroot = \core_component::get_component_directory(self::SIBLING);
        if ($siblingroot === null || !is_readable($siblingroot . '/styles.css')) {
            $this->markTestSkipped(
                'The colour-token family is ' . self::SIBLING . ' + block_dimensions, and ' . self::SIBLING
                    . ' is not installed on this site, so the cross-repo half of the contract cannot run. '
                    . 'test_token_suffixes_match_the_family_contract still holds the parity floor here, and '
                    . 'test_ci_checks_out_the_family_sibling is what stops this skip becoming permanent.'
            );
        }
        $minetext = $this->token_block_text($this->plugin_root(), self::PREFIX);
        $theirstext = $this->token_block_text($siblingroot, self::SIBLING_PREFIX);
        if ($theirstext === '') {
            /*
             * No top-level body rule declaring the sibling's surface token. When the sibling
             * declares none of its tokens at all, it predates the contract: an adoption gap, so the
             * test skips. When it does declare them, the block has moved, lost its anchor or been
             * renamed, and that is a divergence this plugin's build is the only one to see: CI runs
             * this plugin's testsuite, never the sibling's.
             */
            $declaration = '/' . preg_quote(self::SIBLING_PREFIX, '/') . '[a-z0-9-]+\s*:/';
            if (preg_match($declaration, $this->uncommented($siblingroot . '/styles.css'))) {
                $this->fail(
                    self::SIBLING . ' declares ' . self::SIBLING_PREFIX . ' tokens, but not in a top-level body '
                        . 'rule declaring ' . self::SIBLING_PREFIX . 'surface, which is where the family keeps its '
                        . 'token block; the two blocks cannot be compared until it is back there.'
                );
            }
            $this->markTestSkipped(
                self::SIBLING . ' is installed but declares no ' . self::SIBLING_PREFIX
                    . ' tokens at all, so it has not adopted the family colour contract yet and there is '
                    . 'nothing to compare against. Land the two plugins\' contract branches together, '
                    . 'or the sibling\'s first, and this comparison starts running by itself.'
            );
        }
        $residue = [];
        foreach (['block_dimensions' => $minetext, self::SIBLING => $theirstext] as $plugin => $text) {
            foreach ([self::PREFIX, self::SIBLING_PREFIX] as $prefix) {
                if (str_contains($text, $prefix)) {
                    $residue[] = $plugin . "'s block still carries " . $prefix . ' after substitution';
                }
            }
        }
        $this->assertSame(
            [],
            $residue,
            'A token name that survives the prefix substitution is a name that is unprefixed or carries '
                . 'the wrong plugin\'s prefix: ' . implode('; ', $residue)
        );
        $this->assertSame(
            $theirstext,
            $minetext,
            'block_dimensions and ' . self::SIBLING . ' must declare the same token block under their own '
                . 'prefixes; they differ.'
        );
    }

    /**
     * CI must check the family sibling out, or the cross-repo comparison silently stops running.
     *
     * Without the checkout test_token_block_is_identical_to_the_sibling() skips on every job, and a
     * skip fails nothing; the workflow file is the only place that can be checked.
     *
     * @return void
     */
    public function test_ci_checks_out_the_family_sibling(): void {
        $workflow = $this->plugin_root() . '/.github/workflows/ci.yml';
        $this->assertFileExists($workflow, 'The CI workflow is where the sibling checkout is declared.');
        $offenders = [];
        $jobs = preg_split('/\n(?=  [a-z0-9-]+:\n)/', file_get_contents($workflow));
        $found = 0;
        foreach ($jobs as $job) {
            if (!str_contains($job, 'moodle-plugin-ci.yml@main')) {
                continue;
            }
            $found++;
            preg_match('/^  ([a-z0-9-]+):/m', $job, $m);
            $name = $m[1] ?? 'unnamed job';
            if (!preg_match('/plugin-dependencies:.*\n(\s+.*\n)*?\s*\S*moodle-' . self::SIBLING . '\b/', $job)) {
                $offenders[] = $name;
            }
        }
        $this->assertGreaterThan(
            0,
            $found,
            'No reusable-workflow job was found in ci.yml, so this test would pass over an empty list.'
        );
        $this->assertSame(
            [],
            $offenders,
            'These CI jobs do not check out moodle-' . self::SIBLING . ' under plugin-dependencies, so the '
                . 'cross-repo token comparison would skip on them: ' . implode(', ', $offenders)
        );
    }

    /* --------------------------------------------------------------------------------------- */
    /* The activation contract.                                                                */
    /* --------------------------------------------------------------------------------------- */

    /**
     * The mode layer may assign the three plugin-owned decorative tokens and nothing else.
     *
     * The mode layer is every rule whose selector reads data-bs-theme or that sits in the
     * prefers-color-scheme block, and there must be exactly two. The other 31 tokens follow core's
     * own --bs-* values, so a wrongly firing mode rule can only deepen a shadow, darken the scrim
     * and brighten the favourite star; it cannot give the plugin a surface or an ink that core did
     * not supply, which is how a plugin ends up dark on a light page.
     *
     * Changes that must make it fail: assign a surface token in the dark block; add another rule
     * inside the prefers-color-scheme block.
     *
     * @return void
     */
    public function test_dark_activation_block_assigns_only_plugin_owned_tokens(): void {
        $allowed = [];
        foreach (self::DARK_OWNED as $suffix) {
            $allowed[] = self::PREFIX . $suffix;
        }
        $offenders = [];
        $checked = 0;
        foreach ($this->stylesheets() as $path) {
            foreach ($this->rules($path) as $rule) {
                $inmedia = str_contains($rule['at'], 'prefers-color-scheme');
                if (!str_contains($rule['selector'], colour_mode::HOST_ATTRIBUTE) && !$inmedia) {
                    continue;
                }
                $checked++;
                foreach ($this->declarations($rule['body']) as $property => $value) {
                    if (in_array($property, $allowed, true)) {
                        continue;
                    }
                    $offenders[] = $rule['file'] . ':' . $rule['line'] . ' declares ' . $property
                        . ': ' . $value;
                }
            }
        }
        $this->assertSame(
            2,
            $checked,
            'Exactly two rules carry the mode layer - the activation block and the inert OS-preference '
                . 'block - and this test found ' . $checked . '.'
        );
        sort($offenders);
        $this->assertSame(
            [],
            $offenders,
            'Only ' . implode(', ', $allowed) . ' may be assigned by a mode rule; everything else follows '
                . 'core\'s own --bs-* values and must have no dark rule at all: ' . implode('; ', $offenders)
        );
    }

    /**
     * Every colour-mode selector has body as its subject, and no dead mechanism survives.
     *
     * A bare [data-bs-theme="dark"] matches through any ancestor at any depth, and CSS descendant
     * combinators have no nearest-ancestor-wins rule: a theme that marks only its navbar dark
     * (theme_boost_union_fundaseg does) would switch the plugin's tokens for everything below it.
     * With body as the subject the only possible ancestor is html. Class hooks such as .theme-dark
     * and body.dark are rejected because no supported Moodle branch emits them.
     *
     * Changes that must make it fail: change one selector to a bare attribute selector; re-add a
     * .theme-dark rule.
     *
     * @return void
     */
    public function test_activation_selectors_have_body_as_subject(): void {
        $offenders = [];
        foreach ($this->stylesheets() as $path) {
            foreach ($this->rules($path) as $rule) {
                if (!str_contains($rule['selector'], colour_mode::HOST_ATTRIBUTE)) {
                    continue;
                }
                foreach (explode(',', $rule['selector']) as $part) {
                    $part = trim($part);
                    /* The subject is the last compound in the selector. It must be body, either
                       carrying the attribute itself or sitting under an html that does. */
                    $subject = substr($part, (int) strrpos(' ' . $part, ' '));
                    if (!str_starts_with($subject, 'body')) {
                        $offenders[] = $rule['file'] . ':' . $rule['line'] . ' ' . $part;
                    }
                }
            }
            $css = $this->uncommented($path);
            foreach (['.theme-dark', 'body.dark', '.darkmode', '[data-theme'] as $dead) {
                if (str_contains($css, $dead)) {
                    $offenders[] = basename($path) . ' still contains ' . $dead
                        . ', which nothing on any supported branch has ever emitted';
                }
            }
        }
        sort($offenders);
        $this->assertSame(
            [],
            $offenders,
            'The host signal is read only on body or on the html element above it, and no other dark '
                . 'mechanism may live beside it: ' . implode('; ', $offenders)
        );
    }

    /**
     * The OS-preference fallback is written, is gated, and nothing can reach the gate.
     *
     * Three assertions: styles.css carries exactly one @media (prefers-color-scheme) block, every
     * selector in it carries the gate attribute, and no runtime file of this plugin or of the
     * installed local_dimensions contains that attribute.
     *
     * Changes that must make it fail, one per assertion: delete the whole media block; drop the
     * gate from the selector; write the attribute into any template.
     *
     * @return void
     */
    public function test_media_fallback_is_written_and_unreachable(): void {
        $bodies = $this->at_rule_bodies($this->plugin_root() . '/styles.css', '@media (prefers-color-scheme');
        $this->assertCount(
            1,
            $bodies,
            'styles.css must carry exactly one @media (prefers-color-scheme) block: the OS-preference '
                . 'fallback, written and ready so switching it on is one edit. Found ' . count($bodies) . '.'
        );
        $ungated = [];
        foreach ($bodies as $body) {
            foreach (explode('}', $body) as $chunk) {
                if (!str_contains($chunk, '{')) {
                    continue;
                }
                $selector = trim(preg_replace('/\s+/', ' ', explode('{', $chunk)[0]));
                if ($selector === '') {
                    continue;
                }
                if (!str_contains($selector, colour_mode::MEDIA_OPTIN_ATTRIBUTE)) {
                    $ungated[] = $selector;
                }
            }
        }
        $this->assertSame(
            [],
            $ungated,
            'Every selector in the OS-preference block must carry [' . colour_mode::MEDIA_OPTIN_ATTRIBUTE
                . ']; these do not, so the block can fire: ' . implode('; ', $ungated)
        );
        $writers = [];
        foreach ($this->family_roots() as $component => $root) {
            foreach ($this->source_files($root) as $path) {
                if (basename($path) === 'colour_mode.php' || str_contains($path, '/tests/')) {
                    /* The colour_mode class only declares the name, and nothing under tests/ runs
                       on a production page. */
                    continue;
                }
                if (str_contains(file_get_contents($path), colour_mode::MEDIA_OPTIN_ATTRIBUTE)) {
                    $writers[] = $component . '/' . basename($path);
                }
            }
        }
        sort($writers);
        $this->assertSame(
            [],
            $writers,
            'Nothing may write [' . colour_mode::MEDIA_OPTIN_ATTRIBUTE . ']: the OS preference is an input '
                . 'to the host\'s own colour mode, never an independent trigger, and a plugin that fires on '
                . 'it directly is dark inside a light page. Found in: ' . implode(', ', $writers)
        );
    }

    /**
     * The plugin roots of every installed member of the colour-token family.
     *
     * @return array Component name => absolute plugin directory.
     */
    private function family_roots(): array {
        $roots = ['block_dimensions' => $this->plugin_root()];
        $sibling = \core_component::get_component_directory(self::SIBLING);
        if ($sibling !== null && is_dir($sibling)) {
            $roots[self::SIBLING] = $sibling;
        }

        return $roots;
    }

    /* --------------------------------------------------------------------------------------- */
    /* The contrast obligations.                                                               */
    /* --------------------------------------------------------------------------------------- */

    /**
     * The effective background of every rule that paints one, keyed by selector.
     *
     * Only a flat token background is recorded: a value starting with var() that reads a plugin
     * token. A gradient or an image has no single colour to compute a contrast ratio against.
     *
     * @return array Selector part => token suffix.
     */
    private function background_map(): array {
        $map = [];
        foreach ($this->stylesheets() as $path) {
            foreach ($this->rules($path) as $rule) {
                foreach ($this->declarations($rule['body']) as $property => $value) {
                    if ($property !== 'background' && $property !== 'background-color') {
                        continue;
                    }
                    if (!str_starts_with($value, 'var(')) {
                        continue;
                    }
                    if (!preg_match('/' . preg_quote(self::PREFIX, '/') . '([a-z0-9-]+)/', $value, $m)) {
                        continue;
                    }
                    foreach (explode(',', $rule['selector']) as $part) {
                        $map[trim($part)] = $m[1];
                    }
                }
            }
        }

        return $map;
    }

    /**
     * The effective background token for a selector, resolved through its ancestors.
     *
     * The longest recorded selector that equals this one or is a textual prefix of it wins, so an
     * ink set in a descendant rule is paired with the background set in its ancestor rule.
     *
     * @param string $selector One selector part, whitespace already collapsed.
     * @param array $map Selector part => token suffix, from background_map().
     * @return string|null The token suffix, or null when no ancestor paints a flat token.
     */
    private function effective_background(string $selector, array $map): ?string {
        $best = null;
        $bestlength = -1;
        foreach ($map as $candidate => $suffix) {
            $matches = $candidate === $selector
                || preg_match('/^' . preg_quote($candidate, '/') . '[\s.:\[>]/', $selector);
            if ($matches && strlen($candidate) > $bestlength) {
                $best = $suffix;
                $bestlength = strlen($candidate);
            }
        }

        return $best;
    }

    /**
     * Three coloured inks may not be normal-size text on the inset surface.
     *
     * accent and brand-ink measure 4.495:1 there in dark and danger-ink 4.20:1, with core's own
     * values on core's own surface. Large text is exempt because all three clear 3:1, but a rule
     * whose font-size is not given in rem counts as normal, never as large. Rules whose background
     * cannot be resolved are counted against UNRESOLVED_BUDGET.
     *
     * Changes that must make it fail: write the denied pairing in one rule; write the same pairing
     * split across an ancestor rule and a descendant rule; raise UNRESOLVED_BUDGET.
     *
     * @return void
     */
    public function test_no_low_contrast_ink_on_the_inset_surface(): void {
        $map = $this->background_map();
        $offenders = [];
        $unresolved = [];
        foreach ($this->stylesheets() as $path) {
            foreach ($this->rules($path) as $rule) {
                $declarations = $this->declarations($rule['body']);
                if (!isset($declarations['color'])) {
                    continue;
                }
                if (!preg_match('/' . preg_quote(self::PREFIX, '/') . '([a-z0-9-]+)/', $declarations['color'], $m)) {
                    continue;
                }
                if (!in_array($m[1], self::DENIED_ON_INSET, true)) {
                    continue;
                }
                $islarge = false;
                if (isset($declarations['font-size']) && preg_match('/^([0-9.]+)rem/', $declarations['font-size'], $s)) {
                    $pixels = (float) $s[1] * 16;
                    $bold = isset($declarations['font-weight']) && (int) $declarations['font-weight'] >= 700;
                    $islarge = $pixels >= 24 || ($bold && $pixels >= 18.66);
                }
                foreach (explode(',', $rule['selector']) as $part) {
                    $part = trim($part);
                    $background = $this->effective_background($part, $map);
                    if ($background === null) {
                        $unresolved[] = $rule['file'] . ':' . $rule['line'] . ' ' . $part;
                        continue;
                    }
                    if ($background === 'surface-inset' && !$islarge) {
                        $offenders[] = $rule['file'] . ':' . $rule['line'] . ' paints ' . $m[1]
                            . ' on surface-inset (' . $part . ')';
                    }
                }
            }
        }
        sort($offenders);
        $this->assertSame(
            [],
            $offenders,
            'accent, brand-ink and danger-ink measure 4.495, 4.495 and 4.20 against surface-inset on the dark '
                . 'page, so they may not be normal-size text there: ' . implode('; ', $offenders)
        );
        sort($unresolved);
        $this->assertSame(
            self::UNRESOLVED_BUDGET,
            count($unresolved),
            'Coloured-ink rules whose effective background the scanner cannot resolve are counted rather '
                . 'than silently skipped, and the count is a ratchet that may only fall. Expected '
                . self::UNRESOLVED_BUDGET . ', found ' . count($unresolved) . ': ' . implode('; ', $unresolved)
        );
    }

    /**
     * Every declared pair clears its floor, in all three resolutions.
     *
     * The values are resolved out of the stylesheet with resolve(), so the test fails when the CSS
     * changes, not only when the constants in this file do. The ratio is compared unrounded: a WCAG
     * threshold is a minimum, so 4.495:1 fails a 4.5 floor.
     *
     * Changes that must make it fail, all of them applied to the CSS: point favourite at #fd7e14 in
     * light (2.57 on surface); put ink-muted's 4.5 fallback back to var(--gray, #6a737b) (4.07 on
     * surface-inset); chain focus-ring to --bs-focus-ring-color (core does not flip it).
     *
     * @return void
     */
    public function test_declared_values_clear_their_floor(): void {
        $offenders = [];
        foreach (['light', 'dark', 'bs4'] as $mode) {
            foreach (self::PAIRS as [$foreground, $background, $floor]) {
                $fore = $this->resolve($foreground, $mode);
                $back = $this->resolve($background, $mode);
                if ($fore === null || $back === null) {
                    $offenders[] = $mode . ': ' . $foreground . ' on ' . $background
                        . ' could not be resolved out of the stylesheet';
                    continue;
                }
                $ratio = $this->contrast($fore, $back);
                if ($ratio === null) {
                    $offenders[] = $mode . ': ' . $foreground . ' (' . $fore . ') on ' . $background
                        . ' (' . $back . ') could not be parsed as colours';
                    continue;
                }
                if ($ratio < $floor) {
                    $offenders[] = sprintf(
                        '%s: %s (%s) on %s (%s) is %.3f:1, floor %.1f',
                        $mode,
                        $foreground,
                        $fore,
                        $background,
                        $back,
                        $ratio,
                        $floor
                    );
                }
            }
        }
        $this->assertSame(
            [],
            $offenders,
            'Every token pairing must clear its WCAG floor on the light page, on the dark page and on the '
                . 'Moodle 4.5 fallback literals: ' . implode('; ', $offenders)
        );
    }

    /**
     * The favourite star sits on an opaque disc, in every state that paints one.
     *
     * The disc overlays card art the admin chose. A translucent disc such as the scrim lets the art
     * through and moves the star's ground: over black art the light star measures 1.80:1. So every
     * rule painting the disc must read one plugin token that resolves opaque in all three
     * resolutions, and every star colour the stylesheet sets must clear the 3:1 floor for a
     * graphical object (WCAG 1.4.11) on it.
     *
     * Changes that must make it fail: paint .dims-fav-btn, or its hover, with the scrim token; give
     * the empty star the line token.
     *
     * @return void
     */
    public function test_favourite_star_sits_on_an_opaque_ground(): void {
        $grounds = [];
        $stars = [];
        foreach ($this->stylesheets() as $path) {
            foreach ($this->rules($path) as $rule) {
                $declarations = $this->declarations($rule['body']);
                $where = $rule['file'] . ':' . $rule['line'];
                foreach (explode(',', $rule['selector']) as $part) {
                    $part = trim($part);
                    if (str_contains($part, '.dims-fav-icon') && isset($declarations['color'])) {
                        $stars[$where . ' ' . $part] = $declarations['color'];
                    }
                    /* The disc itself: the button as the subject, in any state. */
                    if (!preg_match('/\.dims-fav-btn(?::[a-z-]+)*$/', $part)) {
                        continue;
                    }
                    foreach (['background', 'background-color'] as $property) {
                        if (isset($declarations[$property])) {
                            $grounds[$where . ' ' . $part] = $declarations[$property];
                        }
                    }
                }
            }
        }
        $this->assertNotEmpty($grounds, 'No rule paints the favourite disc, so this test checks nothing.');
        $this->assertNotEmpty($stars, 'No rule colours the favourite star, so this test checks nothing.');

        $tokenpattern = '/^var\(' . preg_quote(self::PREFIX, '/') . '([a-z0-9-]+)\)$/';
        $offenders = [];
        foreach ($grounds as $groundsite => $groundvalue) {
            if (!preg_match($tokenpattern, $groundvalue, $ground)) {
                $offenders[] = $groundsite . ' paints ' . $groundvalue . ', which is not one plugin token';
                continue;
            }
            foreach (['light', 'dark', 'bs4'] as $mode) {
                $back = (string) $this->resolve($ground[1], $mode);
                $parsed = $this->parse_colour($back);
                if ($parsed === null || $parsed[3] < 1) {
                    $offenders[] = $mode . ': ' . $groundsite . ' paints ' . $ground[1] . ' (' . $back
                        . '), which is not opaque';
                    continue;
                }
                foreach ($stars as $starsite => $starvalue) {
                    if (!preg_match($tokenpattern, $starvalue, $star)) {
                        $offenders[] = $starsite . ' colours the star ' . $starvalue . ', which is not one plugin token';
                        continue;
                    }
                    $ratio = $this->contrast((string) $this->resolve($star[1], $mode), $back);
                    if ($ratio === null || $ratio < 3.0) {
                        $offenders[] = sprintf(
                            '%s: %s (%s) on %s (%s) is %.3f:1, floor 3.0',
                            $mode,
                            $star[1],
                            $starsite,
                            $ground[1],
                            $groundsite,
                            (float) $ratio
                        );
                    }
                }
            }
        }
        $offenders = array_values(array_unique($offenders));
        sort($offenders);
        $this->assertSame(
            [],
            $offenders,
            'The favourite star must sit on an opaque disc it clears 3:1 against, in every mode: '
                . implode('; ', $offenders)
        );
    }

    /**
     * The plugin token a top-level rule with exactly this selector paints its background in.
     *
     * @param string $selector The whole selector of the rule, whitespace collapsed.
     * @return string|null The token suffix, or null when no such rule paints a plugin token.
     */
    private function ground_token(string $selector): ?string {
        $tokenpattern = '/var\(\s*' . preg_quote(self::PREFIX, '/') . '([a-z0-9-]+)\s*\)/';
        $ground = null;
        foreach ($this->stylesheets() as $path) {
            foreach ($this->rules($path) as $rule) {
                if ($rule['at'] !== '' || $rule['selector'] !== $selector) {
                    continue;
                }
                $declarations = $this->declarations($rule['body']);
                $fill = $declarations['background-color'] ?? $declarations['background'] ?? '';
                $ground = preg_match($tokenpattern, $fill, $m) ? $m[1] : $ground;
            }
        }

        return $ground;
    }

    /**
     * Every border colour that can reach a filter pill's count badge in one state.
     *
     * A part can reach the badge when the badge is its subject, it is in no user-action state, and
     * outside :not() it names nothing only the other state carries: .active and aria-checked="true"
     * for a checked pill, aria-checked="false" for an unchecked one. So the resting badge's rule,
     * which names neither, is held to both grounds. That is deliberate rather than an
     * approximation of the cascade, which card_layout_test models: the checked rule overrides the
     * resting border today, but the resting border is what a checked badge falls back to if the
     * checked one goes.
     *
     * @param bool $checked True for a checked pill's badge, false for an unchecked one's.
     * @return array [edges, offenders]: site => token suffix for each border colour naming a plugin
     *               token, and a message for each border that names none.
     */
    private function pill_badge_edges(bool $checked): array {
        $tokenpattern = '/var\(\s*' . preg_quote(self::PREFIX, '/') . '([a-z0-9-]+)\s*\)/';
        $other = $checked ? '/\[aria-checked="false"\]/' : '/\.active(?![\w-])|\[aria-checked="true"\]/';
        $own = $checked ? '/\.active(?![\w-])|\[aria-checked="true"\]/' : '/\[aria-checked="false"\]/';
        $edges = [];
        $offenders = [];
        foreach ($this->stylesheets() as $path) {
            foreach ($this->rules($path) as $rule) {
                if ($rule['at'] !== '') {
                    continue;
                }
                $declarations = $this->declarations($rule['body']);
                $where = $rule['file'] . ':' . $rule['line'];
                foreach (explode(',', $rule['selector']) as $part) {
                    $part = trim($part);
                    preg_match_all('/:not\(([^()]*)\)/', $part, $negations);
                    $positive = (string) preg_replace('/:not\([^()]*\)/', '', $part);
                    $reaches = preg_match('/\.dims-filter-count$/', $part)
                        && !preg_match('/:(?:hover|active|focus)/', $positive)
                        && !preg_match($other, $positive)
                        && !preg_match($own, implode(' ', $negations[1]));
                    if (!$reaches) {
                        continue;
                    }
                    foreach ($declarations as $property => $value) {
                        if (!str_starts_with($property, 'border') || $property === 'border-radius') {
                            continue;
                        }
                        if (!preg_match_all($tokenpattern, $value, $tokens)) {
                            $offenders[] = $where . ' ' . $part . ' sets ' . $property . ': ' . $value
                                . ', which names no plugin token';
                            continue;
                        }
                        foreach ($tokens[1] as $token) {
                            $edges[$where . ' ' . $part . ' ' . $property] = $token;
                        }
                    }
                }
            }
        }

        return [$edges, $offenders];
    }

    /**
     * The edges that PAIRS does not pin at 3:1 or more against a ground.
     *
     * @param array $edges Site => token suffix, from pill_badge_edges().
     * @param string $ground The ground's token suffix.
     * @return array One message per unpinned edge.
     */
    private function unpinned_edges(array $edges, string $ground): array {
        $offenders = [];
        foreach ($edges as $site => $token) {
            $pinned = false;
            foreach (self::PAIRS as [$foreground, $background, $floor]) {
                if ($foreground === $token && $background === $ground && $floor >= 3.0) {
                    $pinned = true;
                }
            }
            if (!$pinned) {
                $offenders[] = $site . ' draws ' . $token . ', and PAIRS has no ' . $token . ' on ' . $ground
                    . ' row with a floor of 3:1 or more';
            }
        }

        return $offenders;
    }

    /**
     * A checked pill's count badge is edged in a token pinned at 3:1 against the indicator.
     *
     * The badge of a checked status or show-all pill sits on the sliding indicator, and its
     * brand-tint fill measures 1.33:1 against the indicator's surface in light and 1.13:1 in dark,
     * so the badge's outline is its border. The border colours and the indicator's fill are read
     * out of the stylesheet, and each pairing must be a row of PAIRS with a floor of at least 3:1
     * (WCAG 1.4.11), where test_declared_values_clear_their_floor() measures it in all three
     * resolutions. pill_badge_edges() says which borders count. card_layout_test checks that a
     * border reaches every checked pill.
     *
     * Changes that must make it fail: draw the checked badge's border in brand-edge; delete the
     * brand-ink on surface row from PAIRS; delete both the checked and the resting badge's border.
     *
     * @return void
     */
    public function test_checked_pill_badge_edge_is_pinned(): void {
        $indicator = $this->ground_token('.block_dimensions .dims-filter-tabs-indicator');
        $this->assertNotNull($indicator, 'The indicator paints no plugin token, so there is nothing to measure against.');
        [$edges, $offenders] = $this->pill_badge_edges(true);
        $this->assertNotEmpty(
            $edges,
            'No rule draws a border on a checked pill\'s count badge, so its fill is its only outline, and '
                . 'brand-tint on the indicator is 1.33:1 in light.'
        );
        $offenders = array_merge($offenders, $this->unpinned_edges($edges, $indicator));
        sort($offenders);
        $this->assertSame(
            [],
            $offenders,
            'A checked pill\'s count badge must be edged in a token PAIRS pins at 3:1 against the indicator: '
                . implode('; ', $offenders)
        );
    }

    /**
     * An unchecked pill's count badge is edged in a token pinned at 3:1 against the platter.
     *
     * An unchecked pill paints no fill, so its badge sits on the filter platter, and the badge's
     * surface fill measures 1.19:1 against the platter's surface-inset in light and 1.41:1 in dark:
     * as on a checked pill, the badge's outline is its border. Each border colour that can reach
     * the badge must be a row of PAIRS against the platter's fill with a floor of at least 3:1.
     *
     * Changes that must make it fail: delete the resting badge's border; draw it in line; delete
     * the ink-muted on surface-inset row from PAIRS.
     *
     * @return void
     */
    public function test_resting_pill_badge_edge_is_pinned(): void {
        $platter = $this->ground_token('.block_dimensions .block-dimensions-content .dims-filter-tabs');
        $this->assertNotNull($platter, 'The platter paints no plugin token, so there is nothing to measure against.');
        [$edges, $offenders] = $this->pill_badge_edges(false);
        $this->assertNotEmpty(
            $edges,
            'No rule draws a border on an unchecked pill\'s count badge, so its fill is its only outline, and '
                . 'surface on the platter is 1.19:1 in light.'
        );
        $offenders = array_merge($offenders, $this->unpinned_edges($edges, $platter));
        sort($offenders);
        $this->assertSame(
            [],
            $offenders,
            'An unchecked pill\'s count badge must be edged in a token PAIRS pins at 3:1 against the platter: '
                . implode('; ', $offenders)
        );
    }

    /**
     * The factor by which an opacity or filter declaration fades an element, when it can be read.
     *
     * CSS clamps both opacity and the opacity() filter function to 1, and an empty opacity()
     * means 1.
     *
     * @param string $property opacity or filter.
     * @param string $value Its value.
     * @return float|null The factor in 0..1, or null for a value this model cannot measure: a var(),
     *                    or a filter function other than opacity(), which changes colours in ways a
     *                    single factor does not describe.
     */
    private function fade_factor(string $property, string $value): ?float {
        $amount = static function (string $text): ?float {
            if (!preg_match('/^([0-9]*\.?[0-9]+)(%?)$/', trim($text), $m)) {
                return null;
            }

            return min(1.0, (float) $m[1] / ($m[2] === '%' ? 100 : 1));
        };
        $value = strtolower(trim($value));
        if ($property === 'opacity') {
            return $amount($value);
        }
        if ($value === 'none') {
            return 1.0;
        }
        $pattern = '/([a-z-]+)\(([^()]*)\)/';
        if (!preg_match_all($pattern, $value, $functions, PREG_SET_ORDER) || trim(preg_replace($pattern, '', $value)) !== '') {
            return null;
        }
        $factor = 1.0;
        foreach ($functions as [, $name, $argument]) {
            $part = trim($argument) === '' ? 1.0 : $amount($argument);
            if ($name !== 'opacity' || $part === null) {
                return null;
            }
            $factor *= $part;
        }

        return $factor;
    }

    /**
     * What the stylesheets paint and fade on an unchecked filter pill.
     *
     * A selector part reaches an unchecked pill when, outside :not(), it names neither .active nor
     * aria-checked="true" nor an inactive state (WCAG 1.4.3 exempts inactive text), and no :not()
     * excludes aria-checked="false". User-action states count, so a hover rule is read with the
     * resting one, and so does every at-rule but print. The subject decides what the part styles:
     * the pill itself (.dims-filter-tab, or a pill type's own class), its count badge, or the track
     * between the pill and the platter (.dims-filter-tabs-items, .dims-filter-tabs-mask), whose
     * opacity fades the pill with it. The lowest opacity found for each is kept, whatever state
     * sets it, so a fade on hover alone is measured against the resting colour too.
     *
     * @param array $paths Absolute stylesheet paths.
     * @return array With keys inks (pill and badge, each site => color value), fills (pill and
     *               badge, each site => background value, transparent left out), opacity (track,
     *               pill and badge => the lowest factor, 1.0 when none is set) and offenders (one
     *               message per fade the model cannot measure).
     */
    private function unchecked_pill_paint(array $paths): array {
        $paint = [
            'inks' => ['pill' => [], 'badge' => []],
            'fills' => ['pill' => [], 'badge' => []],
            'opacity' => ['track' => 1.0, 'pill' => 1.0, 'badge' => 1.0],
            'offenders' => [],
        ];
        $excluded = '/\.active(?![\w-])|\[aria-checked="true"\]|:disabled|\[aria-disabled|\.disabled(?![\w-])/';
        foreach ($paths as $path) {
            foreach ($this->rules($path) as $rule) {
                if (preg_match('/(?<![\w-])print(?![\w-])/', $rule['at'])) {
                    continue;
                }
                $declarations = $this->declarations($rule['body']);
                $where = $rule['file'] . ':' . $rule['line'];
                foreach (explode(',', $rule['selector']) as $part) {
                    $part = trim($part);
                    preg_match_all('/:not\(([^()]*)\)/', $part, $negations);
                    $positive = trim((string) preg_replace('/:not\([^()]*\)/', '', $part));
                    if (preg_match($excluded, $positive) || preg_match('/\[aria-checked="false"\]/', implode(' ', $negations[1]))) {
                        continue;
                    }
                    $compounds = preg_split('/\s*[>+~]\s*|\s+/', $positive);
                    $subject = (string) end($compounds);
                    if (preg_match('/\.dims-filter-tab(?![\w-])|\.dims-[a-z]+-filter-btn(?![\w-])/', $subject)) {
                        $role = 'pill';
                    } else if (preg_match('/\.dims-filter-count(?![\w-])/', $subject)) {
                        $role = 'badge';
                    } else if (preg_match('/\.dims-filter-tabs-(?:items|mask)(?![\w-])/', $subject)) {
                        $role = 'track';
                    } else {
                        continue;
                    }
                    $site = $where . ' ' . $part;
                    foreach ($declarations as $property => $value) {
                        if ($property === 'opacity' || $property === 'filter') {
                            $factor = $this->fade_factor($property, $value);
                            if ($factor === null) {
                                $paint['offenders'][] = $site . ' sets ' . $property . ': ' . $value
                                    . ', which the contrast model cannot measure';
                                continue;
                            }
                            $paint['opacity'][$role] = min($paint['opacity'][$role], $factor);
                        } else if ($role === 'track') {
                            continue;
                        } else if ($property === 'color') {
                            $paint['inks'][$role][$site] = $value;
                        } else if ($property === 'background' || $property === 'background-color') {
                            if (!in_array($value, ['transparent', 'none'], true)) {
                                $paint['fills'][$role][$site] = $value;
                            }
                        }
                    }
                }
            }
        }

        return $paint;
    }

    /**
     * An unchecked filter pill's label and count clear 4.5:1 on the platter, any opacity applied.
     *
     * The pills are enabled radio options, so their text takes the 4.5:1 floor (0.875rem at weight
     * 500, and the count's 0.75rem at 600, are normal-size text). PAIRS measures a token on a
     * token, but an opacity on the pill multiplies into its colour: at 0.8 the ink-muted label
     * falls to 4.07:1 on the platter in light, and the count to 4.42:1 on its badge. So every
     * colour that reaches an unchecked pill's label or count, resting or hovered, is resolved in
     * all three resolutions and measured through every opacity on its way to the platter, the
     * label against the platter (or a fill the pill paints) and the count against its badge's
     * fill. unchecked_pill_paint() says which rules count. The badge's border is pinned by
     * test_resting_pill_badge_edge_is_pinned().
     *
     * Two controls keep the test from passing blind: a fixture stylesheet with the 0.8 opacity must
     * be read as 0.8, and the arithmetic must fail the ink-muted label at that opacity.
     *
     * Changes that must make it fail, applied to styles.css: put opacity: 0.8 back on the tab rule,
     * or on its hover alone; write it as filter: opacity(0.8); set opacity: 0.8 on the count badge,
     * or on .dims-filter-tabs-items; give the unchecked label ink-faint.
     *
     * @return void
     */
    public function test_unchecked_pill_text_clears_aa_on_the_platter(): void {
        $fixture = make_request_directory() . '/styles.css';
        file_put_contents(
            $fixture,
            ".block_dimensions .block-dimensions-content .dims-filter-tab {\n"
                . "    color: var(" . self::PREFIX . "ink-muted);\n    opacity: 0.8;\n}\n"
                . ".block_dimensions .block-dimensions-content .dims-filter-tab.active {\n    opacity: 0.5;\n}\n"
        );
        $control = $this->unchecked_pill_paint([$fixture]);
        $this->assertSame(0.8, $control['opacity']['pill'], 'The model no longer reads a resting pill\'s opacity.');
        $this->assertCount(1, $control['inks']['pill'], 'The model no longer reads a resting pill\'s label colour.');
        $this->assertLessThan(
            4.5,
            (float) $this->faded_contrast(
                (string) $this->resolve('ink-muted', 'light'),
                (string) $this->resolve('surface-inset', 'light'),
                0.8
            ),
            'The arithmetic no longer lets an opacity fade the label, so it cannot catch one.'
        );

        $platter = $this->ground_token('.block_dimensions .block-dimensions-content .dims-filter-tabs');
        $this->assertNotNull($platter, 'The platter paints no plugin token, so there is nothing to measure against.');
        $paint = $this->unchecked_pill_paint($this->stylesheets());
        $this->assertNotEmpty($paint['inks']['pill'], 'No rule colours an unchecked pill\'s label, so this test checks nothing.');
        $this->assertNotEmpty($paint['inks']['badge'], 'No rule colours an unchecked pill\'s count, so this test checks nothing.');
        $this->assertNotEmpty($paint['fills']['badge'], 'No rule fills an unchecked pill\'s count, so this test checks nothing.');

        $fade = $paint['opacity']['track'] * $paint['opacity']['pill'];
        $measures = [];
        foreach ($paint['inks']['pill'] as $site => $ink) {
            foreach (array_merge([null], array_values($paint['fills']['pill'])) as $fill) {
                $measures[] = [$site, $ink, $fill, $fade];
            }
        }
        foreach ($paint['inks']['badge'] as $site => $ink) {
            foreach ($paint['fills']['badge'] as $fill) {
                $measures[] = [$site, $ink, $fill, $fade * $paint['opacity']['badge']];
            }
        }

        $tokenpattern = '/^var\(' . preg_quote(self::PREFIX, '/') . '([a-z0-9-]+)\)$/';
        $offenders = $paint['offenders'];
        foreach (['light', 'dark', 'bs4'] as $mode) {
            $ground = (string) $this->resolve($platter, $mode);
            foreach ($measures as [$site, $ink, $fill, $opacity]) {
                $istoken = preg_match($tokenpattern, $ink, $inktoken)
                    && ($fill === null || preg_match($tokenpattern, $fill, $filltoken));
                if (!$istoken) {
                    $offenders[] = $site . ' paints ' . $ink . ($fill === null ? '' : ' on ' . $fill)
                        . ', which is not one plugin token';
                    continue;
                }
                $ratio = $this->faded_contrast(
                    (string) $this->resolve($inktoken[1], $mode),
                    $ground,
                    $opacity,
                    $fill === null ? null : (string) $this->resolve($filltoken[1], $mode)
                );
                if ($ratio === null || $ratio < 4.5) {
                    $offenders[] = sprintf(
                        '%s: %s (%s) on %s at opacity %.2f is %.3f:1, floor 4.5',
                        $mode,
                        $inktoken[1],
                        $site,
                        $fill === null ? $platter : $filltoken[1],
                        $opacity,
                        (float) $ratio
                    );
                }
            }
        }
        $offenders = array_values(array_unique($offenders));
        sort($offenders);
        $this->assertSame(
            [],
            $offenders,
            'An unchecked pill\'s label and count must clear 4.5:1 in every resolution, through any opacity on the '
                . 'pill: ' . implode('; ', $offenders)
        );
    }

    /* --------------------------------------------------------------------------------------- */
    /* The focus indicator.                                                                    */
    /* --------------------------------------------------------------------------------------- */

    /**
     * Whether a set of declarations draws a real, visible outline.
     *
     * @param array $declarations Property => value, as returned by declarations().
     * @return bool True when an outline is drawn that forced-colors mode would paint.
     */
    private function draws_an_outline(array $declarations): bool {
        foreach (['outline', 'outline-style', 'outline-width'] as $property) {
            if (!isset($declarations[$property])) {
                continue;
            }
            $value = strtolower($declarations[$property]);
            if ($value === 'none' || $value === '0' || $value === 'hidden') {
                continue;
            }
            if ($property === 'outline' && preg_match('/\b(none|hidden)\b/', $value)) {
                continue;
            }
            if ($property === 'outline' && preg_match('/(^|\s)0(\s|$)/', $value)) {
                continue;
            }

            return true;
        }

        return false;
    }

    /**
     * A focus indicator must survive forced-colors mode, so it must be a real outline.
     *
     * Two offences: a focus rule that switches the outline off without drawing another one, and a
     * focus rule whose only visible signal is a box-shadow. Forced-colors mode (Windows High
     * Contrast) does not render box-shadow at all and does not restore an author's outline: none,
     * so either leaves a keyboard user with no focus indicator.
     *
     * Change that must make it fail: revert a filter-tab focus rule to outline: none plus an inset
     * box-shadow.
     *
     * @return void
     */
    public function test_focus_indicators_survive_forced_colors(): void {
        $offenders = [];
        foreach ($this->stylesheets() as $path) {
            foreach ($this->rules($path) as $rule) {
                if (!str_contains($rule['selector'], ':focus')) {
                    continue;
                }
                $declarations = $this->declarations($rule['body']);
                $drawsoutline = $this->draws_an_outline($declarations);
                $where = $rule['file'] . ':' . $rule['line'];
                $suppresses = false;
                foreach (['outline', 'outline-style', 'outline-width'] as $property) {
                    if (!isset($declarations[$property])) {
                        continue;
                    }
                    $value = strtolower($declarations[$property]);
                    if (
                        $value === 'none'
                        || $value === '0'
                        || $value === 'hidden'
                        || preg_match('/\b(none|hidden)\b/', $value)
                    ) {
                        $suppresses = true;
                    }
                }
                if ($suppresses && !$drawsoutline) {
                    $offenders[] = $where . ' switches the outline off and draws no other one';
                    continue;
                }
                if ($drawsoutline || !isset($declarations['box-shadow'])) {
                    continue;
                }
                if (strtolower($declarations['box-shadow']) === 'none') {
                    continue;
                }
                /*
                 * A shadow beside a colour, a border, an underline or a movement is a decoration
                 * on top of a signal forced-colors does paint; a shadow on its own is the whole
                 * signal. transform and opacity are in the list because forced-colors substitutes
                 * a palette and does not stop an element moving or appearing.
                 */
                $othersignals = ['color', 'background', 'background-color', 'border', 'border-color',
                    'border-bottom-color', 'border-left-color', 'border-right-color', 'border-top-color',
                    'border-bottom', 'border-left', 'border-right', 'border-top', 'text-decoration',
                    'text-decoration-color', 'fill', 'stroke', 'transform', 'opacity'];
                if (!array_intersect($othersignals, array_keys($declarations))) {
                    $offenders[] = $where . ' signals focus with a box-shadow only';
                }
            }
        }
        sort($offenders);
        $this->assertSame(
            [],
            $offenders,
            'box-shadow is not painted in forced-colors mode, so a focus indicator has to be a real '
                . 'outline: ' . implode('; ', $offenders)
        );
    }

    /**
     * A focus ring may never be drawn in the brand colour, nor in a literal.
     *
     * Checks every outline declaration and every box-shadow in a :focus rule. The ring uses the
     * focus-ring token, which chains --bs-emphasis-color and so flips with the mode. Core's
     * --bs-focus-ring-color is not redefined for dark mode (rgba(15, 108, 191, 0.25), 1.26:1
     * against the dark page), and the brand and link colours are chosen per site, so a 3:1 ring
     * cannot depend on either. currentcolor is allowed for a ring on a branded island, whose
     * ground does not change with the mode.
     *
     * Change that must make it fail: point one focus ring at the accent token, or at a literal.
     *
     * @return void
     */
    public function test_focus_ring_is_never_brand_coloured(): void {
        $banned = [
            self::PREFIX . 'accent',
            self::PREFIX . 'brand-fill',
            '--bs-primary',
            '--bs-focus-ring-color',
            '--dimension-custombgcolor',
            '--primary',
        ];
        $offenders = [];
        foreach ($this->stylesheets() as $path) {
            foreach ($this->rules($path) as $rule) {
                $isfocus = str_contains($rule['selector'], ':focus');
                foreach ($this->declarations($rule['body']) as $property => $value) {
                    $isoutline = $property === 'outline' || str_starts_with($property, 'outline-');
                    if (!$isoutline && !($isfocus && $property === 'box-shadow')) {
                        continue;
                    }
                    $where = $rule['file'] . ':' . $rule['line'] . ' ' . $property;
                    foreach ($banned as $name) {
                        /* Match the whole custom-property name, never a longer one starting with it. */
                        if (preg_match('/' . preg_quote($name, '/') . '(?![-\w])/', $value)) {
                            $offenders[] = $where . ' reads ' . $name;
                        }
                    }
                    if (preg_match('/#[0-9a-f]{3,8}\b|\b(rgba?|hsla?)\s*\(/i', $value)) {
                        $offenders[] = $where . ' uses a colour literal';
                    }
                }
            }
        }
        sort($offenders);
        $this->assertSame(
            [],
            $offenders,
            'A focus ring must resolve to ' . self::PREFIX . 'focus-ring (or currentcolor on a branded '
                . 'island), never to the brand or a literal: ' . implode('; ', $offenders)
        );
    }

    /* --------------------------------------------------------------------------------------- */
    /* The admin-configured colours and their islands.                                         */
    /* --------------------------------------------------------------------------------------- */

    /**
     * The mode layer never declares an admin-configured colour, in either mode.
     *
     * No rule may declare a name in ADMIN_COLOUR_NAMES, mode rules included. The admin's colours
     * are instance data, not design tokens: the stylesheet reads them and never owns them, so a
     * site's chosen colour stays its colour when the page goes dark. The final assertion checks
     * that the stylesheet still reads the two card properties, so the ban is not over names
     * nothing uses.
     *
     * Changes that must make it fail: declare --dimension-customtextcolor in the dark block;
     * declare any of these names anywhere in the stylesheet.
     *
     * @return void
     */
    public function test_admin_colours_are_never_declared_by_the_mode_layer(): void {
        $offenders = [];
        foreach ($this->stylesheets() as $path) {
            foreach ($this->rules($path) as $rule) {
                $ismode = str_contains($rule['selector'], colour_mode::HOST_ATTRIBUTE)
                    || str_contains($rule['selector'], colour_mode::MEDIA_OPTIN_ATTRIBUTE);
                foreach (array_keys($this->declarations($rule['body'])) as $property) {
                    if (!in_array($property, self::ADMIN_COLOUR_NAMES, true)) {
                        continue;
                    }
                    $offenders[] = $rule['file'] . ':' . $rule['line'] . ' declares ' . $property
                        . ($ismode ? ' inside a mode rule' : '');
                }
            }
        }
        sort($offenders);
        $this->assertSame(
            [],
            $offenders,
            'These are admin instance data carried in on the element; the stylesheet reads them and never '
                . 'declares them: ' . implode('; ', $offenders)
        );
        $referenced = [];
        foreach ($this->stylesheets() as $path) {
            $css = $this->uncommented($path);
            foreach (['--dimension-custombgcolor', '--dimension-customtextcolor'] as $name) {
                if (str_contains($css, 'var(' . $name)) {
                    $referenced[] = $name;
                }
            }
        }
        $this->assertSame(
            ['--dimension-custombgcolor', '--dimension-customtextcolor'],
            array_values(array_unique($referenced)),
            'The stylesheet must still READ the admin transport properties, or this ban is guarding names '
                . 'nothing uses.'
        );
    }

    /**
     * The admin colour transport survives from the template to the stylesheet.
     *
     * Each of the three card roots in plan_card and competency_card emits the admin's colours as
     * custom properties in the article's inline style, inside the hasbgcolor / hastextcolor
     * sections, and styles.css reads them from there. No other gate checks that the templates and
     * the stylesheet agree on those names.
     *
     * Change that must make it fail: remove --dimension-customtextcolor from a card template.
     *
     * @return void
     */
    public function test_admin_colour_transport_is_intact(): void {
        $expected = ['hasbgcolor' => '--dimension-custombgcolor', 'hastextcolor' => '--dimension-customtextcolor'];
        $offenders = [];
        $sites = 0;
        foreach (glob($this->plugin_root() . '/templates/*.mustache') ?: [] as $path) {
            foreach ($this->source_lines($path) as $number => $line) {
                if ($this->is_comment_line($line) || !str_contains($line, 'style="')) {
                    /* Only an inline style attribute is a transport site. The same conditional
                       section also toggles a class name a few lines below, and counting that as a
                       transport site would make the total meaningless. */
                    continue;
                }
                foreach ($expected as $section => $property) {
                    if (!str_contains($line, '{{#' . $section . '}}')) {
                        continue;
                    }
                    $sites++;
                    if (!preg_match('/\{\{#' . $section . '\}\}\s*' . preg_quote($property, '/') . '\s*:/', $line)) {
                        $offenders[] = basename($path) . ':' . ($number + 1) . ' opens {{#' . $section
                            . '}} without emitting ' . $property;
                    }
                }
            }
        }
        $this->assertSame(
            6,
            $sites,
            'Three card roots each carry both admin colour sections, so six transport sites are expected; '
                . 'found ' . $sites . '. A missing one means a card stopped carrying the admin\'s colour.'
        );
        sort($offenders);
        $this->assertSame(
            [],
            $offenders,
            'An admin colour section that emits no custom property is a colour the stylesheet can never '
                . 'read: ' . implode('; ', $offenders)
        );
    }

    /**
     * Inside a branded island, nothing reads a mode token.
     *
     * An island is a surface painted with a colour the admin chose. Inside it "adapt" means
     * relative to that colour, not relative to the page, so a mode token there is measuring
     * against the wrong ground - and the island does not go dark when the page does, correctly,
     * because the admin's colour did not change.
     *
     * Change that must make it fail: paint an island with an ink or surface token.
     *
     * @return void
     */
    public function test_branded_islands_use_no_mode_token(): void {
        $modetokens = [];
        foreach (['surface', 'surface-alt', 'surface-inset', 'ink', 'ink-muted', 'ink-faint', 'ink-strong', 'line'] as $s) {
            $modetokens[] = self::PREFIX . $s;
        }
        $offenders = [];
        $reached = 0;
        foreach ($this->stylesheets() as $path) {
            foreach ($this->rules($path) as $rule) {
                $onisland = false;
                foreach (self::ISLAND_ROOTS as $island) {
                    if (str_contains($rule['selector'], $island)) {
                        $onisland = true;
                    }
                }
                if (!$onisland) {
                    continue;
                }
                $reached++;
                foreach ($this->declarations($rule['body']) as $property => $value) {
                    foreach ($modetokens as $token) {
                        if (preg_match('/' . preg_quote($token, '/') . '(?![-\w])/', $value)) {
                            $offenders[] = $rule['file'] . ':' . $rule['line'] . ' ' . $property
                                . ' reads ' . $token;
                        }
                    }
                }
            }
        }
        $this->assertGreaterThan(
            0,
            $reached,
            'No rule matched a documented island root, so this ban is scanning nothing; check ISLAND_ROOTS '
                . 'against the selectors that actually paint an admin colour.'
        );
        sort($offenders);
        $this->assertSame(
            [],
            $offenders,
            'A branded island is measured against the admin\'s own colour, never against the page, so no '
                . 'mode token may appear inside one: ' . implode('; ', $offenders)
        );
    }

    /* --------------------------------------------------------------------------------------- */
    /* The host signal, the faint ink and the token reads.                                     */
    /* --------------------------------------------------------------------------------------- */

    /**
     * The plugin never writes the host's colour-mode signal.
     *
     * The attribute is the host's to write; the plugin only reads it, from CSS, because whether the
     * page is dark cannot be known server-side ({@see \block_dimensions\local\colour_mode}). Behat
     * steps are exempt because a scenario plays the host's part. Comment lines are skipped.
     *
     * Changes that must make it fail: setAttribute('data-bs-theme', ...) in an AMD module; move the
     * Behat step's script body into one.
     *
     * @return void
     */
    public function test_plugin_never_writes_the_host_signal(): void {
        $offenders = [];
        foreach ($this->source_files() as $path) {
            if (basename($path) === 'colour_mode.php') {
                /* The constant declaration is the contract's own dictionary, not a writer. */
                continue;
            }
            if (str_contains($path, '/tests/behat/')) {
                continue;
            }
            $relative = str_replace($this->plugin_root() . '/', '', $path);
            foreach ($this->source_lines($path) as $number => $line) {
                if ($this->is_comment_line($line)) {
                    continue;
                }
                if (str_contains($line, colour_mode::HOST_ATTRIBUTE)) {
                    $offenders[] = $relative . ':' . ($number + 1);
                }
            }
        }
        sort($offenders);
        $this->assertSame(
            [],
            $offenders,
            colour_mode::HOST_ATTRIBUTE . ' belongs to the host page. The plugin reads it from CSS and must '
                . 'never write it, because whether the page is dark is not knowable server-side: '
                . implode(', ', $offenders)
        );
    }

    /**
     * ink-faint is the colour of an inactive control, and of nothing else.
     *
     * It measures 3.07:1 on surface-inset and 3.16:1 on surface-alt in light mode, below the 4.5:1
     * text floor. WCAG 1.4.3 exempts the text of inactive components, so the token may only set
     * color, and only in a disabled, read-only or placeholder state.
     *
     * Changes that must make it fail: use it as a border colour; use it as the colour of an
     * ordinary caption.
     *
     * @return void
     */
    public function test_ink_faint_is_only_inactive_text(): void {
        $token = self::PREFIX . 'ink-faint';
        $inactive = [':disabled', '[aria-disabled', '::placeholder', ':read-only', '.disabled'];
        $offenders = [];
        foreach ($this->stylesheets() as $path) {
            foreach ($this->rules($path) as $rule) {
                foreach ($this->declarations($rule['body']) as $property => $value) {
                    if (!preg_match('/' . preg_quote($token, '/') . '(?![-\w])/', $value)) {
                        continue;
                    }
                    $where = $rule['file'] . ':' . $rule['line'];
                    if ($property !== 'color') {
                        $offenders[] = $where . ' uses it on ' . $property . ', which has no incidental exception';
                        continue;
                    }
                    $isinactive = false;
                    foreach ($inactive as $marker) {
                        if (str_contains($rule['selector'], $marker)) {
                            $isinactive = true;
                        }
                    }
                    if (!$isinactive) {
                        $offenders[] = $where . ' paints active text (' . $rule['selector'] . ')';
                    }
                }
            }
        }
        sort($offenders);
        $ratio = $this->contrast((string) $this->resolve('ink-faint', 'light'), (string) $this->resolve('surface-inset', 'light'));
        $this->assertSame(
            [],
            $offenders,
            sprintf('%s is %.2f:1 on the inset surface in light mode, below the 4.5:1 text floor, and is ', $token, $ratio)
                . 'legitimate only as the text of an inactive control or a placeholder: ' . implode('; ', $offenders)
        );
    }

    /**
     * Every token read names a token the contract declares.
     *
     * Scans the stylesheets and amd/src. The plugin reads its tokens without a var() fallback, so a
     * mistyped name makes the whole declaration invalid at computed-value time: the property is
     * unset (no background, or the inherited text colour) and no other gate reports it.
     *
     * Change that must make it fail: misspell a token name at any consumption site.
     *
     * @return void
     */
    public function test_every_token_read_is_declared(): void {
        $declared = array_keys($this->token_block());
        $offenders = [];
        $paths = $this->stylesheets();
        foreach (glob($this->plugin_root() . '/amd/src/*.js') ?: [] as $path) {
            $paths[] = $path;
        }
        foreach ($paths as $path) {
            $text = str_ends_with($path, '.css') ? $this->uncommented($path) : file_get_contents($path);
            if (!preg_match_all('/var\(\s*(' . preg_quote(self::PREFIX, '/') . '[a-z0-9-]+)/i', $text, $matches)) {
                continue;
            }
            foreach (array_unique($matches[1]) as $name) {
                if (!in_array($name, $declared, true)) {
                    $offenders[] = basename($path) . ' reads ' . $name;
                }
            }
        }
        sort($offenders);
        $this->assertSame(
            [],
            $offenders,
            'An undeclared token is not a wrong colour, it is no colour: the declaration is invalid at '
                . 'computed-value time and the property goes unset: ' . implode('; ', $offenders)
        );
    }

    /**
     * Mustache comments do not reach the line scans as markup.
     *
     * A template docblock is one comment spanning many lines, and its continuation lines carry no
     * comment marker of their own, so a scan that only skipped the opening line would read the
     * prose as markup: a docblock naming the host attribute would count as writing it.
     *
     * Changes that must make it fail: make strip_mustache_comments() return its input unchanged;
     * make its pattern greedy, which also blanks the markup between two comments.
     *
     * @return void
     */
    public function test_mustache_comments_are_not_scanned_as_markup(): void {
        $template = "{{!\n    Prose naming " . colour_mode::HOST_ATTRIBUTE . " and style=\"x\".\n}}\n"
            . "<div class=\"a\">{{! Inline. }}<span style=\"b\"></span></div>\n"
            . "{{! Second comment. }}\n";

        $this->assertSame(
            ['', '', '', '<div class="a"><span style="b"></span></div>', '', ''],
            explode("\n", $this->strip_mustache_comments($template)),
            'Every comment span is blanked, its line breaks kept, and the markup around it stays on its line.'
        );
    }

    /**
     * Each rule's reported line is the line its selector starts on.
     *
     * The failure messages in this file cite the file:line that rules() reports. The text between
     * the previous brace and a selector opens with line breaks, so a line counted from that brace
     * points above the rule.
     *
     * Change that must make it fail: count the line in rules() from $selectorstart again.
     *
     * @return void
     */
    public function test_rule_lines_point_at_their_selector(): void {
        $checked = 0;
        $offenders = [];
        foreach ($this->stylesheets() as $path) {
            $lines = explode("\n", $this->uncommented($path));
            foreach ($this->rules($path) as $rule) {
                $checked++;
                $source = explode('{', $lines[$rule['line'] - 1] ?? '')[0];
                $source = trim(preg_replace('/\s+/', ' ', $source));
                if ($source === '' || !str_starts_with($rule['selector'], $source)) {
                    $offenders[] = $rule['file'] . ':' . $rule['line'] . ' reads "' . $source . '", not the start of '
                        . $rule['selector'];
                }
            }
        }

        $this->assertGreaterThan(0, $checked, 'No rule was found, so this test checks nothing.');
        $this->assertSame([], $offenders, 'A rule is reported on a line that is not its selector: ' . implode('; ', $offenders));
    }

    /**
     * A template with its Mustache comments blanked, line breaks preserved.
     *
     * A comment runs from its opening marker to the first closing pair of braces, as Mustache
     * reads it, so a docblock is blanked as a whole and markup after it keeps its line number.
     *
     * @param string $text Template source.
     * @return string The source with every comment replaced by its own line breaks.
     */
    private function strip_mustache_comments(string $text): string {
        return (string) preg_replace_callback('/\{\{!.*?\}\}/s', static function (array $m): string {
            return str_repeat("\n", substr_count($m[0], "\n"));
        }, $text);
    }

    /**
     * The lines of a source file, with Mustache comments blanked in a template.
     *
     * @param string $path Absolute path to a source file.
     * @return array Zero-based line index => line text.
     */
    private function source_lines(string $path): array {
        $text = (string) file_get_contents($path);
        if (str_ends_with($path, '.mustache')) {
            $text = $this->strip_mustache_comments($text);
        }

        return explode("\n", $text);
    }

    /**
     * Whether a line is prose rather than markup.
     *
     * Mustache comments never reach this check: source_lines() has already blanked them.
     *
     * @param string $line One source line.
     * @return bool True when the line is blank or opens with a PHP or JS comment marker.
     */
    private function is_comment_line(string $line): bool {
        $trimmed = ltrim($line);

        return $trimmed === ''
            || str_starts_with($trimmed, '//')
            || str_starts_with($trimmed, '/*')
            || str_starts_with($trimmed, '*');
    }
}
