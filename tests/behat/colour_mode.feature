@block @block_dimensions @javascript
Feature: The block's colours follow the host page and nothing else
  In order that a learner never sees a dark panel on a light page
  As a site administrator
  The block's surfaces must track the host, and only the host

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Sam       | Student  | student1@example.com |
    And the following "core_competency > frameworks" exist:
      | shortname   | idnumber |
      | Framework 1 | frm1     |
    And the following "core_competency > competencies" exist:
      | shortname    | competencyframework | idnumber |
      | Competency 1 | frm1                | comp1    |
    And the following "core_competency > plans" exist:
      | name   | user     | status | competencies |
      | Plan 1 | student1 | active | comp1        |
    And the following "blocks" exist:
      | blockname  | contextlevel | reference | pagetypepattern | defaultregion |
      | dimensions | System       | 1         | my-index        | side-post     |
    # HOMEPAGE_MY, so logging in lands on the Dashboard, where the block is placed, on every branch.
    And the following config values are set as admin:
      | defaulthomepage | 1 |

  # @B2 is the control for @B1: @B1 proves the block follows the host into dark mode, and this
  # proves it does not go dark unasked. Changes that must make them fail: pointing a surface token
  # at a literal (@B1); adding an ungated dark rule (@B2).
  #
  # No scenario covers the inert prefers-color-scheme block: headless Chrome reports light, so the
  # media query never matches and an assertion on it would test nothing. That is covered by
  # colour_tokens_test::test_media_fallback_is_written_and_unreachable, which proves nothing can
  # set the block's gate.
  @B2
  Scenario: The block stays light when the host says nothing
    Given I log in as "student1"
    And I remember the host page background colour
    Then the ".block_dimensions .plan-card" element background should still match the host page
    And the "block-dimensions-shadow" colour token should resolve to "rgb(0 0 0 / 10%)" on the host

  @B1
  Scenario: The block follows the host into dark mode and never diverges from it
    Given I log in as "student1"
    And I remember the host page background colour
    When the host colour mode is "dark"
    Then the ".block_dimensions .plan-card" element background should still match the host page

  # The block appends nothing to document.body, so its deepest surface is the access pill: nested
  # in a card amd/src/filters.js builds from a web-service response after page load. The tokens
  # reach it because they are declared on body, the ancestor of every node the plugin paints.
  @B3
  Scenario: A pill deep inside a card built after page load follows the host too
    Given I log in as "student1"
    And I remember the host page background colour
    When the host colour mode is "dark"
    Then the ".block_dimensions .dims-card-access-btn" element background should still match the host page

  # The only scenario that needs a dark palette in core's stylesheet, so the only one guarded.
  # The guard detects the palette at run time instead of trusting the branch number.
  @B4
  Scenario: The block's own decorative tokens flip with the host
    Given I log in as "student1"
    And the host ships a colour mode
    When the host colour mode is "dark"
    Then the "block-dimensions-shadow" colour token should resolve to "rgb(0 0 0 / 55%)" on the host
    And the "block-dimensions-scrim" colour token should resolve to "rgb(29 33 37 / 72%)" on the host
    And the "block-dimensions-favourite" colour token should resolve to "#fd7e14" on the host
