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
    # HOMEPAGE_MY, so logging in lands on the Dashboard on every branch rather than on
    # whatever that branch happens to default to. Core's own block tests rely on the same
    # arrival; pinning the config is what makes it true on 4.05 as well as on 5.02.
    And the following config values are set as admin:
      | defaulthomepage | 1 |

  # B2 is the anti-vacuity control the fleet rule asks for: B1 proves the mechanism fires,
  # and this proves it does not fire unasked. Point a surface token at a literal and B1
  # reddens; add an ungated dark rule and this one reddens.
  #
  # What no scenario here can cover is the inertness of the prefers-color-scheme block.
  # Emulating a media feature needs Emulation.setEmulatedMedia over the DevTools protocol and
  # Moodle's WebDriver wiring is not confirmed to expose it; headless Chrome reports light, so
  # the query never evaluates true and an assertion on it would pass having tested nothing.
  # That gap is closed by colour_tokens_test::test_media_fallback_is_written_and_unreachable,
  # which is strictly stronger: it proves nothing CAN set the gate, not merely that nothing did
  # on one run. Do not read this scenario as that proof.
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

  # The block opens no core/modal and appends nothing to document.body, so it has no relocated
  # root of the kind that left local_awareness with an unresolved var() inside a dialogue. This
  # is the nearest thing it has, and the claim is the same one: the access pill is a nested
  # element inside a card amd/src/filters.js builds from a web-service response, inside a block
  # region the theme renders as its own off-canvas drawer - the far end of everything this
  # plugin paints. Tokens declared on bare :root reach it for the reason they reach a dialogue,
  # because html is the ancestor of every node in the document, and an enumeration of roots is
  # exactly what would have missed it. Observed here rather than argued.
  @B3
  Scenario: A pill deep inside a card built after page load follows the host too
    Given I log in as "student1"
    And I remember the host page background colour
    When the host colour mode is "dark"
    Then the ".block_dimensions .dims-card-access-btn" element background should still match the host page

  # The only scenario that needs a real dark palette, and so the only one that is guarded.
  # The guard is a runtime measurement rather than a branch number, because Moodle 5.00 is a
  # real CI leg here and nobody has measured its compiled sheet.
  @B4
  Scenario: The block's own decorative tokens flip with the host
    Given I log in as "student1"
    And the host ships a colour mode
    When the host colour mode is "dark"
    Then the "block-dimensions-shadow" colour token should resolve to "rgb(0 0 0 / 55%)" on the host
    And the "block-dimensions-scrim" colour token should resolve to "rgb(29 33 37 / 72%)" on the host
    And the "block-dimensions-favourite" colour token should resolve to "#fd7e14" on the host
