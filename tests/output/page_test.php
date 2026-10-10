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
 * Tests for the block's own page.
 *
 * @package    block_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_dimensions\output;

use advanced_testcase;

/**
 * The block's own page: the block's shell in the stylesheet's scope, behind its gates.
 *
 * The metadata stays in docblock form while the plugin supports 4.5: moodle-cs on that leg cannot
 * see attributes and warns per method.
 *
 * @package    block_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_dimensions\output\page
 * @covers     \block_dimensions\output\renderer
 * @covers     \block_dimensions\output\summary
 */
final class page_test extends advanced_testcase {
    /**
     * Turn the page and competencies on, and log a fresh user in.
     *
     * @return \stdClass The user.
     */
    private function enable_page_for_a_user(): \stdClass {
        set_config('enabled', 1, 'core_competency');
        set_config('enable_page', 1, 'block_dimensions');
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        return $user;
    }

    /**
     * Run require_access() and say whether it redirected.
     *
     * Under CLI redirect() throws rather than sending a header; that exception is the redirect.
     *
     * @return bool
     */
    private function redirects(): bool {
        try {
            page::require_access();
        } catch (\moodle_exception $e) {
            $this->assertSame('redirecterrordetected', $e->errorcode);
            return true;
        }

        return false;
    }

    /**
     * The page renders the block's shell inside a wrapper carrying the stylesheet's scope class.
     *
     * The wrapper carries block_dimensions, the class every rule of styles.css is written under,
     * and not core's block card chrome; the shell is the summary template with its client init. The
     * viewer holds no plan, which closes the block's gate - the control that the page does not read
     * it: a start page cannot vanish the way an empty block does.
     *
     * @return void
     */
    public function test_the_page_renders_the_block_shell_in_the_stylesheet_scope(): void {
        global $PAGE;

        $this->resetAfterTest();
        $this->enable_page_for_a_user();
        $PAGE->set_url('/');

        $page = new page();
        $this->assertFalse($page->has_content(), 'the viewer holds no plan, so the block would render nothing');

        $html = $PAGE->get_renderer('block_dimensions')->render($page);

        $this->assertMatchesRegularExpression('/^<div class="block_dimensions dims-page">/', $html);
        $this->assertStringContainsString('class="block-dimensions-content', $html);
        $this->assertStringContainsString('data-cards-type="plan"', $html);
        $this->assertStringNotContainsString('card-body', $html);
        $this->assertLessThan(
            strpos($html, 'class="block-dimensions-content'),
            strpos($html, 'class="block_dimensions dims-page"'),
            'the wrapper encloses the shell'
        );
        // The template's js block reaches the requirements manager, which prints it in the footer.
        $amdjscode = (new \ReflectionProperty($PAGE->requires, 'amdjscode'))->getValue($PAGE->requires);
        $this->assertStringContainsString("require(['block_dimensions/filters']", implode("\n", $amdjscode));
    }

    /**
     * A guest whose home page is this very page is refused: redirecting home would loop.
     *
     * The control is the next test: the same guest with another home page is redirected.
     *
     * @return void
     */
    public function test_a_guest_whose_home_is_this_page_is_refused(): void {
        global $CFG;

        $this->resetAfterTest();
        set_config('enabled', 1, 'core_competency');
        set_config('enable_page', 1, 'block_dimensions');
        $CFG->defaulthomepage = page::PATH;
        $this->setGuestUser();
        $this->assertSame(HOMEPAGE_URL, get_home_page());

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('noguest', 'error'));
        page::require_access();
    }

    /**
     * A guest is sent to the site home, whatever the home page is, unless it is this page.
     *
     * @return void
     */
    public function test_a_guest_is_redirected_to_the_site_home(): void {
        global $CFG;

        $this->resetAfterTest();
        set_config('enabled', 1, 'core_competency');
        set_config('enable_page', 1, 'block_dimensions');
        $this->setGuestUser();

        $CFG->defaulthomepage = HOMEPAGE_SITE;
        $this->assertNotSame(HOMEPAGE_URL, get_home_page());
        $this->assertTrue($this->redirects(), 'a guest with the site front page as home was not redirected');

        $CFG->defaulthomepage = '/course/index.php';
        $this->assertSame(HOMEPAGE_URL, get_home_page());
        $this->assertTrue($this->redirects(), 'a guest whose home is another URL was not redirected');

        $CFG->defaulthomepage = page::PATH . '?x=1';
        $this->assertSame(HOMEPAGE_URL, get_home_page());
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('noguest', 'error'));
        page::require_access();
    }

    /**
     * A signed-in user is not affected by the guest gate, whatever the home page is.
     *
     * @return void
     */
    public function test_a_user_is_not_affected_by_the_guest_gate(): void {
        global $CFG;

        $this->resetAfterTest();
        $this->enable_page_for_a_user();
        $CFG->defaulthomepage = page::PATH;

        $this->assertFalse($this->redirects(), 'a signed-in user was sent away from the enabled page');
    }

    /**
     * While the page is off it redirects to the Dashboard; on, it lets the viewer through.
     *
     * The control is the enabled half: the same viewer passes while the setting is stored, so the
     * redirect is the setting's doing and not a broken login.
     *
     * @return void
     */
    public function test_the_page_redirects_to_the_dashboard_while_it_is_off(): void {
        $this->resetAfterTest();
        $this->enable_page_for_a_user();

        $this->assertTrue(page::is_enabled());
        $this->assertFalse($this->redirects(), 'the enabled page lets a logged-in user through');

        unset_config('enable_page', 'block_dimensions');
        $this->assertFalse(page::is_enabled(), 'off unless an explicit 1 is stored');
        $this->assertTrue($this->redirects(), 'the page let a viewer in while it was off');
    }

    /**
     * While competencies are disabled the page redirects too, whatever enable_page says.
     *
     * The block cannot be added then and its web service returns no card, so the page would
     * open empty.
     *
     * @return void
     */
    public function test_the_page_redirects_while_competencies_are_disabled(): void {
        $this->resetAfterTest();
        $this->enable_page_for_a_user();

        $this->assertFalse($this->redirects(), 'the enabled page lets a logged-in user through');

        set_config('enabled', 0, 'core_competency');
        $this->assertFalse(page::is_enabled());
        $this->assertTrue($this->redirects(), 'the page let a viewer in while competencies were disabled');
    }
}
