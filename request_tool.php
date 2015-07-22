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
 * Submits a request to administrators to add a tool configuration for the requested site.
 *
 * @package    mod_casa
 * @copyright  Copyright (c) 2011 Moodlerooms Inc. (http://www.moodlerooms.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Chris Scribner
 */

require_once('../../config.php');
require_once($CFG->dirroot.'/mod/casa/lib.php');
require_once($CFG->dirroot.'/mod/casa/locallib.php');

$instanceid = required_param('instanceid', PARAM_INT);

$casa = $DB->get_record('casa', array('id' => $instanceid));
$course = $DB->get_record('course', array('id' => $casa->course));
$cm = get_coursemodule_from_instance('casa', $casa->id, $casa->course, false, MUST_EXIST);
$context = context_module::instance($cm->id);

require_login($course);

require_sesskey();

require_capability('mod/casa:requesttooladd', context_course::instance($casa->course));

$baseurl = casa_get_domain_from_url($casa->toolurl);

$url = new moodle_url('/mod/casa/request_tool.php', array('instanceid' => $instanceid));
$PAGE->set_url($url);

$pagetitle = strip_tags($course->shortname);
$PAGE->set_title($pagetitle);
$PAGE->set_heading($course->fullname);

$PAGE->set_pagelayout('incourse');

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($casa->name, true, array('context' => $context)));

// Add a tool type if one does not exist already.
if (!casa_get_tool_by_url_match($casa->toolurl, $casa->course, CASA_TOOL_STATE_ANY)) {
    // There are no tools (active, pending, or rejected) for the launch URL. Create a new pending tool.
    $tooltype = new stdClass();
    $toolconfig = new stdClass();

    $toolconfig->casa_toolurl = casa_get_domain_from_url($casa->toolurl);
    $toolconfig->casa_typename = $toolconfig->casa_toolurl;

    casa_add_type($tooltype, $toolconfig);

    echo get_string('casa_tool_request_added', 'casa');
} else {
    echo get_string('casa_tool_request_existing', 'casa');
}

echo $OUTPUT->footer();
