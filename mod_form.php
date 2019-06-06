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
 * This file defines the main casa configuration form
 *
 * @package mod_casa
 * @copyright  2009 Marc Alier, Jordi Piguillem, Nikolas Galanis
 *  marc.alier@upc.edu
 * @copyright  2009 Universitat Politecnica de Catalunya http://www.upc.edu
 * @author     Marc Alier
 * @author     Jordi Piguillem
 * @author     Nikolas Galanis
 * @author     Chris Scribner
 * @copyright  2015 Vital Source Technologies http://vitalsource.com
 * @author     Stephen Vickers
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot.'/course/moodleform_mod.php');
require_once($CFG->dirroot.'/mod/casa/locallib.php');

class mod_casa_mod_form extends moodleform_mod {

    public function definition() {
        global $DB, $PAGE, $OUTPUT, $USER, $COURSE, $sesskey, $section;

        if ($type = optional_param('type', false, PARAM_ALPHA)) {
            component_callback("casasource_$type", 'add_instance_hook');
        }
        $sectionreturn = optional_param('sr', 0, PARAM_INT);
        $stradd = get_string('add', 'casa');
        $stredit = get_string('edit', 'casa');
        $strdelete = get_string('delete', 'casa');
        $strvalid = get_string('valid', 'casa');

        $this->typeid = 0;

        $mform =& $this->_form;
        // Adding the "general" fieldset, where all the common settings are shown.
        $mform->addElement('header', 'general', get_string('general', 'form'));
        
        // Tool settings.
        $attributes = array();
        if ($update = optional_param('update', false, PARAM_INT)) {
            $attributes['disabled'] = 'disabled';
        }
        $attributes['onchange'] = 'M.mod_casa.editor.contentItem(this);';
        $tooltypes = $mform->addElement('select', 'typeid', get_string('external_tool_type', 'casa'), array(), $attributes);
        $mform->addHelpButton('typeid', 'external_tool_type', 'casa');
        $toolproxy = array();
        foreach (casa_get_types_for_add_instance() as $id => $type) {
            if (!empty($type->toolproxyid)) {
                $toolproxy[] = $type->id;
                $attributes = array( 'globalTool' => 1, 'toolproxy' => 1);
                $enabledcapabilities = explode("\n", $type->enabledcapability);
                if (!in_array('Result.autocreate', $enabledcapabilities)) {
                    $attributes['nogrades'] = 1;
                }
                if (!in_array('Person.name.full', $enabledcapabilities) && !in_array('Person.name.family', $enabledcapabilities) &&
                    !in_array('Person.name.given', $enabledcapabilities)) {
                    $attributes['noname'] = 1;
                }
                if (!in_array('Person.email.primary', $enabledcapabilities)) {
                    $attributes['noemail'] = 1;
                }
            } else {
                if ($type->course == $COURSE->id) {
                    $attributes = array( 'editable' => 1, 'courseTool' => 1, 'domain' => $type->tooldomain );
                } else if ($id != 0) {
                    $attributes = array( 'globalTool' => 1, 'domain' => $type->tooldomain);
                    if (!$update) {
                        $config = casa_get_type_config($id);
                        if (isset($config['contentitem']) && $config['contentitem']) {
                            $contentitemurl = new moodle_url('/mod/casa/contentitem.php',
                                array('course' => $COURSE->id, 'section' => $section, 'id' => $id, 'sr' => $sectionreturn));
                            $attributes['contentitem'] = 1;
                            $attributes['contentitemurl'] = $contentitemurl->out(false);
                            $type->name = '&raquo; ' . $type->name;
                        }
                    }
                } else {
                    $attributes = array();
                }
            }
            $tooltypes->addOption($type->name, $id, $attributes);
        }

        // If there is only one resource configured for contentitem, then directly go there.
        $contentitems = array();
        foreach ($tooltypes->_options as $option) {
            if (isset($option['attr']['contentitem']) && !empty($option['attr']['contentitem'])) {
                $contentitems[] = $option['attr'];
            }
        }
        if (count($contentitems) === 1) {
            $contentitem = array_pop($contentitems);
            redirect($contentitem['contentitemurl']);
        }
        
        // Adding the standard "name" field.
        $mform->addElement('text', 'name', get_string('basiccasaname', 'casa'), array('size' => '64'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        // Adding the optional "intro" and "introformat" pair of fields.
        $this->standard_intro_elements(get_string('basiccasaintro', 'casa'));
        $mform->setAdvanced('introeditor');

        // Display the label to the right of the checkbox so it looks better & matches rest of the form.
        $coursedesc = $mform->getElement('showdescription');
        if (!empty($coursedesc)) {
            $coursedesc->setText(' ' . $coursedesc->getLabel());
            $coursedesc->setLabel('&nbsp');
        }

        $mform->setAdvanced('showdescription');

        $mform->addElement('checkbox', 'showtitlelaunch', '&nbsp;', ' ' . get_string('display_name', 'casa'));
        $mform->setAdvanced('showtitlelaunch');
        $mform->setDefault('showtitlelaunch', true);
        $mform->addHelpButton('showtitlelaunch', 'display_name', 'casa');

        $mform->addElement('checkbox', 'showdescriptionlaunch', '&nbsp;', ' ' . get_string('display_description', 'casa'));
        $mform->setAdvanced('showdescriptionlaunch');
        $mform->addHelpButton('showdescriptionlaunch', 'display_description', 'casa');

        $mform->addElement('text', 'toolurl', get_string('launch_url', 'casa'), array('size' => '64'));
        $mform->setType('toolurl', PARAM_TEXT);
        $mform->addHelpButton('toolurl', 'launch_url', 'casa');
        $mform->disabledIf('toolurl', 'typeid', 'neq', '0');

        $mform->addElement('text', 'securetoolurl', get_string('secure_launch_url', 'casa'), array('size' => '64'));
        $mform->setType('securetoolurl', PARAM_TEXT);
        $mform->setAdvanced('securetoolurl');
        $mform->addHelpButton('securetoolurl', 'secure_launch_url', 'casa');
        $mform->disabledIf('securetoolurl', 'typeid', 'neq', '0');

        $mform->addElement('hidden', 'urlmatchedtypeid', '', array( 'id' => 'id_urlmatchedtypeid' ));
        $mform->setType('urlmatchedtypeid', PARAM_INT);

        $launchoptions = array();
        $launchoptions[CASA_LAUNCH_CONTAINER_DEFAULT] = get_string('default', 'casa');
        $launchoptions[CASA_LAUNCH_CONTAINER_EMBED] = get_string('embed', 'casa');
        $launchoptions[CASA_LAUNCH_CONTAINER_EMBED_NO_BLOCKS] = get_string('embed_no_blocks', 'casa');
        $launchoptions[CASA_LAUNCH_CONTAINER_REPLACE_MOODLE_WINDOW] = get_string('existing_window', 'casa');
        $launchoptions[CASA_LAUNCH_CONTAINER_WINDOW] = get_string('new_window', 'casa');

        $mform->addElement('select', 'launchcontainer', get_string('launchinpopup', 'casa'), $launchoptions);
        $mform->setDefault('launchcontainer', CASA_LAUNCH_CONTAINER_DEFAULT);
        $mform->addHelpButton('launchcontainer', 'launchinpopup', 'casa');

        $mform->addElement('text', 'resourcekey', get_string('resourcekey', 'casa'));
        $mform->setType('resourcekey', PARAM_TEXT);
        $mform->setAdvanced('resourcekey');
        $mform->addHelpButton('resourcekey', 'resourcekey', 'casa');
        $mform->disabledIf('resourcekey', 'typeid', 'neq', '0');

        $mform->addElement('passwordunmask', 'password', get_string('password', 'casa'));
        $mform->setType('password', PARAM_TEXT);
        $mform->setAdvanced('password');
        $mform->addHelpButton('password', 'password', 'casa');
        $mform->disabledIf('password', 'typeid', 'neq', '0');

        $mform->addElement('textarea', 'instructorcustomparameters', get_string('custom', 'casa'), array('rows' => 4, 'cols' => 60));
        $mform->setType('instructorcustomparameters', PARAM_TEXT);
        $mform->setAdvanced('instructorcustomparameters');
        $mform->addHelpButton('instructorcustomparameters', 'custom', 'casa');

        $mform->addElement('text', 'icon', get_string('icon_url', 'casa'), array('size' => '64'));
        $mform->setType('icon', PARAM_TEXT);
        $mform->setAdvanced('icon');
        $mform->addHelpButton('icon', 'icon_url', 'casa');
        $mform->disabledIf('icon', 'typeid', 'neq', '0');

        $mform->addElement('text', 'secureicon', get_string('secure_icon_url', 'casa'), array('size' => '64'));
        $mform->setType('secureicon', PARAM_TEXT);
        $mform->setAdvanced('secureicon');
        $mform->addHelpButton('secureicon', 'secure_icon_url', 'casa');
        $mform->disabledIf('secureicon', 'typeid', 'neq', '0');

        // Add privacy preferences fieldset where users choose whether to send their data.
        $mform->addElement('header', 'privacy', get_string('privacy', 'casa'));

        $mform->addElement('advcheckbox', 'instructorchoicesendname', '&nbsp;', ' ' . get_string('share_name', 'casa'));
        $mform->setDefault('instructorchoicesendname', '1');
        $mform->addHelpButton('instructorchoicesendname', 'share_name', 'casa');
        $mform->disabledIf('instructorchoicesendname', 'typeid', 'in', $toolproxy);

        $mform->addElement('advcheckbox', 'instructorchoicesendemailaddr', '&nbsp;', ' ' . get_string('share_email', 'casa'));
        $mform->setDefault('instructorchoicesendemailaddr', '1');
        $mform->addHelpButton('instructorchoicesendemailaddr', 'share_email', 'casa');
        $mform->disabledIf('instructorchoicesendemailaddr', 'typeid', 'in', $toolproxy);

        $mform->addElement('advcheckbox', 'instructorchoiceacceptgrades', '&nbsp;', ' ' . get_string('accept_grades', 'casa'));
        $mform->setDefault('instructorchoiceacceptgrades', '1');
        $mform->addHelpButton('instructorchoiceacceptgrades', 'accept_grades', 'casa');
        $mform->disabledIf('instructorchoiceacceptgrades', 'typeid', 'in', $toolproxy);

        // Add standard course module grading elements.
        $this->standard_grading_coursemodule_elements();

        // Add standard elements, common to all modules.
        $this->standard_coursemodule_elements();
        $mform->setAdvanced('cmidnumber');

        // Add standard buttons, common to all modules.
        $this->add_action_buttons();

        $editurl = new moodle_url('/mod/casa/instructor_edit_tool_type.php',
                array('sesskey' => sesskey(), 'course' => $COURSE->id));
        $ajaxurl = new moodle_url('/mod/casa/ajax.php');

        $jsinfo = (object)array(
                        'edit_icon_url' => (string)$OUTPUT->pix_icon('t/edit', $stredit),
                        'add_icon_url' => (string)$OUTPUT->pix_icon('t/add', $stradd),
                        'delete_icon_url' => (string)$OUTPUT->pix_icon('t/delete', $strdelete),
                        'green_check_icon_url' => (string)$OUTPUT->pix_icon('i/valid', $strvalid),
                        'warning_icon_url' => (string)$OUTPUT->pix_icon('warning', 'casa'),
                        'instructor_tool_type_edit_url' => $editurl->out(false),
                        'ajax_url' => $ajaxurl->out(true),
                        'courseId' => $COURSE->id
                  );

        $module = array(
            'name' => 'mod_casa_edit',
            'fullpath' => '/mod/casa/mod_form.js',
            'requires' => array('base', 'io', 'querystring-stringify-simple', 'node', 'event', 'json-parse'),
            'strings' => array(
                array('addtype', 'casa'),
                array('edittype', 'casa'),
                array('deletetype', 'casa'),
                array('delete_confirmation', 'casa'),
                array('cannot_edit', 'casa'),
                array('cannot_delete', 'casa'),
                array('global_tool_types', 'casa'),
                array('course_tool_types', 'casa'),
                array('using_tool_configuration', 'casa'),
                array('domain_mismatch', 'casa'),
                array('custom_config', 'casa'),
                array('tool_config_not_found', 'casa'),
                array('forced_help', 'casa')
            ),
        );

        $PAGE->requires->js_init_call('M.mod_casa.editor.init', array(json_encode($jsinfo)), true, $module);

        // Don't let user change most LTI settings.
        $mform->hardFreeze(array('toolurl', 'securetoolurl', 'resourcekey', 
            'password', 'instructorcustomparameters', 'icon', 'secureicon',
            'instructorchoicesendname', 'instructorchoicesendemailaddr',
            'instructorchoiceacceptgrades'));
    }

}
