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
 * Class to handle checking and signing of the privacy waiver.
 *
 * @package     mod_casa
 * @copyright   2016 UC Regents
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Class file.
 *
 * @package     mod_casa
 * @copyright   2016 UC Regents
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_casa_privacy_waiver {
    /**
     * Checks if user needs to sign privacy waiver.
     *
     * We need this to be very fast with as few database calls as possible,
     * because this method is called for every page load. We will try to do all
     * the checks before we query for waiver information with existing data.
     *
     * @param context $context
     * @param moodle_url $url
     * @param int $userid
     * @return boolean  If true, then user needs to sign waiver.
     */
    static public function check(context $context, moodle_url $url, $userid) {
        global $DB;
        $checkwaiver = false;

        // Don't bother checking if user is not logged in.
        if (!isloggedin() || isguestuser()) {
            return false;
        }

        // Check URL if it is a module/block that needs a privacy waiver.
        $sql = "SELECT c.official
                  FROM {context} cxt
                  JOIN {course_modules} cm ON (cxt.instanceid=cm.id)
                  JOIN {casa} c ON (cm.instance=c.id)
                 WHERE cxt.id=?";
        $official = $DB->get_field_sql($sql, array($context->id));
        if ($official != 1) {
            $checkwaiver = true;
        }

        // Is a page that needs a waiver.
        if (!empty($checkwaiver)) {
            // See if user needs to sign a waiver.
            $coursecontext = $context->get_course_context();
            if (self::check_user($coursecontext, $userid)) {
                // See if user signed the waiver.
                return !self::check_signed($coursecontext->instanceid, $context->id, $userid);
            }
        }

        return false;
    }

    /**
     * Checks if there is already a signed waiver for user.
     *
     * @param int $courseid
     * @param int $contextid
     * @param int $userid
     * @return boolean      Returns true if user signed waiver, otherwise false.
     */
    static public function check_signed($courseid, $contextid, $userid) {
        global $DB;
        return $DB->record_exists('lti_privacy_waiver',
                    array('courseid'    => $courseid,
                          'contextid'   => $contextid,
                          'userid'      => $userid));
    }

    /**
     * Checks if given user has a role that needs to sign a waiver.
     *
     * @param context_course $context
     * @param int $userid
     * @return boolean
     */
    static public function check_user(context_course $context, $userid) {
        global $DB;
        $doesroleexist = $DB->get_records('role', array('shortname'=>'student'));
        if (empty($doesroleexist)) {
            debugging("Role shortname not found in database table.");
            return false;
        }

        $rolesresult = get_user_roles($context);
        foreach ($rolesresult as $role) {
            if (in_array($role->shortname, array('student'))) {
                return true;
            }
        }
        return false;
    }

    /**
     * Returns link to get to the privacy waiver.
     *
     * @param context $context
     * @param moodle_url $url   The URL that a user was on, so that we can
     *                          redirect them back
     * return moodle_url
     */
    static public function get_link(context $context, moodle_url $url) {
        return new moodle_url('/mod/casa/privacywaiver.php',
                array('contextid'   => $context->id,
                      'return'      => $url->out_as_local_url()));
    }

    /**
     * Signs privacy waiver for given course, module, and user.
     *
     * @param int $courseid
     * @param int $contextid
     * @param int $userid
     *
     * @return boolean      Returns false if cannot sign waiver. Maybe because
     *                      of duplicate entry.
     * @throws dml_exception    Throws exception if cannot handle error.
     */
    static public function sign($courseid, $contextid, $userid) {
        global $DB;

        try {
            $DB->insert_record('lti_privacy_waiver',
                    array('courseid'    => $courseid,
                          'contextid'   => $contextid,
                          'userid'      => $userid,
                          'timestamp'   => time()));
        } catch (dml_exception $e) {
            // Check if it is already signed.
            if (self::check_signed($courseid, $contextid, $userid)) {
                return false;
            } else {
                throw $e;   // Cannot handle error.
            }
        }

        return true;
    }
}
