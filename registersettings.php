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
 * This file contains the script used to register a new external tool.
 *
 * It is used to create a new form used to configure the capabilities
 * and services to be offered to the tool provider.
 *
 * @package mod_casa
 * @copyright  2014 Vital Source Technologies http://vitalsource.com
 * @author     Stephen Vickers
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->dirroot.'/mod/casa/register_form.php');
require_once($CFG->dirroot.'/mod/casa/locallib.php');

$action       = optional_param('action', null, PARAM_ALPHANUMEXT);
$id           = optional_param('id', null, PARAM_INT);
$tab          = optional_param('tab', '', PARAM_ALPHAEXT);

// No guest autologin.
require_login(0, false);

$isupdate = !empty($id);
$pageurl = new moodle_url('/mod/casa/registersettings.php');
if ($isupdate) {
    $pageurl->param('id', $id);
}
$PAGE->set_url($pageurl);

admin_externalpage_setup('casatoolproxies');

$redirect = new moodle_url('/mod/casa/toolproxies.php', array('tab' => $tab));
$redirect = $redirect->out();

require_sesskey();

if ($action == 'delete') {
    casa_delete_tool_proxy($id);
    redirect($redirect);
}

$data = array();
if ($isupdate) {
    $data['isupdate'] = true;
}

$form = new mod_casa_register_types_form($pageurl, (object)$data);

if ($form->is_cancelled()) {
    redirect($redirect);
} else if ($data = $form->get_data()) {
    $id = casa_add_tool_proxy($data);
    redirect($redirect);
} else {
    $PAGE->set_title("{$SITE->shortname}: " . get_string('toolregistration', 'casa'));
    $PAGE->navbar->add(get_string('casa_administration', 'casa'), $redirect);

    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('toolregistration', 'casa'));
    echo $OUTPUT->box_start('generalbox');
    if ($action == 'update') {
        $toolproxy = casa_get_tool_proxy_config($id);
        $form->set_data($toolproxy);
        if ($toolproxy->state == CASA_TOOL_PROXY_STATE_ACCEPTED) {
            $form->disable_fields();
        }
    }
    $form->display();

    echo $OUTPUT->box_end();
    echo $OUTPUT->footer();
}
