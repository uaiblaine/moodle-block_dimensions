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

namespace block_dimensions\external;

use advanced_testcase;
use core_competency\plan;
use core_external\external_api;
use local_dimensions\constants;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The names on the cards: plain text in the web service, escaped exactly once on the page, and
 * formatted in the context each name belongs to.
 *
 * Every consumer of the dataset escapes what it draws (the card templates' double stash, and
 * escapeHtml() / textContent in filters.js), so a name the provider had already escaped was drawn
 * as "R&amp;D". The names below carry an ampersand and a double quote: the ampersand catches a
 * second escape, the quote inside an aria-label catches a missing one, since an unescaped quote
 * ends the attribute early.
 *
 * @package    block_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\block_dimensions\local\dataset_provider::class)]
#[CoversClass(\block_dimensions\external\get_block_dataset::class)]
final class card_names_test extends advanced_testcase {
    /** @var string Stored plan name, which the learner must read unchanged. */
    private const PLANNAME = 'R&D "Core" Plan';

    /** @var string Stored shortname of the competency the plan card's trail shows. */
    private const TRAILNAME = 'Q&A "One"';

    /** @var string Stored shortname of the competency drawn as a competency card. */
    private const CARDNAME = 'S&T "Lab"';

    /** @var string A multilang name; the filter keeps only the English span. */
    private const MULTILANG = '<span lang="en" class="multilang">English</span>'
        . '<span lang="de" class="multilang">Deutsch</span>';

    /**
     * Set one of the sibling plugin's template custom fields, as the admin.
     *
     * instance_form_save() drops every field the current user cannot edit, so a save as the learner
     * would write nothing.
     *
     * @param int $templateid Learning plan template id.
     * @param string $shortname Custom field shortname.
     * @param mixed $value Value to store.
     * @return void
     */
    private function set_template_field(int $templateid, string $shortname, $value): void {
        global $USER;

        $previous = $USER;
        $this->setAdminUser();
        \local_dimensions\helper::ensure_custom_fields_exist(\local_dimensions\helper::AREA_LP);
        \local_dimensions\customfield\lp_handler::create()->instance_form_save((object) [
            'id' => $templateid,
            'customfield_' . $shortname => $value,
        ], true);
        $this->setUser($previous);
    }

    /**
     * Seed a learner with two active plans: one drawn as a plan card whose trail shows one
     * competency, and one on a competencies-mode template drawn as one competency card.
     *
     * @param string $planname Name of the plan drawn as a plan card.
     * @param string $trailname Shortname of the competency in that plan's trail.
     * @param string $cardname Shortname of the competency drawn as a competency card.
     * @param int $contextid Context of both frameworks and both templates.
     * @return \stdClass The learner.
     */
    private function seed(string $planname, string $trailname, string $cardname, int $contextid): \stdClass {
        set_config('enabled', 1, 'core_competency');
        $generator = $this->getDataGenerator();
        $lp = $generator->get_plugin_generator('core_competency');
        $user = $generator->create_user();
        $framework = $lp->create_framework(['contextid' => $contextid]);

        $trailcomp = $lp->create_competency([
            'competencyframeworkid' => $framework->get('id'),
            'shortname' => $trailname,
        ]);
        $plantemplate = $lp->create_template(['contextid' => $contextid]);
        $lp->create_template_competency([
            'templateid' => $plantemplate->get('id'),
            'competencyid' => $trailcomp->get('id'),
        ]);
        $this->set_template_field((int) $plantemplate->get('id'), constants::CFIELD_DISPLAYMODE, constants::DISPLAYMODE_PLAN);
        $lp->create_plan([
            'userid' => $user->id,
            'templateid' => $plantemplate->get('id'),
            'status' => plan::STATUS_ACTIVE,
            'name' => $planname,
        ]);

        // A competency card is drawn only for a competency linked to a course.
        $cardcomp = $lp->create_competency([
            'competencyframeworkid' => $framework->get('id'),
            'shortname' => $cardname,
        ]);
        $course = $generator->create_course();
        $lp->create_course_competency(['courseid' => $course->id, 'competencyid' => $cardcomp->get('id')]);
        $comptemplate = $lp->create_template(['contextid' => $contextid]);
        $lp->create_template_competency([
            'templateid' => $comptemplate->get('id'),
            'competencyid' => $cardcomp->get('id'),
        ]);
        $this->set_template_field(
            (int) $comptemplate->get('id'),
            constants::CFIELD_DISPLAYMODE,
            constants::DISPLAYMODE_COMPETENCIES
        );
        $lp->create_plan([
            'userid' => $user->id,
            'templateid' => $comptemplate->get('id'),
            'status' => plan::STATUS_ACTIVE,
        ]);

        return $user;
    }

    /**
     * The active bucket's dataset as a client receives it, through the return allowlist.
     *
     * @return array{plan: array, competency: array} The one plan card and the one competency card.
     */
    private function fetch_cards(): array {
        $clean = external_api::clean_returnvalue(
            get_block_dataset::execute_returns(),
            get_block_dataset::execute(false)
        );
        $this->assertCount(1, $clean['plancards'], 'the fixture draws one plan card');
        $this->assertCount(1, $clean['competencycards'], 'the fixture draws one competency card');

        return ['plan' => $clean['plancards'][0], 'competency' => $clean['competencycards'][0]];
    }

    /**
     * Parse a rendered card.
     *
     * @param string $html One card's markup.
     * @return \DOMXPath
     */
    private function parse(string $html): \DOMXPath {
        $dom = new \DOMDocument();
        // The parser knows HTML 4 only and reports every HTML5 element, such as article, as an error.
        $dom->loadHTML(
            '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">' . $html,
            LIBXML_NOERROR | LIBXML_NOWARNING
        );
        return new \DOMXPath($dom);
    }

    /**
     * The web service returns each name as plain text, and the templates escape it once.
     */
    public function test_names_are_plain_in_the_dataset_and_escaped_once_on_the_page(): void {
        global $OUTPUT;
        $this->resetAfterTest();
        $user = $this->seed(self::PLANNAME, self::TRAILNAME, self::CARDNAME, \context_system::instance()->id);
        $this->setUser($user);

        ['plan' => $plancard, 'competency' => $compcard] = $this->fetch_cards();

        // Plain in the dataset: no entity, so filters.js and the search index read what is drawn.
        $this->assertSame(self::PLANNAME, $plancard['name']);
        $this->assertSame([self::TRAILNAME], array_column($plancard['trail'], 'shortname'));
        $this->assertSame(self::CARDNAME, $compcard['name']);
        $this->assertStringContainsString(self::PLANNAME, $plancard['buttonarialabel']);
        $this->assertStringContainsString(self::CARDNAME, $compcard['buttonarialabel']);

        /* Escaped once on the page: the parsed text and attributes read back exactly the plain name.
           The name reaches an attribute only on the image, so both cards are drawn with one. */
        $plancard = array_merge($plancard, ['hasimage' => true, 'imageurl' => 'https://example.com/card.png']);
        $compcard = array_merge($compcard, ['hasimage' => true, 'imageurl' => 'https://example.com/card.png']);
        $planhtml = $OUTPUT->render_from_template('block_dimensions/plan_card', $plancard);
        $this->assertStringNotContainsString('&amp;amp;', $planhtml);
        $xpath = $this->parse($planhtml);
        $link = $xpath->query('//a[contains(@class, "plan-card-link")]');
        $this->assertSame(1, $link->length);
        $this->assertSame(self::PLANNAME, $link->item(0)->textContent);
        $this->assertSame([self::PLANNAME], $this->attribute_values($xpath, '//*[@role="img"]', 'aria-label'));
        $this->assertSame(
            [self::TRAILNAME],
            array_map(fn($node) => $node->textContent, iterator_to_array($xpath->query('//*[@class="trail-label"]')))
        );

        $comphtml = $OUTPUT->render_from_template('block_dimensions/competency_card', $compcard);
        $this->assertStringNotContainsString('&amp;amp;', $comphtml);
        $xpath = $this->parse($comphtml);
        $link = $xpath->query('//a[contains(@class, "competency-card-link")]');
        $this->assertSame(1, $link->length);
        $this->assertSame(self::CARDNAME, $link->item(0)->textContent);
        $this->assertSame([self::CARDNAME], $this->attribute_values($xpath, '//*[@role="img"]', 'aria-label'));
    }

    /**
     * A competency's name is formatted in its framework's context, in the cards and in the trail.
     *
     * The multilang filter is on only in the framework's category, so a name formatted in the
     * system context keeps both spans' text. The control formats the same string in the system
     * context and proves the filter is really off there. The plan name is not asserted: it is
     * formatted in its owner's user context, and a user context cannot carry local filter settings
     * ({@see filter_context_may_have_filter_settings()}), so there it reads as in the system context.
     */
    public function test_competency_names_are_formatted_in_their_framework_context(): void {
        $this->resetAfterTest();
        set_config('filterall', 1);
        filter_set_global_state('multilang', TEXTFILTER_OFF);
        // Format_string() runs only the filters that apply to headings as well as content.
        filter_set_applies_to_strings('multilang', true);
        $category = $this->getDataGenerator()->create_category();
        $catcontext = \context_coursecat::instance($category->id);
        $user = $this->seed(self::MULTILANG, self::MULTILANG, self::MULTILANG, $catcontext->id);
        filter_set_local_state('multilang', $catcontext->id, TEXTFILTER_ON);
        // The seed formatted strings already, and the filter manager keeps what it loaded then.
        \filter_manager::reset_caches();
        $this->setUser($user);

        // Control: in the system context the filter does not run.
        $this->assertSame(
            'EnglishDeutsch',
            format_string(self::MULTILANG, true, ['context' => \context_system::instance(), 'escape' => false])
        );

        ['plan' => $plancard, 'competency' => $compcard] = $this->fetch_cards();
        $this->assertSame(['English'], array_column($plancard['trail'], 'shortname'));
        $this->assertSame('English', $compcard['name']);
    }

    /**
     * The values of one attribute on every node an XPath query selects.
     *
     * @param \DOMXPath $xpath The parsed card.
     * @param string $query XPath query.
     * @param string $attribute Attribute name.
     * @return array
     */
    private function attribute_values(\DOMXPath $xpath, string $query, string $attribute): array {
        $values = [];
        foreach ($xpath->query($query) as $node) {
            if ($node->hasAttribute($attribute)) {
                $values[] = $node->getAttribute($attribute);
            }
        }
        return $values;
    }
}
