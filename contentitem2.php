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
 * Handle sending a user to a tool provider to initiate a content-item selection.
 *
 * @package mod_casa
 * @copyright  2015 Vital Source Technologies http://vitalsource.com
 * @author     Stephen Vickers
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("../../config.php");
require_once($CFG->dirroot.'/mod/casa/lib.php');
require_once($CFG->dirroot.'/mod/casa/locallib.php');

$courseid = required_param('course', PARAM_INT);
$sectionid = required_param('section', PARAM_INT);
$id = required_param('id', PARAM_INT);
$sectionreturn = required_param('sr', PARAM_INT);

$tool = casa_get_type($id);
$typeconfig = casa_get_type_config($id);

$title = optional_param('title', $tool->name, PARAM_TEXT);

$key = $typeconfig['resourcekey'];
$secret = $typeconfig['password'];


$endpoint = !empty($instance->toolurl) ? $instance->toolurl : $typeconfig['toolurl'];
$endpoint = trim($endpoint);

// If the current request is using SSL and a secure tool URL is specified, use it.
if (casa_request_is_using_ssl() && !empty($instance->securetoolurl)) {
    $endpoint = trim($instance->securetoolurl);
}

// If SSL is forced, use the secure tool url if specified. Otherwise, make sure https is on the normal launch URL.
if (isset($typeconfig['forcessl']) && ($typeconfig['forcessl'] == '1')) {
    if (!empty($instance->securetoolurl)) {
        $endpoint = trim($instance->securetoolurl);
    }

    $endpoint = casa_ensure_url_is_https($endpoint);
} else {
    if (!strstr($endpoint, '://')) {
        $endpoint = 'http://' . $endpoint;
    }
}

$requestparams['lti_version'] = 'LTI-1p0';
$requestparams['lti_message_type'] = 'ContentItemSelectionRequest';

$requestparams['accept_media_types'] = 'application/vnd.ims.lti.v1.launch+json';
$requestparams['accept_presentation_document_targets'] = 'frame,iframe,window';
$requestparams['accept_unsigned'] = 'false';
$requestparams['accept_multiple'] = 'false';
$requestparams['auto_create'] = 'true';
$requestparams['can_confirm'] = 'false';
$requestparams['accept_copy_advice'] = 'false';
$requestparams['text'] = $title;
$requestparams['title'] = '';

$customstr = '';
if (isset($typeconfig['customparameters'])) {
    $customstr = $typeconfig['customparameters'];
}
$requestparams = array_merge($requestparams, casa_build_custom_parameters(null, $tool, null, $requestparams, $customstr,
    '', false));


$returnurlparams = array('course' => $courseid,
                         'section' => $sectionid,
                         'id' => $id,
                         'sr' => $sectionreturn,
                         'sesskey' => sesskey());

// Add the return URL. We send the launch container along to help us avoid frames-within-frames when the user returns.
$url = new \moodle_url('/mod/casa/contentitem_return.php', $returnurlparams);
$returnurl = $url->out(false);

if (isset($typeconfig['forcessl']) && ($typeconfig['forcessl'] == '1')) {
    $returnurl = casa_ensure_url_is_https($returnurl);
}

$requestparams['content_item_return_url'] = $returnurl;


$parms = casa_sign_parameters($requestparams, $endpoint, "POST", $key, $secret);

$endpointurl = new \moodle_url($endpoint);
$endpointparams = $endpointurl->params();

// Strip querystring params in endpoint url from $parms to avoid duplication.
if (!empty($endpointparams) && !empty($parms)) {
    foreach (array_keys($endpointparams) as $paramname) {
        if (isset($parms[$paramname])) {
            unset($parms[$paramname]);
        }
    }
}

$content = casa_post_launch_html($parms, $endpoint, false);

echo $content;
