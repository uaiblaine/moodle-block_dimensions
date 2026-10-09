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
 * Tests for the block's hook callbacks.
 *
 * @package    block_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_dimensions;

use advanced_testcase;
use block_dimensions\output\page;
use core_user\hook\extend_default_homepage;

/**
 * The start page option: offered while the page is enabled, and resolved by core.
 *
 * The hook is dispatched through core's hook manager, so db/hooks.php is part of what is tested.
 * The metadata stays in docblock form while the plugin supports 4.5: moodle-cs on that leg cannot
 * see attributes and warns per method.
 *
 * @package    block_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_dimensions\hook_callbacks
 * @covers     \block_dimensions\output\page
 */
final class hook_callbacks_test extends advanced_testcase {
    /**
     * The options core's start page setting gets from every callback.
     *
     * @param bool $userpreference Whether the choices are built for a user's own preference.
     * @return array Local path => label.
     */
    private function options(bool $userpreference = false): array {
        $hook = new extend_default_homepage($userpreference);
        \core\di::get(\core\hook\manager::class)->dispatch($hook);

        return $hook->get_options();
    }

    /**
     * The option is offered while the page is enabled, keyed on the page's local path.
     *
     * Proved through core's own resolution rather than by reading the array back: with the
     * option's key stored as the site setting, get_home_page() says the start page is a URL and
     * get_default_home_page_url() points at the page.
     *
     * @return void
     */
    public function test_the_start_page_option_follows_the_setting(): void {
        $this->resetAfterTest();
        set_config('enabled', 1, 'core_competency');
        $this->setUser($this->getDataGenerator()->create_user());

        $this->assertArrayNotHasKey(page::PATH, $this->options(), 'offered while the page is off');

        set_config('enable_page', 1, 'block_dimensions');
        $options = $this->options();
        $this->assertArrayHasKey(page::PATH, $options);
        $this->assertSame(get_string('pluginname', 'block_dimensions'), (string) $options[page::PATH]);

        set_config('defaulthomepage', page::PATH);
        $this->assertSame(HOMEPAGE_URL, get_home_page());
        $this->assertSame(
            (new \core\url(page::PATH))->out(false),
            get_default_home_page_url()->out(false)
        );
    }

    /**
     * The option is withdrawn while competencies are disabled, whatever enable_page says.
     *
     * @return void
     */
    public function test_the_start_page_option_needs_competencies(): void {
        $this->resetAfterTest();
        set_config('enable_page', 1, 'block_dimensions');
        set_config('enabled', 1, 'core_competency');
        $this->assertArrayHasKey(page::PATH, $this->options(), 'the control: offered with both on');

        set_config('enabled', 0, 'core_competency');
        $this->assertArrayNotHasKey(page::PATH, $this->options());
    }

    /**
     * When the site leaves the start page to each user, the page is a choice they may store.
     *
     * Core builds the user_home_page_preference definition from the same hook, and cleaning a
     * value outside its choices returns the default; the control is the same value refused while
     * the page is off.
     *
     * @return void
     */
    public function test_a_user_may_choose_the_page_as_their_start_page(): void {
        $this->resetAfterTest();
        set_config('enabled', 1, 'core_competency');
        set_config('defaulthomepage', HOMEPAGE_USER);
        $this->setUser($this->getDataGenerator()->create_user());

        $this->assertNotSame(page::PATH, \core_user::clean_preference(page::PATH, 'user_home_page_preference'));

        set_config('enable_page', 1, 'block_dimensions');
        \core_user::reset_caches();
        $this->assertArrayHasKey(page::PATH, $this->options(true));
        $this->assertSame(page::PATH, \core_user::clean_preference(page::PATH, 'user_home_page_preference'));

        set_user_preference('user_home_page_preference', page::PATH);
        $this->assertSame(HOMEPAGE_URL, get_home_page());
        $this->assertSame(
            (new \core\url(page::PATH))->out(false),
            get_default_home_page_url()->out(false)
        );
    }
}
