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
 * Page for signing privacy waiver.
 *
 * @package    mod_casa
 * @copyright  2014 UC Regents
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

// Get necessary variables.
$contextid = required_param('contextid', PARAM_INT);
$returnurl = optional_param('return', null, PARAM_LOCALURL);
$confirm = optional_param('confirm', 0, PARAM_BOOL);
$disagree = optional_param('disagree', 0, PARAM_BOOL);

// Validate variables.
$context = context::instance_by_id($contextid);
$coursecontext = $context->get_course_context();
$course = get_course($coursecontext->instanceid);
$courselink = new moodle_url('/course/view.php', array('id' => $course->id));
if (!empty($returnurl)) {
    $returnurl = new moodle_url($returnurl);
} else {
    // Else redirect back to course.
    $returnurl = $courselink;
}

// Setup page.
$PAGE->set_context($context);
$pageurl = mod_casa_privacy_waiver::get_link($context, $returnurl);
$PAGE->set_url($pageurl);
$PAGE->set_title(get_string('privacywaivertitle', 'mod_casa'));

// Get course module, if context is a module.
$cm = null;
if ($context->contextlevel == CONTEXT_MODULE) {
    $modinfo = get_fast_modinfo($course);
    $cm = $modinfo->get_cm($context->instanceid);
}

require_login($course, false, $cm);

// Check if user declined to sign the privacy waiver.
if (!empty($disagree) && confirm_sesskey()) {
    $message = get_string('privacywaiverfail', 'mod_casa');
    redirect($returnurl, $message);
}

// Check if user is signing the privacy waiver.
if (!empty($confirm) && confirm_sesskey()) {
    if (mod_casa_privacy_waiver::sign($course->id, $context->id, $USER->id)) {
        redirect($returnurl, 'Waiver signed', 0);
    } else {
        print_error('privacywaivererror', 'mod_casa');
    }
}

// Prepare agree button by adding confirm and sesskey.
$pageurl->param('confirm', 1);
$pageurl->param('sesskey', sesskey());
$agreebutton = new single_button($pageurl, get_string('privacywaiveragree', 'mod_casa'));

// Create cancel/disagree button.
$cancelurl = mod_casa_privacy_waiver::get_link($context, $courselink);
$cancelurl->param('disagree', 1);
$cancelurl->param('sesskey', sesskey());
$disagreebutton = new single_button($cancelurl, get_string('privacywaiverdisagree', 'mod_casa'));

// Display title of module or block in header.
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('privacywaiverheader', 'mod_casa'));

// Output waiver text.
$toolname = $context->get_context_name(false);
echo $OUTPUT->confirm(get_string('privacywaiverdesc', 'mod_casa', $toolname),
        $agreebutton, $disagreebutton);

$infolink = 'http://www2.ed.gov/policy/gen/guid/fpco/ferpa/index.html';
echo html_writer::tag('p', get_string('privacywaivermoreinfo', 'mod_casa',
        html_writer::link($infolink, $infolink, array('target' => '_blank'))));

echo $OUTPUT->footer();