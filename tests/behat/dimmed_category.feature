@block @block_filtered_course_list
Feature: Hidden categories are dimmed for those who can see them
    In order to tell which categories are hidden
    As an admin 
    I need to looked for dimmed categories

    @javascript
    Scenario: Displaying a dimmed category
        Given the following "categories" exist:
            | name  | category | idnumber | visible |
            | Test  | 0        | test     | 1       |
            | Cat 1 | test     | cat1     | 0       |
            | Cat 2 | test     | cat2     | 1       |
        And the following "courses" exist:
            | fullname  | shortname | category |
            | Course 11 | course11  | cat1     |
            | Course 21 | course21  | cat2     |
        And the following "course enrolments" exist:
            | user  | course   | role    |
            | admin | course11 | student |
            | admin | course21 | student |
        And I log in as "admin"
        And I set the following administration settings values:
            | block_filtered_course_list_filtertype | categories |
            | block_filtered_course_list_adminview  | own        |
        And I navigate to "Filtered course list" node in "Site administration>Plugins>Blocks"
        And I press "Blocks editing on"
        And I add the "filtered_course_list" block
        When I navigate to "Filtered course list" node in "Site administration>Plugins>Blocks"
        And I set the field "s__block_filtered_course_list_categories" to "Test"
        Then "Test" "link" should be visible
        And "Cat 1" "link" should be visible
        And "Cat 2" "link" should be visible
