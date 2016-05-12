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

/**
 * Class to delete privacy waivers for deleted blocks and modules.
 *
 * @package     mod_casa
 * @copyright   2014 UC Regents
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Class file.
 *
 * @package     mod_casa
 * @copyright   2014 UC Regents
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_casa_privacy_waiver_observer {
    /**
     * Delete signed waiver for deleted course module.
     *
     * @param \core\event\course_module_deleted $event
     */
    static public function deleted_mod(\core\event\course_module_deleted $event) {
        global $DB;
        $DB->delete_records('lti_privacy_waiver',
                array('contextid' => $event->contextid));
    }

    /**
     * Delete signed waiver for deleted course.
     *
     * @param \core\event\course_deleted $event
     */
    static public function deleted_course(\core\event\course_deleted $event) {
        global $DB;
        $DB->delete_records('lti_privacy_waiver',
                array('courseid' => $event->courseid));
    }
}
