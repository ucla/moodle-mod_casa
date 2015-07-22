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
 * This file defines the global casa administration form
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

/*
 * @var admin_settingpage $settings
 */
$modcasafolder = new admin_category('modcasafolder', new lang_string('pluginname', 'mod_casa'), $module->is_enabled() === false);
$ADMIN->add('modsettings', $modcasafolder);
$settings->visiblename = new lang_string('manage_tools', 'mod_casa');
$ADMIN->add('modcasafolder', $settings);
$ADMIN->add('modcasafolder', new admin_externalpage('casatoolproxies',
        get_string('manage_tool_proxies', 'casa'),
        new moodle_url('/mod/casa/toolproxies.php')));

foreach (core_plugin_manager::instance()->get_plugins_of_type('casasource') as $plugin) {
    /*
     * @var \mod_casa\plugininfo\casasource $plugin
     */
    $plugin->load_settings($ADMIN, 'modcasafolder', $hassiteconfig);
}

$toolproxiesurl = new moodle_url('/mod/casa/toolproxies.php');
$toolproxiesurl = $toolproxiesurl->out();

if ($ADMIN->fulltree) {
    require_once($CFG->dirroot.'/mod/casa/locallib.php');

    $configuredtoolshtml = '';
    $pendingtoolshtml = '';
    $rejectedtoolshtml = '';

    $active = get_string('active', 'casa');
    $pending = get_string('pending', 'casa');
    $rejected = get_string('rejected', 'casa');

    // Gather strings used for labels in the inline JS.
    $PAGE->requires->strings_for_js(
        array(
            'typename',
            'baseurl',
            'action',
            'createdon'
        ),
        'mod_casa'
    );

    $types = casa_filter_get_types(get_site()->id);

    $configuredtools = casa_filter_tool_types($types, CASA_TOOL_STATE_CONFIGURED);

    $configuredtoolshtml = casa_get_tool_table($configuredtools, 'casa_configured');

    $pendingtools = casa_filter_tool_types($types, CASA_TOOL_STATE_PENDING);

    $pendingtoolshtml = casa_get_tool_table($pendingtools, 'casa_pending');

    $rejectedtools = casa_filter_tool_types($types, CASA_TOOL_STATE_REJECTED);

    $rejectedtoolshtml = casa_get_tool_table($rejectedtools, 'casa_rejected');

    $tab = optional_param('tab', '', PARAM_ALPHAEXT);
    $activeselected = '';
    $pendingselected = '';
    $rejectedselected = '';
    switch ($tab) {
        case 'casa_pending':
            $pendingselected = 'class="selected"';
            break;
        case 'casa_rejected':
            $rejectedselected = 'class="selected"';
            break;
        default:
            $activeselected = 'class="selected"';
            break;
    }
    $addtype = get_string('addtype', 'casa');
    $config = get_string('manage_tool_proxies', 'casa');

    $addtypeurl = "{$CFG->wwwroot}/mod/casa/typessettings.php?action=add&amp;sesskey={$USER->sesskey}";

    $template = <<< EOD
<div id="casa_tabs" class="yui-navset">
    <ul id="casa_tab_heading" class="yui-nav" style="display:none">
        <li {$activeselected}>
            <a href="#tab1">
                <em>$active</em>
            </a>
        </li>
        <li {$pendingselected}>
            <a href="#tab2">
                <em>$pending</em>
            </a>
        </li>
        <li {$rejectedselected}>
            <a href="#tab3">
                <em>$rejected</em>
            </a>
        </li>
    </ul>
    <div class="yui-content">
        <div>
            <div><a style="margin-top:.25em" href="{$addtypeurl}">{$addtype}</a></div>
            $configuredtoolshtml
        </div>
        <div>
            $pendingtoolshtml
        </div>
        <div>
            $rejectedtoolshtml
        </div>
    </div>
</div>

<script type="text/javascript">
//<![CDATA[
    YUI().use('yui2-tabview', 'yui2-datatable', function(Y) {
        //If javascript is disabled, they will just see the three tabs one after another
        var casa_tab_heading = document.getElementById('casa_tab_heading');
        casa_tab_heading.style.display = '';

        new Y.YUI2.widget.TabView('casa_tabs');

        var setupTools = function(id, sort){
            var casa_tools = Y.YUI2.util.Dom.get(id);

            if(casa_tools){
                var dataSource = new Y.YUI2.util.DataSource(casa_tools);

                var configuredColumns = [
                    {key:'name', label: M.util.get_string('typename', 'mod_casa'), sortable: true},
                    {key:'baseURL', label: M.util.get_string('baseurl', 'mod_casa'), sortable: true},
                    {key:'timecreated', label: M.util.get_string('createdon', 'mod_casa'), sortable: true},
                    {key:'action', label: M.util.get_string('action', 'mod_casa')}
                ];

                dataSource.responseType = Y.YUI2.util.DataSource.TYPE_HTMLTABLE;
                dataSource.responseSchema = {
                    fields: [
                        {key:'name'},
                        {key:'baseURL'},
                        {key:'timecreated'},
                        {key:'action'}
                    ]
                };

                new Y.YUI2.widget.DataTable(id + '_container', configuredColumns, dataSource,
                    {
                        sortedBy: sort
                    }
                );
            }
        };

        setupTools('casa_configured_tools', {key:'name', dir:'asc'});
        setupTools('casa_pending_tools', {key:'timecreated', dir:'desc'});
        setupTools('casa_rejected_tools', {key:'timecreated', dir:'desc'});
    });
//]]
</script>
EOD;
    $settings->add(new admin_setting_heading('casa_types', new lang_string('external_tool_types', 'casa') .
        $OUTPUT->help_icon('main_admin', 'casa'), $template));
}

// Tell core we already added the settings structure.
$settings = null;

