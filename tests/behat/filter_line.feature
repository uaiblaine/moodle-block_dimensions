@block @block_dimensions @javascript
Feature: Each section of the block filters its cards from a line of its own
  In order to narrow my plans and my competencies apart
  As a learner
  Each section must carry its own search, its own favourites star and its own Filters panel

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Sam       | Student  | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "core_competency > frameworks" exist:
      | shortname   | idnumber |
      | Framework 1 | frm1     |
    And the following "core_competency > competencies" exist:
      | shortname    | competencyframework | idnumber |
      | Safe lifting | frm1                | comp1    |
      | Fire drill   | frm1                | comp2    |
    # A competency card is drawn only for a competency linked to a visible course.
    And the following "core_competency > course_competencies" exist:
      | course | competency |
      | C1     | comp1      |
      | C1     | comp2      |
    And the following "core_competency > templates" exist:
      | shortname  |
      | Onboarding |
    And the following "core_competency > template_competencies" exist:
      | template   | competency |
      | Onboarding | comp1      |
      | Onboarding | comp2      |
    # The template stores no display mode, so its plan is drawn as competency cards.
    And "student1" holds an active learning plan "Onboarding path" based on the template "Onboarding"
    And the following "core_competency > plans" exist:
      | name              | user     | status | competencies |
      | Teaching practice | student1 | active | comp1        |
      | Field safety      | student1 | active | comp1        |
    And the following "blocks" exist:
      | blockname  | contextlevel | reference | pagetypepattern | defaultregion |
      | dimensions | System       | 1         | my-index        | side-pre      |
    # HOMEPAGE_MY, so logging in lands on the Dashboard on every branch.
    And the following config values are set as admin:
      | defaulthomepage | 1 |

  # The plans' search searches the plans alone and the competencies' search the competencies
  # alone: a text typed in one line never empties the other section.
  Scenario: Each section searches its own cards
    Given the following config values are set as admin:
      | enable_search | 1 | block_dimensions |
    When I log in as "student1"
    Then I should see "Teaching practice" in the ".block_dimensions [data-cards-type=plan]" "css_element"
    And I should see "Safe lifting" in the ".block_dimensions [data-cards-type=competency]" "css_element"
    When I set the field "Search plans" to "Field"
    Then I should see "Field safety" in the ".block_dimensions [data-cards-type=plan]" "css_element"
    And I should not see "Teaching practice" in the ".block_dimensions [data-cards-type=plan]" "css_element"
    And I should see "Safe lifting" in the ".block_dimensions [data-cards-type=competency]" "css_element"
    When I set the field "Search competencies" to "Fire"
    Then I should see "Fire drill" in the ".block_dimensions [data-cards-type=competency]" "css_element"
    And I should not see "Safe lifting" in the ".block_dimensions [data-cards-type=competency]" "css_element"
    And I should see "Field safety" in the ".block_dimensions [data-cards-type=plan]" "css_element"

  # The tag filters live in the section's Filters panel; one in force shows as a chip in the row
  # under the line, and pressing the chip drops it.
  Scenario: A tag picked in the Filters panel shows as a chip that drops it
    Given the following config values are set as admin:
      | enable_plan_tag1_filter | 1 | block_dimensions |
    And the following "core_competency > templates" exist:
      | shortname |
      | Safety    |
    And the learning plan template "Safety" shows plan cards tagged "Level 1"
    And "student1" holds an active learning plan "Fire safety" based on the template "Safety"
    And I log in as "student1"
    And I should see "Teaching practice" in the ".block_dimensions [data-cards-type=plan]" "css_element"
    And ".block_dimensions [data-line-type=plan] [data-region=dims-line-chips]" "css_element" should not be visible
    When I click on "Filters" "button" in the ".block_dimensions [data-line-type=plan]" "css_element"
    And I click on "Level 1" "button" in the ".block_dimensions [data-filters-type=plan]" "css_element"
    And I click on "Show" "button" in the ".block_dimensions [data-line-type=plan] [data-region=dims-line-panel]" "css_element"
    Then I should see "Fire safety" in the ".block_dimensions [data-cards-type=plan]" "css_element"
    And I should not see "Teaching practice" in the ".block_dimensions [data-cards-type=plan]" "css_element"
    And I should see "Level 1" in the ".block_dimensions [data-line-type=plan] [data-region=dims-line-chips]" "css_element"
    When I click on "Level 1" "button" in the ".block_dimensions [data-line-type=plan] [data-region=dims-line-chips]" "css_element"
    Then I should see "Teaching practice" in the ".block_dimensions [data-cards-type=plan]" "css_element"
    And ".block_dimensions [data-line-type=plan] [data-region=dims-line-chips]" "css_element" should not be visible

  # With a favourite plan the block opens on the plans' star pressed, the favourites alone and a
  # ghost card; the star released shows every plan. The competencies have no favourite, so their
  # star is not drawn.
  Scenario: A section's star shows its favourites alone and releases to every card
    Given "student1" has marked the learning plan "Teaching practice" as a favourite
    When I log in as "student1"
    Then the "aria-pressed" attribute of ".block_dimensions [data-fav-star=plan]" "css_element" should contain "true"
    And I should see "Teaching practice" in the ".block_dimensions [data-cards-type=plan]" "css_element"
    And I should not see "Field safety" in the ".block_dimensions [data-cards-type=plan]" "css_element"
    And ".block_dimensions [data-fav-star=competency]" "css_element" should not be visible
    When I click on "Favourites" "button" in the ".block_dimensions [data-line-type=plan]" "css_element"
    Then I should see "Field safety" in the ".block_dimensions [data-cards-type=plan]" "css_element"
    And the "aria-pressed" attribute of ".block_dimensions [data-fav-star=plan]" "css_element" should contain "false"
    And I should see "Safe lifting" in the ".block_dimensions [data-cards-type=competency]" "css_element"
