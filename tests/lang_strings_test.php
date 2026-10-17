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
 * Tests for the plugin's language packs.
 *
 * @package    block_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_dimensions;

use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * The English and Brazilian Portuguese packs in lockstep, sorted, saying the filter line's words.
 *
 * The validate gate checks the order of the English pack only; the lockstep, the Portuguese order and
 * the contract's words (moodle-dev/docs/filter-line/CONTRACT.md, section 7) are nobody's but this
 * test's.
 */
#[CoversNothing]
final class lang_strings_test extends \basic_testcase {
    /**
     * The strings of one pack, in file order.
     *
     * @param string $lang en or pt_br.
     * @return array Keys to strings.
     */
    private function pack(string $lang): array {
        $string = [];
        include(dirname(__DIR__) . "/lang/{$lang}/block_dimensions.php");
        return $string;
    }

    /**
     * Both packs carry the same keys, each pack sorted.
     *
     * Changes that must make it fail: a key added to one pack alone; a key out of its alphabetical slot
     * in either pack.
     *
     * @return void
     */
    public function test_the_packs_are_in_lockstep_and_sorted(): void {
        $en = $this->pack('en');
        $ptbr = $this->pack('pt_br');
        $this->assertNotEmpty($en);
        $this->assertSame(array_keys($en), array_keys($ptbr), 'The two packs carry the same keys in the same order.');
        foreach (['en' => $en, 'pt_br' => $ptbr] as $lang => $pack) {
            $sorted = array_keys($pack);
            sort($sorted, SORT_STRING);
            $this->assertSame($sorted, array_keys($pack), "lang/{$lang} is not sorted.");
        }
    }

    /**
     * The filter line says the contract's words in both languages.
     *
     * Changes that must make it fail: any of these words changed in either pack.
     *
     * @return void
     */
    public function test_the_filter_line_says_the_contracts_words(): void {
        $words = [
            'line_clear_all' => ['Clear all', 'Limpar tudo'],
            'line_clear_text' => ['Clear text', 'Limpar texto'],
            'line_close_search' => ['Close search', 'Fechar busca'],
            'line_favourites' => ['Favourites', 'Favoritos'],
            'line_filters' => ['Filters', 'Filtros'],
            'line_search_competencies_placeholder' => ['Search competencies…', 'Buscar competências…'],
            'line_search_plans_placeholder' => ['Search plans…', 'Buscar planos…'],
            'line_suggest_filters' => ['Filters', 'Filtros'],
            'line_suggest_start' => ['Start with a filter', 'Comece por um filtro'],
            'clearfilters' => ['Clear filters', 'Limpar filtros'],
        ];
        $en = $this->pack('en');
        $ptbr = $this->pack('pt_br');
        foreach ($words as $key => [$english, $portuguese]) {
            $this->assertSame($english, $en[$key] ?? null, "en {$key}");
            $this->assertSame($portuguese, $ptbr[$key] ?? null, "pt_br {$key}");
        }
    }

    /**
     * Every string the plugin asks for exists, and none is asked for by a key built at run time.
     *
     * A key built at run time is invisible to this test and to the string tools; a key asked for
     * and missing renders as [[key]] at the learner.
     *
     * Changes that must make it fail: get_string() of this component with a concatenated key; a str
     * helper whose key is a Mustache variable; a key used and not in the pack.
     *
     * @return void
     */
    public function test_every_string_asked_for_exists_and_none_is_built(): void {
        $root = dirname(__DIR__);
        $files = array_merge(
            glob($root . '/*.php'),
            glob($root . '/classes/*/*.php'),
            glob($root . '/classes/*.php'),
            glob($root . '/db/*.php'),
            glob($root . '/templates/*.mustache')
        );
        $en = $this->pack('en');
        $used = [];
        foreach ($files as $file) {
            $source = (string) file_get_contents($file);
            $this->assertDoesNotMatchRegularExpression(
                "~get_string\(\s*['\"][^'\"]*['\"]\s*\.~",
                $source,
                basename($file) . ' builds a string key at run time.'
            );
            $this->assertDoesNotMatchRegularExpression(
                '~\{\{#str\}\}\s*\{\{~',
                $source,
                basename($file) . ' builds a string key in a template.'
            );
            preg_match_all("~get_string\(\s*'([a-z0-9_:]+)',\s*'block_dimensions'~", $source, $calls);
            preg_match_all('~\{\{#str\}\}\s*([a-z0-9_:]+),\s*block_dimensions~', $source, $helpers);
            $used = array_merge($used, $calls[1], $helpers[1]);
        }
        $this->assertNotEmpty($used, 'No string was found, so this test checks nothing.');
        $this->assertSame([], array_values(array_diff(array_unique($used), array_keys($en))), 'Strings asked for and missing.');
    }
}
