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

require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/course/externallib.php');
require_once($CFG->dirroot . '/lib/coursecatlib.php');

class block_filtered_course_list extends block_base {
    public function init() {
        $this->title   = get_string('blockname', 'block_filtered_course_list');
    }

    public function has_config() {
        return true;
    }

    public function specialization() {
        $this->title = isset($this->config->title) ? $this->config->title : get_string('blockname', 'block_filtered_course_list');
    }

    public function get_content() {
        global $CFG, $USER, $DB, $OUTPUT;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content         = new stdClass;
        $this->content->text   = '';
        $this->content->footer = '';
        $context = context_system::instance();

        // Obtain values from our config settings.
        $filtertype = 'shortname';
        if (isset($CFG->block_filtered_course_list_filtertype)) {
            $filtertype = $CFG->block_filtered_course_list_filtertype;
        }

        $currentshortname = ' ';
        if (isset($CFG->block_filtered_course_list_currentshortname)) {
            $currentshortname = $CFG->block_filtered_course_list_currentshortname;
        }

        $futureshortname = ' ';
        if (isset($CFG->block_filtered_course_list_futureshortname)) {
            $futureshortname = $CFG->block_filtered_course_list_futureshortname;
        }

        $categoryids = ' ';
        if (isset($CFG->block_filtered_course_list_categories)) {
            $categoryids = $CFG->block_filtered_course_list_categories;
        }

        $adminview = 'all';
        if (isset($CFG->block_filtered_course_list_adminview)) {
            if ($CFG->block_filtered_course_list_adminview == 'own') {
                $adminview = 'own';
            }
        }

        $maxallcourse = 10;
        if (isset($CFG->block_filtered_course_list_maxallcourse)) {
            $maxallcourse = $CFG->block_filtered_course_list_maxallcourse;
        }

        /* Given that 'my courses' has not been disabled in the config,
         * these are the two types of user who should get to see 'my courses':
         * 1. A logged in user who is neither an admin nor a guest
         * 2. An admin, in the case that $adminview is set to 'own'
         */

        if (empty($CFG->disablemycourses) &&
            (!empty($USER->id) &&
            !has_capability('moodle/course:view', $context) &&
            !isguestuser()) ||
            (has_capability('moodle/course:view', $context) and $adminview == 'own')) {

            $allcourses = enrol_get_my_courses(null, 'visible DESC, fullname ASC');

            if ($allcourses) {
                switch ($filtertype) {
                    case 'shortname':
                        $filteredcourses = $this->_filter_by_shortname($allcourses,
                                                                   $currentshortname,
                                                                   $futureshortname);
                        break;

                    case 'categories':
                        $filteredcourses = $this->_filter_by_category($allcourses,
                                                                       $categoryids);
                        break;

                    case 'custom':
                        // We do not yet have a handler for custom filter types.

                        break;

                    default:
                        // This is unexpected.
                        break;
                }

                foreach ($filteredcourses as $section => $courslist) {
                    if (count($courslist) == 0) {
                        continue;
                    }
                    $this->content->text .= html_writer::tag('div', $section, array('class' => 'course-section'));
                    $this->content->text .= '<ul class="list">';

                    foreach ($courslist as $course) {
                        $this->content->text .= $this->_print_single_course($course);
                    }
                    $this->content->text .= '</ul>';
                    // If we can update any course of the view all isn't hidden.
                    // Show the view all courses link.
                    if (has_capability('moodle/course:update', $context) ||
                        empty($CFG->block_filtered_course_list_hideallcourseslink)) {
                        $this->content->footer = "<a href=\"$CFG->wwwroot/course/index.php\">" .
                                                 get_string('fulllistofcourses') .
                                                 "</a> ...";
                    }
                }
            }
        } else {
            // Parent = 0   ie top-level categories only.
            $categories = coursecat::get(0)->get_children();

            // Check we have categories.
            if ($categories) {
                // Just print top level category links.
                if (count($categories) > 1 ||
                   (count($categories) == 1 &&
                    current($categories)->coursecount > $maxallcourse)) {
                    $this->content->text .= '<ul class="list">';
                    foreach ($categories as $category) {
                        $linkcss = $category->visible ? "" : "dimmed";
                        $this->content->text .= html_writer::tag('li',
                            html_writer::tag('a', format_string($category->name),
                            array('href' => $CFG->wwwroot . '/course/category.php?id=' . $category->id)),
                            array('class' => $linkcss));
                    }
                    $this->content->text .= '</ul>';
                    $this->content->footer .= "<br><a href=\"$CFG->wwwroot/course/index.php\">" .
                                              get_string('searchcourses') .
                                              '</a> ...<br />';

                    // If we can update any course of the view all isn't hidden.
                    // Show the view all courses link.
                    if (has_capability('moodle/course:update', $context) ||
                        empty($CFG->block_filtered_course_list_hideallcourseslink)) {
                        $this->content->footer .= "<a href=\"$CFG->wwwroot/course/index.php\">" .
                                                  get_string('fulllistofcourses') .
                                                  '</a> ...<br>';
                    }

                    $this->title = get_string('blockname', 'block_filtered_course_list');
                } else {
                    // Just print course names of single category.
                    $category = array_shift($categories);
                    $courses = get_courses($category->id);

                    if ($courses) {
                        $this->content->text .= '<ul class="list">';
                        foreach ($courses as $course) {
                            $this->content->text .= $this->_print_single_course($course);
                        }
                        $this->content->text .= '</ul>';

                        // If we can update any course of the view all isn't hidden.
                        // Show the view all courses link.
                        if (has_capability('moodle/course:update', $context) ||
                            empty($CFG->block_filtered_course_list_hideallcourseslink)) {
                            $this->content->footer .= "<a href=\"$CFG->wwwroot/course/index.php\">" .
                                                      get_string('fulllistofcourses') .
                                                      '</a> ...';
                        }
                    }
                }
            }
        }
        return $this->content;
    }

    private function _print_single_course($course) {
        global $CFG;
        $linkcss = $course->visible ? "" : "dimmed";
        $html = html_writer::tag('li',
            html_writer::tag('a', format_string($course->fullname),
            array('href' => $CFG->wwwroot . '/course/view.php?id=' . $course->id,
                'title' => format_string($course->shortname))),
        array('class' => $linkcss));
        return $html;
    }

    private function _filter_by_shortname($courses, $currentshortname, $futureshortname) {
        global $CFG;
        $results = array(get_string('currentcourses', 'block_filtered_course_list') => array(),
                         get_string('futurecourses', 'block_filtered_course_list')  => array(),
                         get_string('othercourses', 'block_filtered_course_list')   => array());

        foreach ($courses as $course) {
            if ($course->id == SITEID) {
                continue;
            }

            if ($currentshortname && stristr($course->shortname, $currentshortname)) {
                $results[get_string('currentcourses', 'block_filtered_course_list')][] = $course;
            } else if ($futureshortname && stristr($course->shortname, $futureshortname)) {
                $results[get_string('futurecourses', 'block_filtered_course_list')][] = $course;
            } else if (empty($CFG->block_filtered_course_list_hideothercourses) ||
                (!$CFG->block_filtered_course_list_hideothercourses)) {
                $results[get_string('othercourses', 'block_filtered_course_list')][] = $course;
            }
        }

        return $results;
    }

    private function _filter_by_category($courses, $catids) {
        $mycats = core_course_external::get_categories(array(
            array('key' => 'id', 'value' => $catids)));
        $results = array();

        foreach ($mycats as $cat) {
            foreach ($courses as $course) {
                if ($course->id == SITEID) {
                    continue;
                }
                if ($course->category == $cat['id']) {
                    $results[$cat['name']][] = $course;
                }
            }
        }

        return $results;
    }
}
