@block @block_dimensions @javascript
Feature: The block's search, favourites and tag filters keep the cards within reach
  In order to find my plans whatever I filtered by
  As a learner
  The block must say when nothing matched, keep the favourites view it opened with, and survive a tag value holding a quote

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Sam       | Student  | student1@example.com |
    And the following "core_competency > frameworks" exist:
      | shortname   | idnumber |
      | Framework 1 | frm1     |
    And the following "core_competency > competencies" exist:
      | shortname    | competencyframework | idnumber |
      | Safe lifting | frm1                | comp1    |
    And the following "blocks" exist:
      | blockname  | contextlevel | reference | pagetypepattern | defaultregion |
      | dimensions | System       | 1         | my-index        | side-pre      |
    # HOMEPAGE_MY, so logging in lands on the Dashboard on every branch.
    And the following config values are set as admin:
      | defaulthomepage | 1 |

  # The message is scoped to the empty-state line: the results announcement, a visually hidden
  # live region elsewhere in the block, says "No results found." whatever the empty state says.
  Scenario: A search that matches no card says no result matched
    Given the following config values are set as admin:
      | enable_search | 1 | block_dimensions |
    And the following "core_competency > plans" exist:
      | name              | user     | status | competencies |
      | Teaching practice | student1 | active | comp1        |
    And I log in as "student1"
    # Control: with no search the learner's card is on screen and the empty state says nothing.
    And I should see "Teaching practice" in the ".block_dimensions [data-cards-type=plan]" "css_element"
    And ".block_dimensions .dims-empty-state" "css_element" should not be visible
    When I set the field "Search cards" to "zzz"
    Then I should see "No results found." in the ".block_dimensions .dims-empty-state" "css_element"
    And I should not see "Teaching practice"
    And I should not see "No competencies in your plans."
    When I click on "Clear" "button" in the ".block_dimensions .dims-search-row" "css_element"
    Then I should see "Teaching practice" in the ".block_dimensions [data-cards-type=plan]" "css_element"
    And ".block_dimensions .dims-empty-state" "css_element" should not be visible

  # With a favourite plan the first request brings the favourite plans alone, shown under the
  # favourites pill with a ghost card offering the rest. The active bucket keeps that partial list
  # while another bucket is on screen, so coming back must restore the favourites view: "Show all"
  # over it would claim every plan while showing one, with nothing left to fetch the others.
  Scenario: Coming back to the active plans keeps the favourites view and its offer of the rest
    Given the following "core_competency > plans" exist:
      | name              | user     | status   | competencies |
      | Teaching practice | student1 | active   | comp1        |
      | Field safety      | student1 | active   | comp1        |
      | Induction 2025    | student1 | complete | comp1        |
    And "student1" has marked the learning plan "Teaching practice" as a favourite
    When I log in as "student1"
    Then I should see "Teaching practice" in the ".block_dimensions [data-cards-type=plan]" "css_element"
    And the "aria-checked" attribute of ".block_dimensions [data-fav-filter-type=plan]" "css_element" should contain "true"
    And ".dims-ghost-card" "css_element" should exist in the ".block_dimensions [data-cards-type=plan]" "css_element"
    And I should not see "Field safety"
    When I click on ".block_dimensions [data-status-filter=complete]" "css_element"
    Then I should see "Induction 2025" in the ".block_dimensions [data-cards-type=plan]" "css_element"
    When I click on ".block_dimensions [data-status-filter=active]" "css_element"
    Then I should see "Teaching practice" in the ".block_dimensions [data-cards-type=plan]" "css_element"
    And the "aria-checked" attribute of ".block_dimensions [data-fav-filter-type=plan]" "css_element" should contain "true"
    And the "aria-checked" attribute of ".block_dimensions [data-all-filter-type=plan]" "css_element" should contain "false"
    And ".dims-ghost-card" "css_element" should exist in the ".block_dimensions [data-cards-type=plan]" "css_element"
    When I click on "View more items" "button" in the ".block_dimensions [data-cards-type=plan]" "css_element"
    Then I should see "Field safety" in the ".block_dimensions [data-cards-type=plan]" "css_element"

  # A search runs over every plan of the bucket on screen. Coming back under a search to an active
  # list that still holds the favourite plans alone fetches the rest, as a search typed there
  # would, and leaves the favourites view off: under it the search could only reach the favourites.
  Scenario: Coming back to the active plans under a search finds the plans the favourites view left out
    Given the following config values are set as admin:
      | enable_search | 1 | block_dimensions |
    And the following "core_competency > plans" exist:
      | name              | user     | status   | competencies |
      | Teaching practice | student1 | active   | comp1        |
      | Field safety      | student1 | active   | comp1        |
      | Induction 2025    | student1 | complete | comp1        |
    And "student1" has marked the learning plan "Teaching practice" as a favourite
    And I log in as "student1"
    And the "aria-checked" attribute of ".block_dimensions [data-fav-filter-type=plan]" "css_element" should contain "true"
    And I click on ".block_dimensions [data-status-filter=complete]" "css_element"
    And I should see "Induction 2025" in the ".block_dimensions [data-cards-type=plan]" "css_element"
    And I set the field "Search cards" to "Field"
    # Precondition: the search has run over the completed plans before the switch; a switch made
    # before it runs would leave the search handler, not the switch, to fetch the missing plans.
    And I should see "No results found." in the ".block_dimensions .dims-empty-state" "css_element"
    When I click on ".block_dimensions [data-status-filter=active]" "css_element"
    Then I should see "Field safety" in the ".block_dimensions [data-cards-type=plan]" "css_element"
    And the "aria-checked" attribute of ".block_dimensions [data-fav-filter-type=plan]" "css_element" should contain "false"
    And I should not see "Teaching practice"

  # A rebuilt filter bar gives focus back to the pill that had it, found by a selector built from
  # the pill's value. "Clear filters" rebuilds the bar; clicked from script, it leaves focus on the
  # tag pill, as a rebuild does when it comes from a load that lands while the learner is on a pill.
  Scenario: A plan tag value holding a double quote survives a filter bar rebuild
    Given the following config values are set as admin:
      | enable_plan_tag1_filter | 1 | block_dimensions |
    And the following "core_competency > templates" exist:
      | shortname |
      | Safety    |
    And the learning plan template "Safety" shows plan cards tagged "Level \"A\""
    And "student1" holds an active learning plan "Fire safety" based on the template "Safety"
    And the following "core_competency > plans" exist:
      | name              | user     | status | competencies |
      | Teaching practice | student1 | active | comp1        |
    And I log in as "student1"
    And I should see "Fire safety" in the ".block_dimensions [data-cards-type=plan]" "css_element"
    When I click on "Level \"A\"" "button" in the ".block_dimensions [data-filters-type=plan]" "css_element"
    # Preconditions: the tag filter hides the untagged plan, and the pill holds focus before the rebuild.
    Then I should not see "Teaching practice"
    And the focused element is "Level \"A\"" "button" in the ".block_dimensions [data-filters-type=plan]" "css_element"
    When I click on ".block_dimensions [data-clear-type=plan]" "css_element" skipping visibility check
    Then I should see "Teaching practice" in the ".block_dimensions [data-cards-type=plan]" "css_element"
    And the focused element is "Level \"A\"" "button" in the ".block_dimensions [data-filters-type=plan]" "css_element"
