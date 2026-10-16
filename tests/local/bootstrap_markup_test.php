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

use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Scans the plugin's markup and stylesheet for Bootstrap spellings that fail on Moodle 5.3.
 *
 * Moodle 5.x resolves Bootstrap 4 class names and data-API attributes only through a deprecated
 * compatibility layer that Moodle 6.0 removes (MDL-84465). No other gate sees a class name or an
 * attribute that resolves to nothing: phpcs, the mustache lint and stylelint never check them in
 * Mustache or JS files.
 *
 * Each arm's scan is a method over one file's lines so a control can run it against a mutated copy
 * of the input without touching the working tree.
 *
 * @package    block_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversNothing]
final class bootstrap_markup_test extends \basic_testcase {
    /**
     * Bootstrap 4 class names that 5.x resolves only through its deprecated compatibility sheet.
     *
     * Each is paired with what to write instead: the BS5 spelling, or, for the font utilities, a
     * declaration in styles.css. See test_no_deprecated_bootstrap4_class_names() for why.
     *
     * The patterns require the token to stand alone: without the lookarounds, border-left would
     * match a CSS property name in a JS style string and text-right would match inside a longer
     * class token.
     *
     * @return array Regex => what to write instead.
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
            '/(?<![-\w])font-weight-(bold|bolder|normal|light|lighter)(?![-\w])/' => 'a font-weight rule in styles.css',
            '/(?<![-\w])font-italic(?![-\w])/' => 'a font-style rule in styles.css',
        ];
    }

    /**
     * Saturated background utilities that need an explicit light text colour.
     *
     * Bootstrap 5's .badge defaults to white, so a light background renders white on near-white
     * (bg-secondary gives 1.49:1 against the 4.5:1 AA floor). Only markup that states its text
     * colour reads on every background.
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
     * Blank out every Mustache comment span in a file's content.
     *
     * A Mustache comment closes at its first closing double brace ({@see is_comment_line()}), so a
     * multi-line template docblock only carries the {{! marker on its opening line - the
     * continuation lines are plain prose with no marker of their own, and is_comment_line() would
     * scan them as markup. Newlines inside the span are preserved so line numbers reported against
     * the result still point at the right source line, the same way colour_tokens_test::uncommented()
     * blanks CSS comments.
     *
     * @param string $content Raw file content.
     * @return string The content with every {{! ... }} span replaced by blank lines.
     */
    private function strip_mustache_comments(string $content): string {
        return preg_replace_callback('~\{\{!.*?\}\}~s', static function (array $match): string {
            return str_repeat("\n", substr_count($match[0], "\n"));
        }, $content);
    }

    /**
     * Read a file as scannable lines, with Mustache comment spans blanked first.
     *
     * @param string $path Absolute path to a file under markup_files().
     * @return array List of lines, 0-indexed, matching file()'s line numbering.
     */
    private function file_lines(string $path): array {
        $content = $this->strip_mustache_comments(file_get_contents($path));
        return explode("\n", $content);
    }

    /**
     * Offending Bootstrap 4 class names in one file's lines.
     *
     * @param string $name File basename for the report.
     * @param array $lines Lines as returned by file_lines().
     * @return array List of offenders.
     */
    private function deprecated_class_findings(string $name, array $lines): array {
        $offenders = [];
        foreach ($lines as $number => $line) {
            if ($this->is_comment_line($line)) {
                continue;
            }
            foreach ($this->deprecated_bs4_utilities() as $pattern => $replacement) {
                if (preg_match($pattern, $line, $matches)) {
                    $offenders[] = $name . ':' . ($number + 1) . ' writes ' . $matches[0] . ', use ' . $replacement;
                }
            }
        }
        return $offenders;
    }

    /**
     * Badge backgrounds that do not state a text colour in one file's lines.
     *
     * Checked on every line carrying a background utility, not only lines that also say "badge": a
     * match arm returning a bare 'bg-success' names no badge, while the method that makes it a badge
     * colour does, a line or more above.
     *
     * @param string $name File basename for the report.
     * @param array $lines Lines as returned by file_lines().
     * @return array List of offenders.
     */
    private function badge_findings(string $name, array $lines): array {
        $offenders = [];
        foreach ($lines as $number => $line) {
            if ($this->is_comment_line($line)) {
                continue;
            }
            foreach ($this->badge_text_colours() as $background => $required) {
                if (!preg_match('/\b' . preg_quote($background, '/') . '\b/', $line)) {
                    continue;
                }
                if (!preg_match('/\btext-(white|dark|body|muted)\b/', $line)) {
                    $offenders[] = $name . ':' . ($number + 1) . ' needs ' . $required;
                }
            }
        }
        return $offenders;
    }

    /**
     * Bootstrap 4 data-API attribute names in one file's lines.
     *
     * @param string $name File basename for the report.
     * @param array $lines Lines as returned by file_lines().
     * @return array List of offenders.
     */
    private function data_api_findings(string $name, array $lines): array {
        $offenders = [];
        $pairs = [
            'data-toggle' => 'data-bs-toggle',
            'data-target' => 'data-bs-target',
            'data-dismiss' => 'data-bs-dismiss',
            'data-parent' => 'data-bs-parent',
        ];
        foreach ($lines as $number => $line) {
            if ($this->is_comment_line($line)) {
                continue;
            }
            foreach ($pairs as $bs4 => $bs5) {
                /* Match the attribute itself, never a longer name that merely starts with it. */
                if (preg_match('/(?<![-\w])' . preg_quote($bs4, '/') . '(?![-\w])/', $line)) {
                    $offenders[] = $name . ':' . ($number + 1) . ' writes ' . $bs4 . ', use ' . $bs5;
                }
            }
        }
        return $offenders;
    }

    /**
     * Declarations in core's --mds- namespace in one stylesheet's lines.
     *
     * @param string $name File basename for the report.
     * @param array $lines Stylesheet lines.
     * @return array List of offenders.
     */
    private function design_system_findings(string $name, array $lines): array {
        $offenders = [];
        foreach ($lines as $number => $line) {
            /* A declaration, not a mention: the property name followed by its colon. */
            if (preg_match('/--mds-[a-z0-9-]+\s*:/i', $line)) {
                $offenders[] = $name . ':' . ($number + 1);
            }
        }
        return $offenders;
    }

    /**
     * No Bootstrap 4 class name may be written.
     *
     * 5.x resolves these only through bs4-compat.scss, which wraps every one in a
     * deprecated-styles mixin - a red outline under behat-site and themedesignermode - and which
     * Moodle 6.0 deletes outright. Every replacement listed resolves on 5.3 without it.
     *
     * @return void
     */
    public function test_no_deprecated_bootstrap4_class_names(): void {
        $offenders = [];
        foreach ($this->markup_files() as $path) {
            $offenders = array_merge($offenders, $this->deprecated_class_findings(basename($path), $this->file_lines($path)));
        }
        sort($offenders);
        $this->assertSame(
            [],
            $offenders,
            'These Bootstrap 4 class names reach 5.x only through the deprecated compatibility sheet '
                . 'Moodle 6.0 removes; use the Bootstrap 5 spelling: ' . implode('; ', $offenders)
        );
    }

    /**
     * Every badge background must state its text colour.
     *
     * @return void
     */
    public function test_badges_state_their_text_colour(): void {
        $offenders = [];
        foreach ($this->markup_files() as $path) {
            $offenders = array_merge($offenders, $this->badge_findings(basename($path), $this->file_lines($path)));
        }
        $this->assertSame(
            [],
            $offenders,
            'Bootstrap 5 defaults .badge to white, so a light background that does not state its own '
                . 'text colour fails contrast: ' . implode('; ', $offenders)
        );
    }

    /**
     * The plugin must not declare custom properties inside core's design-system namespace.
     *
     * Moodle 5.3 declares --mds-* custom properties of its own (e.g. in
     * theme/boost/scss/moodle/dark.scss), so an --mds-* declaration in the plugin's stylesheet can
     * collide with core. Use the plugin's own frankenstyle prefix instead.
     *
     * @return void
     */
    public function test_stylesheet_declares_no_core_design_system_tokens(): void {
        $root = $this->plugin_root();
        $sheets = array_merge([$root . '/styles.css'], glob($root . '/styles_*.css') ?: []);
        $offenders = [];
        foreach ($sheets as $sheet) {
            $offenders = array_merge($offenders, $this->design_system_findings(basename($sheet), file($sheet)));
        }
        $this->assertSame(
            [],
            $offenders,
            'These lines declare custom properties in core\'s --mds- namespace; use the plugin\'s own '
                . 'prefix instead: ' . implode(', ', $offenders)
        );
    }

    /**
     * Markup-wired components must use the Bootstrap 5 data-API spelling only.
     *
     * Moodle 5.3 listens on data-bs-toggle and friends; the Bootstrap 4 spellings (data-toggle,
     * data-target, data-dismiss, data-parent) do nothing there except through a deprecated JS shim.
     *
     * @return void
     */
    public function test_data_api_attributes_use_the_bootstrap5_spelling(): void {
        $offenders = [];
        foreach ($this->markup_files() as $path) {
            $offenders = array_merge($offenders, $this->data_api_findings(basename($path), $this->file_lines($path)));
        }
        $this->assertSame(
            [],
            $offenders,
            'Bootstrap 5 listens on data-bs-* attributes only: ' . implode('; ', $offenders)
        );
    }

    /**
     * A Bootstrap class named only in a Mustache docblock's prose must not count as markup usage.
     *
     * is_comment_line() alone only recognises the LINE that opens a {{! … }} span; a multi-line
     * template docblock's continuation lines carry no marker of their own and, unstripped, would be
     * scanned as real markup. templates/plan_card.mustache has exactly this shape: its docblock
     * names "visually-hidden" in prose on a continuation line. Left unfixed, that prose mention
     * would keep the badge and class scans green even after every real markup use of
     * the class was deleted — the fixture below reproduces the shape without depending on that real
     * template's wording.
     *
     * @return void
     */
    public function test_mustache_docblock_prose_is_not_scanned_as_markup(): void {
        $fixture = sys_get_temp_dir() . '/block_dimensions_bootstrap_markup_' . uniqid() . '.mustache';
        file_put_contents($fixture, implode("\n", [
            '{{!',
            '    Example docblock naming visually-hidden in prose, across more than one line - this',
            '    continuation line carries no comment marker of its own.',
            '}}',
            '<span class="visually-hidden">real usage, outside the comment</span>',
            '',
        ]));

        try {
            $found = [];
            foreach ($this->file_lines($fixture) as $line) {
                if ($this->is_comment_line($line)) {
                    continue;
                }
                if (preg_match('/\bvisually-hidden\b/', $line)) {
                    $found[] = trim($line);
                }
            }
            $this->assertSame(
                ['<span class="visually-hidden">real usage, outside the comment</span>'],
                $found,
                'Only the real markup use of visually-hidden should survive the line scan; the '
                    . 'docblock\'s prose mention must be blanked before it, not counted alongside it.'
            );
        } finally {
            unlink($fixture);
        }
    }
}
