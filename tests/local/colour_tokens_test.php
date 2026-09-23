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
     * Deliberately absent: accent, brand-ink and danger-ink as normal text on surface-inset, which
     * measure 4.50, 4.50 and 4.20 in dark with core's own values, so
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
        ['brand-ink', 'brand-tint', 4.5],
        ['success-ink', 'success-tint', 4.5],
        ['warning-ink', 'warning-tint', 4.5],
        ['danger-ink', 'danger-tint', 4.5],
        ['info-ink', 'info-tint', 4.5],
        ['neutral-ink', 'neutral-tint', 4.5],
    ];

    /** @var array Tokens that may not be normal-size text on surface-inset (dark: 4.50, 4.50, 4.20). */
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
            'why' => 'prefers-contrast flat fill: a MIDDLE, 4.69:1 on the light card face and 3.45:1 on the dark one.',
        ],
        [
            'selector' => '.competency-card',
            'property' => 'border',
            'value' => '#000',
            'why' => 'Print. Paper is white whatever the screen is doing.',
        ],
        [
            'selector' => '.competency-card-gradient',
            'property' => 'background',
            'value' => '#ced4da',
            'why' => 'Print. Paper is white whatever the screen is doing.',
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
                $selector = trim(substr($css, $selectorstart, $i - $selectorstart));
                $line = substr_count(substr($css, 0, $selectorstart), "\n") + 1;
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
        $lighter = max($this->luminance($fore), $this->luminance($back));
        $darker = min($this->luminance($fore), $this->luminance($back));

        return ($lighter + 0.05) / ($darker + 0.05);
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
             * The sibling is installed but has no token block, so it has not adopted the contract
             * yet: CI checks it out from its default branch, which can lag this plugin. That is an
             * adoption gap rather than a divergence, so the test skips. Once adopted, a divergence
             * fails here, and a deleted block fails the sibling's own
             * test_token_block_declares_exactly_the_contract().
             */
            $this->markTestSkipped(
                self::SIBLING . ' is installed but declares no ' . self::SIBLING_PREFIX
                    . ' token block, so it has not adopted the family colour contract yet and there is '
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
            'The host signal is read only from the html element, and no other dark mechanism may live '
                . 'beside it: ' . implode('; ', $offenders)
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
     * accent and brand-ink measure 4.50:1 there in dark and danger-ink 4.20:1, with core's own
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
            'accent, brand-ink and danger-ink measure 4.50, 4.50 and 4.20 against surface-inset on the dark '
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
     * changes, not only when the constants in this file do.
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
                if ($ratio + 0.005 < $floor) {
                    $offenders[] = sprintf(
                        '%s: %s (%s) on %s (%s) is %.2f:1, floor %.1f',
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
            foreach (file($path) as $number => $line) {
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
            foreach (file($path) as $number => $line) {
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
        $this->assertSame(
            [],
            $offenders,
            $token . ' is 2.70:1 on the inset surface in light mode and is legitimate only as the text of '
                . 'an inactive control or a placeholder: ' . implode('; ', $offenders)
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
     * Whether a line is prose rather than markup.
     *
     * @param string $line One raw source line.
     * @return bool True when the line is blank or opens with a PHP, JS or Mustache comment marker.
     */
    private function is_comment_line(string $line): bool {
        $trimmed = ltrim($line);

        return $trimmed === ''
            || str_starts_with($trimmed, '//')
            || str_starts_with($trimmed, '/*')
            || str_starts_with($trimmed, '*')
            || str_starts_with($trimmed, '{{!');
    }
}
