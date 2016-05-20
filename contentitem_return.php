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
 * Handle the return from the Tool Provider after selecting a content item.
 *
 * @package mod_casa
 * @copyright  2015 Vital Source Technologies http://vitalsource.com
 * @author     Stephen Vickers
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/course/modlib.php');
require_once($CFG->dirroot . '/mod/casa/lib.php');
require_once($CFG->dirroot . '/mod/casa/locallib.php');
require_once($CFG->dirroot . '/mod/casa/OAuth.php');
require_once($CFG->dirroot . '/mod/casa/TrivialStore.php');
require_once($CFG->dirroot . '/mod/url/lib.php');
require_once($CFG->dirroot . '/mod/url/locallib.php');

use moodle\mod\casa as casa;

$courseid = required_param('course', PARAM_INT);
$sectionid = required_param('section', PARAM_INT);
$id = required_param('id', PARAM_INT);
$sectionreturn = required_param('sr', PARAM_INT);
$messagetype = required_param('lti_message_type', PARAM_TEXT);
$version = required_param('lti_version', PARAM_TEXT);
$key = required_param('oauth_consumer_key', PARAM_RAW);

$items = optional_param('content_items', '', PARAM_RAW);
$errormsg = optional_param('lti_errormsg', '', PARAM_TEXT);
$msg = optional_param('lti_msg', '', PARAM_TEXT);

$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
$module = $DB->get_record('modules', array('name' => 'casa'), '*', MUST_EXIST);
$urlmodule = $DB->get_record('modules', array('name' => 'url'), '*', MUST_EXIST);
$tool = casa_get_type($id);
$typeconfig = casa_get_type_config($id);

require_login($course);
require_sesskey();

if ($key !== $typeconfig['resourcekey']) {
    throw new Exception('Consumer key is incorrect.');
}

$store = new casa\TrivialOAuthDataStore();
$store->add_consumer($key, $typeconfig['password']);

$server = new casa\OAuthServer($store);

$method = new casa\OAuthSignatureMethod_HMAC_SHA1();
$server->add_signature_method($method);
$request = casa\OAuthRequest::from_request();

try {
    $server->verify_request($request);
} catch (\Exception $e) {
    $message = $e->getMessage();
    debugging($e->getMessage() . "\n");
    throw new casa\OAuthException("OAuth signature failed: " . $message);
}

if ($items) {
    $items = json_decode($items);
    if ($items->{'@context'} !== 'http://purl.imsglobal.org/ctx/lti/v1/ContentItem') {
        throw new Exception('Invalid media type.');
    }
    if (!isset($items->{'@graph'}) || !is_array($items->{'@graph'}) || (count($items->{'@graph'}) > 1)) {
        throw new Exception('Invalid format.');
    }
}

$continueurl = course_get_url($course, $sectionid, array('sr' => $sectionreturn));
if (count($items->{'@graph'}) > 0) {
    foreach ($items->{'@graph'} as $item) {
        $moduleinfo = new stdClass();
        
        // Adds support for text/html media type & bypasses regular add routine.
        if (isset($item->mediaType) && $item->mediaType == 'text/html') {
            $moduleinfo->modulename = 'url';
            $moduleinfo->name = '';
            if (isset($item->title)) {
                $moduleinfo->name = $item->title;
            }
            if (empty($moduleinfo->name)) {
                $moduleinfo->name = $tool->name;
            }
            $moduleinfo->module = $urlmodule->id;
            $moduleinfo->section = $sectionid;
            $moduleinfo->visible = 1;
            if (isset($item->url)) {
                $moduleinfo->externalurl = $item->url;
            } else {
                continue; // can't do anything with this
            }
            $moduleinfo->display = get_config('url', 'display');
            $moduleinfo = add_moduleinfo($moduleinfo, $course, null);
            continue;
        }
        
        $moduleinfo->modulename = 'casa';
        $moduleinfo->name = '';
        if (isset($item->title)) {
            $moduleinfo->name = $item->title;
        }
        if (empty($moduleinfo->name)) {
            $moduleinfo->name = $tool->name;
        }
        $moduleinfo->module = $module->id;
        $moduleinfo->section = $sectionid;
        $moduleinfo->visible = 1;
        if (isset($item->url)) {
            $moduleinfo->toolurl = $item->url;
            $moduleinfo->typeid = 0;
        } else {
            $moduleinfo->typeid = $id;
        }
        // For now, ignore launchcontainer. See CCLE-5829.
//        $moduleinfo->launchcontainer = CASA_LAUNCH_CONTAINER_DEFAULT;
//        if (isset($item->placementAdvice->presentationDocumentTarget)) {
//            if ($item->presentationDocumentTarget === 'window') {
//                $moduleinfo->launchcontainer = CASA_LAUNCH_CONTAINER_WINDOW;
//            } else if ($item->presentationDocumentTarget === 'frame') {
//                $moduleinfo->launchcontainer = CASA_LAUNCH_CONTAINER_EMBED_NO_BLOCKS;
//            } else if ($item->presentationDocumentTarget === 'iframe') {
//                $moduleinfo->launchcontainer = CASA_LAUNCH_CONTAINER_EMBED;
//            }
//        }
        $moduleinfo->launchcontainer = CASA_LAUNCH_CONTAINER_DEFAULT;
        // Handle icon.
        if (isset($item->icon) && isset($item->icon->{'@id'})) {
            $moduleinfo->icon = $item->icon->{'@id'};
        }
        // Handle privacy settings. For now, default to sending name and email.
        $moduleinfo->instructorchoicesendname = 1;
        $moduleinfo->instructorchoicesendemailaddr = 1;
        // Handle custom variables.
        if (isset($item->custom)) {
            $moduleinfo->instructorcustomparameters = '';
            $first = true;
            foreach ($item->custom as $key => $value) {
                // Handle official flag.
                if ($key == 'official') {
                    $moduleinfo->official = $value;
                    continue;
                }
                // Handle passing of key/secret.
                if ($key == 'oauth_key') {
                    $moduleinfo->resourcekey = $value;
                    continue;
                }
                if ($key == 'oauth_secret') {
                    $moduleinfo->password = $value;
                    continue;
                }
                if (!$first) {
                    $moduleinfo->instructorcustomparameters .= "\n";
                }
                $moduleinfo->instructorcustomparameters .= "{$key}={$value}";
                $first = false;
            }
        }
        $moduleinfo = add_moduleinfo($moduleinfo, $course, null);
    }
    $clickhere = get_string('click_to_continue', 'casa', (object)array('link' => $continueurl->out()));
} else {
    $clickhere = get_string('return_to_course', 'casa', (object)array('link' => $continueurl->out()));
}

if (!empty($errormsg) || !empty($msg)) {

    $url = new moodle_url('/mod/casa/contentitem_return.php',
        array('course' => $courseid));
    $PAGE->set_url($url);

    $pagetitle = strip_tags($course->shortname);
    $PAGE->set_title($pagetitle);
    $PAGE->set_heading($course->fullname);

    $PAGE->set_pagelayout('embedded');

    echo $OUTPUT->header();

    if (!empty($casa) and !empty($context)) {
        echo $OUTPUT->heading(format_string($casa->name, true, array('context' => $context)));
    }

    if (!empty($errormsg)) {

        echo '<p style="color: #f00; font-weight: bold; margin: 1em;">';
        echo get_string('casa_launch_error', 'casa') . ' ';
        p($errormsg);
        echo "</p>\n";

    }

    if (!empty($msg)) {

        echo '<p style="margin: 1em;">';
        p($msg);
        echo "</p>\n";

    }

    echo "<p style=\"margin: 1em;\">{$clickhere}</p>";

    echo $OUTPUT->footer();

} else {

    $url = $continueurl->out(false);

    echo '<html><body>';

    $script = "
        <script type=\"text/javascript\">
        //<![CDATA[
            if(window != top){
                top.location.href = '{$url}';
            }
        //]]
        </script>
    ";

    $noscript = "
        <noscript>
            {$clickhere}
        </noscript>
    ";

    echo $script;
    echo $noscript;

    echo '</body></html>';

}
