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
 * This file defines de main basiccasa configuration form
 *
 * @package    mod_casa
 * @copyright  2009 Marc Alier, Jordi Piguillem, Nikolas Galanis
 *  marc.alier@upc.edu
 * @copyright  2009 Universitat Politecnica de Catalunya http://www.upc.edu
 * @author     Marc Alier
 * @author     Jordi Piguillem
 * @author     Nikolas Galanis
 * @author     Charles Severance
 * @author     Chris Scribner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->libdir.'/formslib.php');
require_once($CFG->dirroot.'/mod/casa/locallib.php');

class mod_casa_edit_types_form extends moodleform{
    public function definition() {
        global $CFG;

        $mform    =& $this->_form;

        $istool = $this->_customdata && $this->_customdata->istool;

        // Add basiccasa elements.
        $mform->addElement('header', 'setup', get_string('tool_settings', 'casa'));

        $mform->addElement('text', 'casa_typename', get_string('typename', 'casa'));
        $mform->setType('casa_typename', PARAM_TEXT);
        $mform->addHelpButton('casa_typename', 'typename', 'casa');
        $mform->addRule('casa_typename', null, 'required', null, 'client');

        $mform->addElement('text', 'casa_toolurl', get_string('toolurl', 'casa'), array('size' => '64'));
        $mform->setType('casa_toolurl', PARAM_TEXT);
        $mform->addHelpButton('casa_toolurl', 'toolurl', 'casa');
        if (!$istool) {
            $mform->addRule('casa_toolurl', null, 'required', null, 'client');
        } else {
            $mform->disabledIf('casa_toolurl', null);
        }

        if (!$istool) {
            $mform->addElement('text', 'casa_resourcekey', get_string('resourcekey_admin', 'casa'));
            $mform->setType('casa_resourcekey', PARAM_TEXT);
            $mform->addHelpButton('casa_resourcekey', 'resourcekey_admin', 'casa');

            $mform->addElement('passwordunmask', 'casa_password', get_string('password_admin', 'casa'));
            $mform->setType('casa_password', PARAM_TEXT);
            $mform->addHelpButton('casa_password', 'password_admin', 'casa');
        }

        if ($istool) {
            $mform->addElement('textarea', 'casa_parameters', get_string('parameter', 'casa'), array('rows' => 4, 'cols' => 60));
            $mform->setType('casa_parameters', PARAM_TEXT);
            $mform->addHelpButton('casa_parameters', 'parameter', 'casa');
            $mform->disabledIf('casa_parameters', null);
        }

        $mform->addElement('textarea', 'casa_customparameters', get_string('custom', 'casa'), array('rows' => 4, 'cols' => 60));
        $mform->setType('casa_customparameters', PARAM_TEXT);
        $mform->addHelpButton('casa_customparameters', 'custom', 'casa');
        $mform->setAdvanced('casa_customparameters');

        if (!$istool && !empty($this->_customdata->isadmin)) {
            $mform->addElement('checkbox', 'casa_coursevisible', '&nbsp;', ' ' . get_string('show_in_course', 'casa'));
            $mform->addHelpButton('casa_coursevisible', 'show_in_course', 'casa');
        } else {
            $mform->addElement('hidden', 'casa_coursevisible', '1');
        }
        $mform->setType('casa_coursevisible', PARAM_BOOL);

        $mform->addElement('hidden', 'typeid');
        $mform->setType('typeid', PARAM_INT);

        $launchoptions = array();
        $launchoptions[CASA_LAUNCH_CONTAINER_EMBED] = get_string('embed', 'casa');
        $launchoptions[CASA_LAUNCH_CONTAINER_EMBED_NO_BLOCKS] = get_string('embed_no_blocks', 'casa');
        $launchoptions[CASA_LAUNCH_CONTAINER_REPLACE_MOODLE_WINDOW] = get_string('existing_window', 'casa');
        $launchoptions[CASA_LAUNCH_CONTAINER_WINDOW] = get_string('new_window', 'casa');

        $mform->addElement('select', 'casa_launchcontainer', get_string('default_launch_container', 'casa'), $launchoptions);
        $mform->setDefault('casa_launchcontainer', CASA_LAUNCH_CONTAINER_EMBED_NO_BLOCKS);
        $mform->addHelpButton('casa_launchcontainer', 'default_launch_container', 'casa');
        $mform->setType('casa_launchcontainer', PARAM_INT);
        $mform->setAdvanced('casa_launchcontainer');

        if (!$istool) {
            $mform->addElement('checkbox', 'casa_contentitem', '&nbsp;', ' ' . get_string('contentitem', 'casa'));
            $mform->addHelpButton('casa_contentitem', 'contentitem', 'casa');
            $mform->setAdvanced('casa_contentitem');

            // Add privacy preferences fieldset where users choose whether to send their data.
            $mform->addElement('header', 'privacy', get_string('privacy', 'casa'));

            $options = array();
            $options[0] = get_string('never', 'casa');
            $options[1] = get_string('always', 'casa');
            $options[2] = get_string('delegate', 'casa');

            $mform->addElement('select', 'casa_sendname', get_string('share_name_admin', 'casa'), $options);
            $mform->setType('casa_sendname', PARAM_INT);
            $mform->setDefault('casa_sendname', '2');
            $mform->addHelpButton('casa_sendname', 'share_name_admin', 'casa');

            $mform->addElement('select', 'casa_sendemailaddr', get_string('share_email_admin', 'casa'), $options);
            $mform->setType('casa_sendemailaddr', PARAM_INT);
            $mform->setDefault('casa_sendemailaddr', '2');
            $mform->addHelpButton('casa_sendemailaddr', 'share_email_admin', 'casa');

            // LTI Extensions.

            // Add grading preferences fieldset where the tool is allowed to return grades.
            $mform->addElement('select', 'casa_acceptgrades', get_string('accept_grades_admin', 'casa'), $options);
            $mform->setType('casa_acceptgrades', PARAM_INT);
            $mform->setDefault('casa_acceptgrades', '2');
            $mform->addHelpButton('casa_acceptgrades', 'accept_grades_admin', 'casa');

            $mform->addElement('checkbox', 'casa_forcessl', '&nbsp;', ' ' . get_string('force_ssl', 'casa'), $options);
            $mform->setType('casa_forcessl', PARAM_BOOL);
            if (!empty($CFG->mod_casa_forcessl)) {
                $mform->setDefault('casa_forcessl', '1');
                $mform->freeze('casa_forcessl');
            } else {
                $mform->setDefault('casa_forcessl', '0');
            }
            $mform->addHelpButton('casa_forcessl', 'force_ssl', 'casa');

            if (!empty($this->_customdata->isadmin)) {
                // Add setup parameters fieldset.
                $mform->addElement('header', 'setupoptions', get_string('miscellaneous', 'casa'));

                // Adding option to change id that is placed in context_id.
                $idoptions = array();
                $idoptions[0] = get_string('id', 'casa');
                $idoptions[1] = get_string('courseid', 'casa');

                $mform->addElement('text', 'casa_organizationid', get_string('organizationid', 'casa'));
                $mform->setType('casa_organizationid', PARAM_TEXT);
                $mform->addHelpButton('casa_organizationid', 'organizationid', 'casa');

                $mform->addElement('text', 'casa_organizationurl', get_string('organizationurl', 'casa'));
                $mform->setType('casa_organizationurl', PARAM_TEXT);
                $mform->addHelpButton('casa_organizationurl', 'organizationurl', 'casa');
            }
        }

        /* Suppress this for now - Chuck
         * mform->addElement('text', 'casa_organizationdescr', get_string('organizationdescr', 'casa'))
         * mform->setType('casa_organizationdescr', PARAM_TEXT)
         * mform->addHelpButton('casa_organizationdescr', 'organizationdescr', 'casa')
         */

        /*
        // Add a hidden element to signal a tool fixing operation after a problematic backup - restore process
        //$mform->addElement('hidden', 'casa_fix');
        */

        $tab = optional_param('tab', '', PARAM_ALPHAEXT);
        $mform->addElement('hidden', 'tab', $tab);
        $mform->setType('tab', PARAM_ALPHAEXT);

        $courseid = optional_param('course', 1, PARAM_INT);
        $mform->addElement('hidden', 'course', $courseid);
        $mform->setType('course', PARAM_INT);

        // Add standard buttons, common to all modules.
        $this->add_action_buttons();

    }
}
