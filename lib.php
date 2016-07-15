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
//
// This file is part of BasicLTI4Moodle
//
// BasicLTI4Moodle is an IMS BasicLTI (Basic Learning Tools for Interoperability)
// consumer for Moodle 1.9 and Moodle 2.0. BasicLTI is a IMS Standard that allows web
// based learning tools to be easily integrated in LMS as native ones. The IMS BasicLTI
// specification is part of the IMS standard Common Cartridge 1.1 Sakai and other main LMS
// are already supporting or going to support BasicLTI. This project Implements the consumer
// for Moodle. Moodle is a Free Open source Learning Management System by Martin Dougiamas.
// BasicLTI4Moodle is a project iniciated and leaded by Ludo(Marc Alier) and Jordi Piguillem
// at the GESSI research group at UPC.
// SimpleLTI consumer for Moodle is an implementation of the early specification of LTI
// by Charles Severance (Dr Chuck) htp://dr-chuck.com , developed by Jordi Piguillem in a
// Google Summer of Code 2008 project co-mentored by Charles Severance and Marc Alier.
//
// BasicLTI4Moodle is copyright 2009 by Marc Alier Forment, Jordi Piguillem and Nikolas Galanis
// of the Universitat Politecnica de Catalunya http://www.upc.edu
// Contact info: Marc Alier Forment granludo @ gmail.com or marc.alier @ upc.edu.

/**
 * This file contains a library of functions and constants for the casa module
 *
 * @package    mod_casa
 * @copyright  2009 Marc Alier, Jordi Piguillem, Nikolas Galanis
 *  marc.alier@upc.edu
 * @copyright  2009 Universitat Politecnica de Catalunya http://www.upc.edu
 * @author     Marc Alier
 * @author     Jordi Piguillem
 * @author     Nikolas Galanis
 * @author     Chris Scribner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

/**
 * Returns all other caps used in module.
 *
 * @return array
 */
function casa_get_extra_capabilities() {
    return array('moodle/site:accessallgroups');
}

/**
 * List of features supported in URL module
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed True if module supports feature, false if not, null if doesn't know
 */
function casa_supports($feature) {
    switch ($feature) {
        case FEATURE_GROUPS:
        case FEATURE_GROUPINGS:
            return false;
        case FEATURE_MOD_INTRO:
        case FEATURE_COMPLETION_TRACKS_VIEWS:
        case FEATURE_GRADE_HAS_GRADE:
        case FEATURE_GRADE_OUTCOMES:
        case FEATURE_BACKUP_MOODLE2:
        case FEATURE_SHOW_DESCRIPTION:
            return true;

        default:
            return null;
    }
}

/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod.html) this function
 * will create a new instance and return the id number
 * of the new instance.
 *
 * @param object $instance An object from the form in mod.html
 * @return int The id of the newly inserted basiccasa record
 **/
function casa_add_instance($casa, $mform) {
    global $DB, $CFG;
    require_once($CFG->dirroot.'/mod/casa/locallib.php');

    if (!isset($casa->toolurl)) {
        $casa->toolurl = '';
    }

    $casa->timecreated = time();
    $casa->timemodified = $casa->timecreated;
    $casa->servicesalt = uniqid('', true);

    casa_force_type_config_settings($casa, casa_get_type_config_by_instance($casa));

    if (empty($casa->typeid) && isset($casa->urlmatchedtypeid)) {
        $casa->typeid = $casa->urlmatchedtypeid;
    }

    if (!isset($casa->instructorchoiceacceptgrades) || $casa->instructorchoiceacceptgrades != CASA_SETTING_ALWAYS) {
        // The instance does not accept grades back from the provider, so set to "No grade" value 0.
        $casa->grade = 0;
    }

    $casa->id = $DB->insert_record('casa', $casa);

    if (isset($casa->instructorchoiceacceptgrades) && $casa->instructorchoiceacceptgrades == CASA_SETTING_ALWAYS) {
        if (!isset($casa->cmidnumber)) {
            $casa->cmidnumber = '';
        }

        casa_grade_item_update($casa);
    }

    return $casa->id;
}

/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod.html) this function
 * will update an existing instance with new data.
 *
 * @param object $instance An object from the form in mod.html
 * @return boolean Success/Fail
 **/
function casa_update_instance($casa, $mform) {
    global $DB, $CFG;
    require_once($CFG->dirroot.'/mod/casa/locallib.php');

    $casa->timemodified = time();
    $casa->id = $casa->instance;

    if (!isset($casa->showtitlelaunch)) {
        $casa->showtitlelaunch = 0;
    }

    if (!isset($casa->showdescriptionlaunch)) {
        $casa->showdescriptionlaunch = 0;
    }

    casa_force_type_config_settings($casa, casa_get_type_config_by_instance($casa));

    if (isset($casa->instructorchoiceacceptgrades) && $casa->instructorchoiceacceptgrades == CASA_SETTING_ALWAYS) {
        casa_grade_item_update($casa);
    } else {
        // Instance is no longer accepting grades from Provider, set grade to "No grade" value 0.
        $casa->grade = 0;
        $casa->instructorchoiceacceptgrades = 0;

        casa_grade_item_delete($casa);
    }

    if ($casa->typeid == 0 && isset($casa->urlmatchedtypeid)) {
        $casa->typeid = $casa->urlmatchedtypeid;
    }

    return $DB->update_record('casa', $casa);
}

/**
 * Given an ID of an instance of this module,
 * this function will permanently delete the instance
 * and any data that depends on it.
 *
 * @param int $id Id of the module instance
 * @return boolean Success/Failure
 **/
function casa_delete_instance($id) {
    global $DB;

    if (! $basiccasa = $DB->get_record("casa", array("id" => $id))) {
        return false;
    }

    $result = true;

    // Delete any dependent records here.
    casa_grade_item_delete($basiccasa);

    $casatype = $DB->get_record('casa_types', array('id' => $basiccasa->typeid));
    $DB->delete_records('casa_tool_settings',
        array('toolproxyid' => $casatype->toolproxyid, 'course' => $basiccasa->course, 'coursemoduleid' => $id));

    return $DB->delete_records("casa", array("id" => $basiccasa->id));
}

/**
 * Given a coursemodule object, this function returns the extra
 * information needed to print this activity in various places.
 * For this module we just need to support external urls as
 * activity icons
 *
 * @param stdClass $coursemodule
 * @return cached_cm_info info
 */
function casa_get_coursemodule_info($coursemodule) {
    global $DB, $CFG;
    require_once($CFG->dirroot.'/mod/casa/locallib.php');

    if (!$casa = $DB->get_record('casa', array('id' => $coursemodule->instance),
            'icon, secureicon, intro, introformat, name, toolurl, launchcontainer')) {
        return null;
    }

    $info = new cached_cm_info();

    // We want to use the right icon based on whether the
    // current page is being requested over http or https.
    if (casa_request_is_using_ssl() && !empty($casa->secureicon)) {
        $info->iconurl = new moodle_url($casa->secureicon);
    } else if (!empty($casa->icon)) {
        $info->iconurl = new moodle_url($casa->icon);
    }

    if ($coursemodule->showdescription) {
        // Convert intro to html. Do not filter cached version, filters run at display time.
        $info->content = format_module_intro('casa', $casa, $coursemodule->id, false);
    }

    // Does the link open in a new window?
    $tool = casa_get_tool_by_url_match($casa->toolurl);
    if ($tool) {
        $toolconfig = casa_get_type_config($tool->id);
    } else {
        $toolconfig = array();
    }
    $launchcontainer = casa_get_launch_container($casa, $toolconfig);
    if ($launchcontainer == CASA_LAUNCH_CONTAINER_WINDOW) {
        $launchurl = new moodle_url('/mod/casa/launch.php', array('id' => $coursemodule->id));
        $info->onclick = "window.open('" . $launchurl->out(false) . "', 'casa'); return false;";
    }

    $info->name = $casa->name;

    return $info;
}

/**
 * Return a small object with summary information about what a
 * user has done with a given particular instance of this module
 * Used for user activity reports.
 * $return->time = the time they did it
 * $return->info = a short text description
 *
 * @return null
 * @TODO: implement this moodle function (if needed)
 **/
function casa_user_outline($course, $user, $mod, $basiccasa) {
    return null;
}

/**
 * Print a detailed representation of what a user has done with
 * a given particular instance of this module, for user activity reports.
 *
 * @return boolean
 * @TODO: implement this moodle function (if needed)
 **/
function casa_user_complete($course, $user, $mod, $basiccasa) {
    return true;
}

/**
 * Given a course and a time, this module should find recent activity
 * that has occurred in basiccasa activities and print it out.
 * Return true if there was output, or false is there was none.
 *
 * @uses $CFG
 * @return boolean
 * @TODO: implement this moodle function
 **/
function casa_print_recent_activity($course, $isteacher, $timestart) {
    return false;  //  True if anything was printed, otherwise false.
}

/**
 * Function to be run periodically according to the moodle cron
 * This function searches for things that need to be done, such
 * as sending out mail, toggling flags etc ...
 *
 * @uses $CFG
 * @return boolean
 **/
function casa_cron () {
    return true;
}

/**
 * Must return an array of grades for a given instance of this module,
 * indexed by user.  It also returns a maximum allowed grade.
 *
 * Example:
 *    $return->grades = array of grades;
 *    $return->maxgrade = maximum allowed grade;
 *
 *    return $return;
 *
 * @param int $basiccasaid ID of an instance of this module
 * @return mixed Null or object with an array of grades and with the maximum grade
 *
 * @TODO: implement this moodle function (if needed)
 **/
function casa_grades($basiccasaid) {
    return null;
}

/**
 * This function returns if a scale is being used by one basiccasa
 * it it has support for grading and scales. Commented code should be
 * modified if necessary. See forum, glossary or journal modules
 * as reference.
 *
 * @param int $basiccasaid ID of an instance of this module
 * @return mixed
 *
 * @TODO: implement this moodle function (if needed)
 **/
function casa_scale_used ($basiccasaid, $scaleid) {
    $return = false;

    // $rec = get_record("basiccasa","id","$basiccasaid","scale","-$scaleid");
    //
    // if (!empty($rec)  && !empty($scaleid)) {
    //     $return = true;
    // }

    return $return;
}

/**
 * Checks if scale is being used by any instance of basiccasa.
 * This function was added in 1.9
 *
 * This is used to find out if scale used anywhere
 * @param $scaleid int
 * @return boolean True if the scale is used by any basiccasa
 *
 */
function casa_scale_used_anywhere($scaleid) {
    global $DB;

    if ($scaleid and $DB->record_exists('casa', array('grade' => -$scaleid))) {
        return true;
    } else {
        return false;
    }
}

/**
 * Execute post-install custom actions for the module
 * This function was added in 1.9
 *
 * @return boolean true if success, false on error
 */
function casa_install() {
     return true;
}

/**
 * Execute post-uninstall custom actions for the module
 * This function was added in 1.9
 *
 * @return boolean true if success, false on error
 */
function casa_uninstall() {
    return true;
}

/**
 * Returns available Basic LTI types
 *
 * @return array of basicLTI types
 */
function casa_get_casa_types() {
    global $DB;

    return $DB->get_records('casa_types');
}

/**
 * Create grade item for given basiccasa
 *
 * @category grade
 * @param object $basiccasa object with extra cmidnumber
 * @param mixed optional array/object of grade(s); 'reset' means reset grades in gradebook
 * @return int 0 if ok, error code otherwise
 */
function casa_grade_item_update($basiccasa, $grades = null) {
    global $CFG;
    require_once($CFG->libdir.'/gradelib.php');

    $params = array('itemname' => $basiccasa->name, 'idnumber' => $basiccasa->cmidnumber);

    if ($basiccasa->grade > 0) {
        $params['gradetype'] = GRADE_TYPE_VALUE;
        $params['grademax']  = $basiccasa->grade;
        $params['grademin']  = 0;

    } else if ($basiccasa->grade < 0) {
        $params['gradetype'] = GRADE_TYPE_SCALE;
        $params['scaleid']   = -$basiccasa->grade;

    } else {
        $params['gradetype'] = GRADE_TYPE_TEXT; // Allow text comments only.
    }

    if ($grades === 'reset') {
        $params['reset'] = true;
        $grades = null;
    }

    return grade_update('mod/casa', $basiccasa->course, 'mod', 'casa', $basiccasa->id, 0, $grades, $params);
}

/**
 * Delete grade item for given basiccasa
 *
 * @category grade
 * @param object $basiccasa object
 * @return object basiccasa
 */
function casa_grade_item_delete($basiccasa) {
    global $CFG;
    require_once($CFG->libdir.'/gradelib.php');

    return grade_update('mod/casa', $basiccasa->course, 'mod', 'casa', $basiccasa->id, 0, null, array('deleted' => 1));
}

function casa_extend_settings_navigation($settings, $parentnode) {
    global $PAGE;

    if (has_capability('mod/casa:manage', context_module::instance($PAGE->cm->id))) {
        $keys = $parentnode->get_children_key_list();

        $node = navigation_node::create('Submissions',
            new moodle_url('/mod/casa/grade.php', array('id' => $PAGE->cm->id)),
            navigation_node::TYPE_SETTING, null, 'mod_casa_submissions');

        $parentnode->add_node($node, $keys[1]);
    }
}

/**
 * Log post actions
 *
 * @return array
 */
function casa_get_post_actions() {
    return array();
}

/**
 * Log view actions
 *
 * @return array
 */
function casa_get_view_actions() {
    return array('view all', 'view');
}
