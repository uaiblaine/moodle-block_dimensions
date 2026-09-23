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
 * Guards the plugin's Bootstrap 4 / Bootstrap 5 contract.
 *
 * Moodle 4.5 ships Bootstrap 4 and 5.0+ ship Bootstrap 5, and the bridging is asymmetric:
 * 4.5's forward bridge (theme/boost/scss/moodle/bs5-bridge.scss) covers only g-0, btn-close,
 * the ms/me/ps/pe spacers and float/text/border/rounded-start/end, so any other BS5 utility
 * resolves to nothing on 4.5. BS4 names do resolve on 5.x, but only through bs4-compat.scss,
 * which wraps each in a deprecated-styles mixin and which Moodle 6.0 removes (MDL-84465). So
 * the BS5 name plus a gated polyfill is correct on both branches.
 *
 * No other gate sees a class name that resolves to nothing: phpcs, the mustache lint and
 * stylelint never check class names in Mustache or JS files.
 *
 * @package    block_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_dimensions\local\bootstrap
 */
final class bootstrap_compat_test extends \basic_testcase {
    /**
     * Bootstrap 5 utilities that do not exist on Moodle 4.5.
     *
     * Each entry maps a regular expression matching the class in a class attribute or a JS class
     * string to the human-readable family name reported when it is found unpolyfilled. Source of
     * truth for what 4.5 does bridge: theme/boost/scss/moodle/bs5-bridge.scss.
     *
     * @return array Regex => family label.
     */
    private function bs5_only_utilities(): array {
        return [
            '/\bvisually-hidden\b/' => 'visually-hidden',
            '/\bform-select(-sm)?\b/' => 'form-select',
            '/\bgap-[0-9]\b/' => 'gap-*',
            '/\bfw-(bold|medium|normal|semibold|light)\b/' => 'fw-*',
            '/\bfont-monospace\b/' => 'font-monospace',
            '/\bform-switch\b/' => 'form-switch',
            '/\bform-label\b/' => 'form-label',
        ];
    }

    /**
     * Bootstrap 4 class names that 5.x resolves only through its deprecated compatibility sheet.
     *
     * Each is paired with the BS5 spelling to write instead; see
     * test_no_deprecated_bootstrap4_class_names() for why.
     *
     * The patterns require the token to stand alone: without the lookarounds, border-left would
     * match a CSS property name in a JS style string and text-right would match inside a longer
     * class token.
     *
     * @return array Regex => the BS5 spelling to use instead.
     */
    private function deprecated_bs4_utilities(): array {
        return [
            '/(?<![-\w])sr-only(?![-\w])/' => 'visually-hidden',
            '/(?<![-\w])ml-([0-9]|auto)(?![-\w])/' => 'ms-*',
            '/(?<![-\w])mr-([0-9]|auto)(?![-\w])/' => 'me-*',
            '/(?<![-\w])pl-([0-9]|auto)(?![-\w])/' => 'ps-*',
            '/(?<![-\w])pr-([0-9]|auto)(?![-\w])/' => 'pe-*',
            '/(?<![-\w])text-left(?![-\w])/' => 'text-start',
            '/(?<![-\w])text-right(?![-\w])/' => 'text-end',
            '/(?<![-\w])float-left(?![-\w])/' => 'float-start',
            '/(?<![-\w])float-right(?![-\w])/' => 'float-end',
            '/(?<![-\w])border-left(?![-\w])/' => 'border-start',
            '/(?<![-\w])border-right(?![-\w])/' => 'border-end',
            '/(?<![-\w])rounded-left(?![-\w])/' => 'rounded-start',
            '/(?<![-\w])rounded-right(?![-\w])/' => 'rounded-end',
            '/(?<![-\w])no-gutters(?![-\w])/' => 'g-0',
        ];
    }

    /**
     * Saturated background utilities that need an explicit light text colour.
     *
     * Bootstrap 4's .badge sets no colour at all, so a saturated badge renders near-black text
     * on a dark fill; Bootstrap 5's .badge defaults to white, so a light background renders white
     * on near-white. bg-success gives 3.07:1 on 4.5 and bg-secondary 1.49:1 on 5.2, against the
     * 4.5:1 AA floor, so only markup that states its text colour is correct on both branches.
     *
     * @return array Background utility => the text utility it requires.
     */
    private function badge_text_colours(): array {
        return [
            'bg-success' => 'text-white',
            'bg-primary' => 'text-white',
            'bg-danger' => 'text-white',
            'bg-info' => 'text-white',
            'bg-dark' => 'text-white',
            'bg-secondary' => 'text-dark',
            'bg-warning' => 'text-dark',
        ];
    }

    /**
     * Absolute path to the plugin root.
     *
     * @return string Plugin directory without a trailing separator.
     */
    private function plugin_root(): string {
        return dirname(__DIR__, 2);
    }

    /**
     * Every file whose contents can put a class name in front of a user.
     *
     * Skips amd/build (generated from amd/src) and docs (not shipped, and .gitattributes keeps it
     * out of the release zip).
     *
     * @return array List of absolute file paths.
     */
    private function markup_files(): array {
        $root = $this->plugin_root();
        $dirs = [$root . '/templates', $root . '/amd/src', $root . '/classes'];
        $files = [];
        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                continue;
            }
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
            foreach ($iterator as $file) {
                if (!$file->isFile()) {
                    continue;
                }
                if (!in_array($file->getExtension(), ['mustache', 'js', 'php'], true)) {
                    continue;
                }
                $files[] = $file->getPathname();
            }
        }
        foreach (glob($root . '/*.php') ?: [] as $path) {
            $files[] = $path;
        }
        sort($files);
        return $files;
    }

    /**
     * Whether a line is prose rather than markup.
     *
     * The rules below are about what reaches the browser. A comment that names a class in order
     * to explain the rule is not a violation of it.
     *
     * @param string $line One raw source line.
     * @return bool True when the line opens with a PHP, JS or Mustache comment marker.
     */
    private function is_comment_line(string $line): bool {
        $trimmed = ltrim($line);

        return $trimmed === ''
            || str_starts_with($trimmed, '//')
            || str_starts_with($trimmed, '/*')
            || str_starts_with($trimmed, '*')
            || str_starts_with($trimmed, '{{!');
    }

    /**
     * The exact class tokens the polyfill block defines behind the Bootstrap 4 gate.
     *
     * Token-level, not family-level: a family-level check ("is gap-* covered?") passes while
     * gap-2 alone is missing.
     *
     * @return array List of class tokens, e.g. gap-2, without the leading dot.
     */
    private function polyfilled_tokens(): array {
        $css = file_get_contents($this->plugin_root() . '/styles.css');
        /* Strip comments first: the block's own prose names files and classes it does not define. */
        $css = preg_replace('~/\*.*?\*/~s', '', $css);
        $gate = preg_quote(bootstrap::ROOT_CLASS_BS4, '/');
        $tokens = [];
        foreach (explode('}', $css) as $block) {
            $selector = explode('{', $block)[0];
            if (!preg_match('/\.' . $gate . '\b/', $selector)) {
                continue;
            }
            preg_match_all('/\.([a-z][a-z0-9-]*)/', $selector, $matches);
            foreach ($matches[1] as $token) {
                $tokens[] = $token;
            }
        }
        return array_values(array_unique($tokens));
    }

    /**
     * The exact Bootstrap 5 class tokens the plugin emits that 4.5 does not define.
     *
     * @return array Token => list of file basenames using it.
     */
    private function used_bs5_tokens(): array {
        $used = [];
        foreach ($this->markup_files() as $path) {
            foreach (file($path) as $line) {
                if ($this->is_comment_line($line)) {
                    continue;
                }
                foreach ($this->bs5_only_utilities() as $pattern => $unusedlabel) {
                    if (!preg_match_all($pattern, $line, $matches)) {
                        continue;
                    }
                    foreach ($matches[0] as $token) {
                        $used[$token][basename($path)] = true;
                    }
                }
            }
        }
        return array_map('array_keys', $used);
    }

    /**
     * Every Bootstrap 5 class the plugin emits must be defined by the polyfill for 4.5.
     *
     * @return void
     */
    public function test_every_bs5_utility_used_is_polyfilled(): void {
        $polyfilled = $this->polyfilled_tokens();
        $missing = [];
        foreach ($this->used_bs5_tokens() as $token => $files) {
            if (!in_array($token, $polyfilled, true)) {
                $missing[] = $token . ' (used in ' . implode(', ', array_slice($files, 0, 3)) . ')';
            }
        }
        sort($missing);
        $this->assertSame(
            [],
            $missing,
            'These Bootstrap 5 classes are used but resolve to nothing on Moodle 4.5. Either add them '
                . 'to the Bootstrap 4 utility polyfill at the tail of styles.css, or stop using them: '
                . implode('; ', $missing)
        );
    }

    /**
     * The polyfill must not grow rules for classes the plugin no longer uses.
     *
     * A compatibility layer that outlives its callers is how a temporary shim becomes permanent.
     *
     * @return void
     */
    public function test_polyfill_carries_nothing_unused(): void {
        $used = array_keys($this->used_bs5_tokens());
        /* The gate itself is a marker, not a utility: nothing names it in markup by hand. */
        $structural = [bootstrap::ROOT_CLASS_BS4];
        $unused = array_values(array_diff($this->polyfilled_tokens(), $used, $structural));
        sort($unused);
        $this->assertSame(
            [],
            $unused,
            'The Bootstrap 4 polyfill defines classes nothing uses any more; delete them: '
                . implode(', ', $unused)
        );
    }

    /**
     * No Bootstrap 4 class name may be written, even beside its Bootstrap 5 spelling.
     *
     * 5.x resolves these only through bs4-compat.scss, which wraps every one in a
     * deprecated-styles mixin - a red outline under behat-site and themedesignermode - and
     * which Moodle 6.0 deletes outright. Every replacement listed here resolves on 4.5 too:
     * visually-hidden through this plugin's polyfill, the rest through core's own forward bridge.
     * So the BS5 name alone is correct on both branches, and writing the pair buys nothing and
     * costs the deprecation.
     *
     * @return void
     */
    public function test_no_deprecated_bootstrap4_class_names(): void {
        $offenders = [];
        foreach ($this->markup_files() as $path) {
            foreach (file($path) as $number => $line) {
                if ($this->is_comment_line($line)) {
                    continue;
                }
                foreach ($this->deprecated_bs4_utilities() as $pattern => $replacement) {
                    if (!preg_match($pattern, $line, $matches)) {
                        continue;
                    }
                    $offenders[] = basename($path) . ':' . ($number + 1) . ' writes ' . $matches[0]
                        . ', use ' . $replacement;
                }
            }
        }
        sort($offenders);
        $this->assertSame(
            [],
            $offenders,
            'These Bootstrap 4 class names reach 5.x only through the deprecated compatibility sheet '
                . 'Moodle 6.0 removes; the Bootstrap 5 spelling is correct on both branches: '
                . implode('; ', $offenders)
        );
    }

    /**
     * Every badge background must state its text colour, so it reads on both branches.
     *
     * @return void
     */
    public function test_badges_state_their_text_colour(): void {
        /*
         * Checked on every line carrying a background utility, not only lines that also say
         * "badge": a match arm returning a bare 'bg-success' names no badge, while the method
         * that makes it a badge colour does, a line or more above.
         */
        $offenders = [];
        foreach ($this->markup_files() as $path) {
            foreach (file($path) as $number => $line) {
                if ($this->is_comment_line($line)) {
                    continue;
                }
                foreach ($this->badge_text_colours() as $background => $required) {
                    if (!preg_match('/\b' . preg_quote($background, '/') . '\b/', $line)) {
                        continue;
                    }
                    if (!preg_match('/\btext-(white|dark|body|muted)\b/', $line)) {
                        $offenders[] = basename($path) . ':' . ($number + 1) . ' needs ' . $required;
                    }
                }
            }
        }
        $this->assertSame(
            [],
            $offenders,
            'Bootstrap 4 gives .badge no text colour and Bootstrap 5 defaults it to white, so a badge '
                . 'that does not state its own colour fails contrast on one branch or the other: '
                . implode('; ', $offenders)
        );
    }

    /**
     * A component wired through Bootstrap's markup data-API must carry both attribute spellings.
     *
     * Bootstrap 4's data-API listens on data-toggle and Bootstrap 5's on data-bs-toggle. Neither
     * SCSS bridge translates attributes, and 5.x's theme_boost/bs4-compat JS module does so only
     * on a page that calls it and is itself deprecated, so markup-wired components need both.
     *
     * @return void
     */
    public function test_data_api_attributes_are_paired(): void {
        $offenders = [];
        $pairs = [
            'data-toggle' => 'data-bs-toggle',
            'data-target' => 'data-bs-target',
            'data-dismiss' => 'data-bs-dismiss',
            'data-parent' => 'data-bs-parent',
        ];
        foreach ($this->markup_files() as $path) {
            foreach (file($path) as $number => $line) {
                if ($this->is_comment_line($line)) {
                    continue;
                }
                foreach ($pairs as $bs4 => $bs5) {
                    /* Match the attribute itself, never a longer name that merely starts with it. */
                    $hasbs4 = preg_match('/(?<![-\w])' . preg_quote($bs4, '/') . '(?![-\w])/', $line);
                    $hasbs5 = preg_match('/(?<![-\w])' . preg_quote($bs5, '/') . '(?![-\w])/', $line);
                    if ($hasbs4 !== $hasbs5) {
                        $offenders[] = basename($path) . ':' . ($number + 1) . ' has only ' . ($hasbs4 ? $bs4 : $bs5);
                    }
                }
            }
        }
        $this->assertSame(
            [],
            $offenders,
            'Bootstrap 4 listens on data-toggle and Bootstrap 5 on data-bs-toggle, so markup-wired '
                . 'components need both spellings side by side: ' . implode('; ', $offenders)
        );
    }

    /**
     * The plugin must not declare custom properties inside core's design-system namespace.
     *
     * Moodle 5.2 ships $mds-* tokens in theme/boost/scss/design-system/, and 5.3 declares --mds-*
     * custom properties of its own (e.g. in theme/boost/scss/moodle/dark.scss), so an --mds-*
     * declaration in the plugin's stylesheet can collide with core. Use the plugin's own
     * frankenstyle prefix instead.
     *
     * @return void
     */
    public function test_stylesheet_declares_no_core_design_system_tokens(): void {
        $root = $this->plugin_root();
        $sheets = array_merge([$root . '/styles.css'], glob($root . '/styles_*.css') ?: []);
        $offenders = [];
        foreach ($sheets as $sheet) {
            foreach (file($sheet) as $number => $line) {
                /* A declaration, not a mention: the property name followed by its colon. */
                if (preg_match('/--mds-[a-z0-9-]+\s*:/i', $line)) {
                    $offenders[] = basename($sheet) . ':' . ($number + 1);
                }
            }
        }
        $this->assertSame(
            [],
            $offenders,
            'These lines declare custom properties in core\'s --mds- namespace; use the plugin\'s own '
                . 'prefix instead: ' . implode(', ', $offenders)
        );
    }

    /**
     * The Bootstrap 4 marker must actually reach the block's root element.
     *
     * The sibling local_dimensions gates its polyfill on a body class; a block cannot, because
     * its get_content() may run after the body tag is printed (the Dashboard's content region is
     * rendered after $OUTPUT->header() in my/index.php). So the marker rides the block's own root,
     * and the chain renderable -> template -> class attribute has to hold: a broken link renders
     * the block unstyled on 4.5 while every static gate stays green.
     *
     * @return void
     */
    public function test_the_block_root_carries_the_bootstrap_marker(): void {
        $root = $this->plugin_root();
        $offenders = [];
        $renderable = file_get_contents($root . '/classes/output/summary.php');
        if (!preg_match('/[\'"]isbs4[\'"]\s*=>\s*bootstrap::is_bs4\(\)/', $renderable)) {
            $offenders[] = 'classes/output/summary.php does not export isbs4 from bootstrap::is_bs4()';
        }
        $template = file_get_contents($root . '/templates/summary.mustache');
        $marker = preg_quote(bootstrap::ROOT_CLASS_BS4, '/');
        if (!preg_match('/\{\{#isbs4\}\}\s*' . $marker . '\s*\{\{\/isbs4\}\}/', $template)) {
            $offenders[] = 'templates/summary.mustache does not emit ' . bootstrap::ROOT_CLASS_BS4
                . ' inside an isbs4 section';
        }
        $this->assertSame(
            [],
            $offenders,
            'The Bootstrap 4 polyfill is gated on a class the block\'s own root must carry, so this '
                . 'chain has to stay intact: ' . implode('; ', $offenders)
        );
    }
}
