@block @block_dimensions
Feature: The block's own page shows its content alone, and can be the start page
  In order to reach my learning plans without the rest of the Dashboard
  As a learner
  The Dimensions page must show the block's content with only the theme around it, and open from the site root once it is the start page

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
      | name              | user     | status | competencies |
      | Teaching practice | student1 | active | comp1        |
    And the following "blocks" exist:
      | blockname  | contextlevel | reference | pagetypepattern | defaultregion |
      | dimensions | System       | 1         | my-index        | side-pre      |
    And the following config values are set as admin:
      | defaulthomepage | 1 |

  # The page is a browser-only surface - a layout with no block regions, the theme's heading,
  # the cards a web service returns - and nothing in PHPUnit loads a page.
  @javascript @accessibility
  Scenario: The Dimensions page shows the block alone, and can be the start page
    Given the following config values are set as admin:
      | enable_page | 1 | block_dimensions |
    And I log in as "student1"
    When I visit "/blocks/dimensions/index.php"
    # The theme's own heading is the block's name, and under it the block's content; no block
    # drawer, because the base layout has no regions, and no block instance, because the page
    # renders the block's shell itself.
    Then I should see "Dimensions" in the "#page-header" "css_element"
    And I should see "Teaching practice" in the ".dims-page" "css_element"
    And "#theme_boost-drawers-blocks" "css_element" should not exist
    And ".block_dimensions.block" "css_element" should not exist
    And the ".dims-page" "css_element" should meet accessibility standards
    # As the site's start page: the site root lands on the page, through core's own redirect for
    # a URL start page, with the value the hook offered as the setting's key.
    And the following config values are set as admin:
      | defaulthomepage | /blocks/dimensions/index.php |
    When I am on site homepage
    Then I should see "Teaching practice" in the ".dims-page" "css_element"
    And "#theme_boost-drawers-blocks" "css_element" should not exist

  # Switched off, a start page stored earlier must still land somewhere: the Dashboard, where the
  # block already is.
  Scenario: The Dimensions page redirects to the Dashboard while it is off
    Given I log in as "student1"
    When I visit "/blocks/dimensions/index.php"
    Then ".dims-page" "css_element" should not exist
    And ".block_dimensions.block" "css_element" should exist
