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
 * Two card-layout invariants that only a browser ever showed.
 *
 * Neither is visible to phpcs, the mustache lint or stylelint: both read syntax, and what breaks
 * here is geometry. Both defects below shipped, and both were found by looking at the rendered
 * block rather than by any gate.
 *
 * @package    block_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_dimensions\local\bootstrap
 */
final class card_layout_test extends \basic_testcase {
    /**
     * Read the plugin stylesheet with its comments stripped.
     *
     * The comments have to go before any rule matching: this very file's first draft read a
     * comment that NAMES .dimension-tags as though it were the selector of the rule below it,
     * and reported the wrong rule.
     *
     * @return string
     */
    protected function styles(): string {
        $css = (string) file_get_contents(__DIR__ . '/../../styles.css');

        return (string) preg_replace('~/\*.*?\*/~s', '', $css);
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
     * The card grids lay out on auto-fill tracks, so a lone last card keeps its column width.
     *
     * With a flex row the last card of an odd row grew to the full width - the same card in two
     * sizes on one screen, which is what a reviewer noticed on the first screenshot of the block.
     *
     * @return void
     */
    public function test_card_grids_use_auto_fill_tracks(): void {
        $gridrules = $this->rules_for('[data-cards-type]');
        $this->assertNotEmpty($gridrules);

        $container = null;
        $item = null;
        foreach ($this->rules_for('[data-cards-type]') as $index => $block) {
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
     * The tag strip stays absolutely positioned over the card image.
     *
     * A later rule lifting it above the stretched-link overlay re-declared `position: relative`,
     * which put the strip back in the flow: inside the image wrapper's overflow:hidden it was cut
     * in half on the horizontal card, and nothing failed.
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
