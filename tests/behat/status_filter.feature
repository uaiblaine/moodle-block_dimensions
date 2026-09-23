@block @block_dimensions @javascript
Feature: The plan status filter keeps the competency cards within reach
  In order to reach my competencies whichever plans I am looking at
  As a learner whose active plan shows competency cards rather than a plan card
  The Active pill must stay on offer, uncounted when it has no plan card, and the competency cards must load from any bucket

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
      | First aid    | frm1                | comp2    |
      | Fire drill   | frm1                | comp3    |
    # A competency card is drawn only for a competency linked to a visible course.
    And the following "core_competency > course_competencies" exist:
      | course | competency |
      | C1     | comp1      |
      | C1     | comp3      |
    And the following "core_competency > templates" exist:
      | shortname  |
      | Onboarding |
    And the following "core_competency > template_competencies" exist:
      | template   | competency |
      | Onboarding | comp1      |
      | Onboarding | comp3      |
    # The template stores no display mode, so its plan is shown as competency cards and adds
    # nothing to the Active count.
    And "student1" holds an active learning plan "Onboarding path" based on the template "Onboarding"
    And the following "core_competency > plans" exist:
      | name           | user     | status   | competencies |
      | Induction 2025 | student1 | complete | comp2        |
    And the following "blocks" exist:
      | blockname  | contextlevel | reference | pagetypepattern | defaultregion |
      | dimensions | System       | 1         | my-index        | side-pre      |
    # HOMEPAGE_MY, so logging in lands on the Dashboard on every branch.
    And the following config values are set as admin:
      | defaulthomepage | 1 |

  # Without the Active pill on the Completed bucket the status group would hold a single bucket and
  # not be drawn at all, leaving the competency cards with no way back to their own bucket.
  Scenario: A learner goes to the completed plans and back to the competency cards
    When I log in as "student1"
    Then I should see "Safe lifting" in the ".block_dimensions [data-cards-type=competency]" "css_element"
    And the "aria-checked" attribute of ".block_dimensions [data-status-filter=active]" "css_element" should contain "true"
    # The count is of plan cards, and this bucket draws none.
    And ".dims-filter-count" "css_element" should not exist in the ".block_dimensions [data-status-filter=active]" "css_element"
    And I should see "1" in the ".block_dimensions [data-status-filter=complete] .dims-filter-count" "css_element"
    And I should not see "No active plans at the moment."
    When I click on ".block_dimensions [data-status-filter=complete]" "css_element"
    Then I should see "Induction 2025" in the ".block_dimensions [data-cards-type=plan]" "css_element"
    # The competency cards belong to the active plans, whichever bucket the plan grid shows.
    And I should see "Safe lifting" in the ".block_dimensions [data-cards-type=competency]" "css_element"
    And the "aria-checked" attribute of ".block_dimensions [data-status-filter=active]" "css_element" should contain "false"
    And ".dims-filter-count" "css_element" should not exist in the ".block_dimensions [data-status-filter=active]" "css_element"
    When I click on ".block_dimensions [data-status-filter=active]" "css_element"
    Then I should not see "Induction 2025"
    And the "aria-checked" attribute of ".block_dimensions [data-status-filter=active]" "css_element" should contain "true"
    And I should see "Safe lifting" in the ".block_dimensions [data-cards-type=competency]" "css_element"
    And I should not see "No active plans at the moment."

  # With a favourite, the block opens on the favourite cards alone and fetches the others when they
  # are needed. Those are built from the active plans only, so the fetch must ask for the active
  # bucket whichever bucket the plan grid shows; asked for the completed one, it comes back empty.
  Scenario: A search from the completed plans fetches the competency cards the favourites left out
    Given the following config values are set as admin:
      | enable_search | 1 | block_dimensions |
    And I log in as "student1"
    And I click on "Add to favourites" "button" in the "Safe lifting" "list_item"
    And I should see "1" in the ".block_dimensions [data-fav-filter-type=competency] .dims-filter-count" "css_element"
    And I reload the page
    And I should see "Safe lifting" in the ".block_dimensions [data-cards-type=competency]" "css_element"
    # The ghost card offers the cards the first request left out, so "Fire drill" is not loaded yet.
    And ".dims-ghost-card" "css_element" should exist in the ".block_dimensions [data-cards-type=competency]" "css_element"
    And I should not see "Fire drill"
    And I click on ".block_dimensions [data-status-filter=complete]" "css_element"
    And I should see "Induction 2025" in the ".block_dimensions [data-cards-type=plan]" "css_element"
    When I set the field "Search cards" to "Fire"
    Then I should see "Fire drill" in the ".block_dimensions [data-cards-type=competency]" "css_element"
    And I should not see "Safe lifting"
