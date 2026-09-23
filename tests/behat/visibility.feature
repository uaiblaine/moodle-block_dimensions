@block @block_dimensions
Feature: The block appears only for a learner holding a plan it can show
  In order not to fill the Dashboard with an empty panel
  As a learner
  The block must render when I hold a plan in one of its status buckets, and stay away otherwise

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Sam       | Student  | student1@example.com |
      | student2 | Pat       | Pupil    | student2@example.com |
      | student3 | Dai       | Draft    | student3@example.com |
    # Without this, a learner cannot read their own draft at all, and the draft scenario below
    # would pass because a capability hid the plan rather than because no bucket shows it.
    And the following "role capabilities" exist:
      | role | moodle/competency:planviewowndraft |
      | user | allow                              |
    And the following "core_competency > frameworks" exist:
      | shortname   | idnumber |
      | Framework 1 | frm1     |
    And the following "core_competency > competencies" exist:
      | shortname    | competencyframework | idnumber |
      | Competency 1 | frm1                | comp1    |
    And the following "core_competency > plans" exist:
      | name              | user     | status   | competencies |
      | Teaching practice | student1 | active   | comp1        |
      | Induction 2025    | student1 | complete | comp1        |
      | Plan 2            | student2 | complete | comp1        |
      | Plan 3            | student3 | draft    | comp1        |
    And the following "blocks" exist:
      | blockname  | contextlevel | reference | pagetypepattern | defaultregion |
      | dimensions | System       | 1         | my-index        | side-pre      |
    # HOMEPAGE_MY, so logging in lands on the Dashboard on every branch.
    And the following config values are set as admin:
      | defaulthomepage | 1 |

  Scenario: A learner with an active plan sees the block
    When I log in as "student1"
    Then ".block_dimensions" "css_element" should exist

  # The block carries a completed bucket, so a plan that finished still has a place to be seen.
  Scenario: A learner whose only plan is completed sees the block
    When I log in as "student2"
    Then ".block_dimensions" "css_element" should exist

  # A plain draft belongs to no bucket, so the block would open with nothing in it.
  Scenario: A learner whose only plan is a draft does not see the block
    When I log in as "student3"
    Then ".block_dimensions" "css_element" should not exist

  # Core drops an empty block from the page except in editing mode, where it keeps its
  # controls - which is how an administrator with no plan of their own can still find it.
  Scenario: The block stays reachable in editing mode for a user without a plan
    Given I log in as "admin"
    And ".block_dimensions" "css_element" should not exist
    When I turn editing mode on
    Then ".block_dimensions" "css_element" should exist

  # The plan grid loads the active bucket with the page and fetches any other bucket on the
  # first click. The block renders its cards from a web service, so this one needs JavaScript.
  @javascript
  Scenario: A learner opens the completed plans on demand
    Given I log in as "student1"
    And I should see "Teaching practice"
    And I should not see "Induction 2025"
    When I click on "Completed" "button"
    Then I should see "Induction 2025"
    And I should not see "Teaching practice"

  # A learner whose plans have all finished lands on them, not on an empty Active bucket with its
  # notice.
  @javascript
  Scenario: A learner with no active plan opens on their completed plans
    When I log in as "student2"
    Then I should see "Plan 2"
    And I should not see "No active plans at the moment."
