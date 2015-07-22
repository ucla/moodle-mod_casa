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
 * LTI web service endpoints
 *
 * @package mod_casa
 * @copyright  Copyright (c) 2011 Moodlerooms Inc. (http://www.moodlerooms.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Chris Scribner
 */

define('NO_DEBUG_DISPLAY', true);
define('NO_MOODLE_COOKIES', true);

require_once(dirname(__FILE__) . "/../../config.php");
require_once($CFG->dirroot.'/mod/casa/locallib.php');
require_once($CFG->dirroot.'/mod/casa/servicelib.php');

// TODO: Switch to core oauthlib once implemented - MDL-30149.
use moodle\mod\casa as casa;

$rawbody = file_get_contents("php://input");

if (casa_should_log_request($rawbody)) {
    casa_log_request($rawbody);
}

foreach (casa\OAuthUtil::get_headers() as $name => $value) {
    if ($name === 'Authorization') {
        // TODO: Switch to core oauthlib once implemented - MDL-30149.
        $oauthparams = casa\OAuthUtil::split_header($value);

        $consumerkey = $oauthparams['oauth_consumer_key'];
        break;
    }
}

if (empty($consumerkey)) {
    throw new Exception('Consumer key is missing.');
}

$sharedsecret = casa_verify_message($consumerkey, casa_get_shared_secrets_by_key($consumerkey), $rawbody);

if ($sharedsecret === false) {
    throw new Exception('Message signature not valid');
}

// TODO MDL-46023 Replace this code with a call to the new library.
$origentity = libxml_disable_entity_loader(true);
$xml = simplexml_load_string($rawbody);
if (!$xml) {
    libxml_disable_entity_loader($origentity);
    throw new Exception('Invalid XML content');
}
libxml_disable_entity_loader($origentity);

$body = $xml->imsx_POXBody;
foreach ($body->children() as $child) {
    $messagetype = $child->getName();
}

switch ($messagetype) {
    case 'replaceResultRequest':
        try {
            $parsed = casa_parse_grade_replace_message($xml);
        } catch (Exception $e) {
            $responsexml = casa_get_response_xml(
                'failure',
                $e->getMessage(),
                uniqid(),
                'replaceResultResponse');

            echo $responsexml->asXML();
            break;
        }

        $casainstance = $DB->get_record('casa', array('id' => $parsed->instanceid));

        if (!casa_accepts_grades($casainstance)) {
            throw new Exception('Tool does not accept grades');
        }

        casa_verify_sourcedid($casainstance, $parsed);
        casa_set_session_user($parsed->userid);

        $gradestatus = casa_update_grade($casainstance, $parsed->userid, $parsed->launchid, $parsed->gradeval);

        $responsexml = casa_get_response_xml(
                $gradestatus ? 'success' : 'failure',
                'Grade replace response',
                $parsed->messageid,
                'replaceResultResponse'
        );

        echo $responsexml->asXML();

        break;

    case 'readResultRequest':
        $parsed = casa_parse_grade_read_message($xml);

        $casainstance = $DB->get_record('casa', array('id' => $parsed->instanceid));

        if (!casa_accepts_grades($casainstance)) {
            throw new Exception('Tool does not accept grades');
        }

        // Getting the grade requires the context is set.
        $context = context_course::instance($casainstance->course);
        $PAGE->set_context($context);

        casa_verify_sourcedid($casainstance, $parsed);

        $grade = casa_read_grade($casainstance, $parsed->userid);

        $responsexml = casa_get_response_xml(
                'success',  // Empty grade is also 'success'.
                'Result read',
                $parsed->messageid,
                'readResultResponse'
        );

        $node = $responsexml->imsx_POXBody->readResultResponse;
        $node = $node->addChild('result')->addChild('resultScore');
        $node->addChild('language', 'en');
        $node->addChild('textString', isset($grade) ? $grade : '');

        echo $responsexml->asXML();

        break;

    case 'deleteResultRequest':
        $parsed = casa_parse_grade_delete_message($xml);

        $casainstance = $DB->get_record('casa', array('id' => $parsed->instanceid));

        if (!casa_accepts_grades($casainstance)) {
            throw new Exception('Tool does not accept grades');
        }

        casa_verify_sourcedid($casainstance, $parsed);
        casa_set_session_user($parsed->userid);

        $gradestatus = casa_delete_grade($casainstance, $parsed->userid);

        $responsexml = casa_get_response_xml(
                $gradestatus ? 'success' : 'failure',
                'Grade delete request',
                $parsed->messageid,
                'deleteResultResponse'
        );

        echo $responsexml->asXML();

        break;

    default:
        // Fire an event if we get a web service request which we don't support directly.
        // This will allow others to extend the LTI services, which I expect to be a common
        // use case, at least until the spec matures.
        $data = new stdClass();
        $data->body = $rawbody;
        $data->xml = $xml;
        $data->messageid = casa_parse_message_id($xml);
        $data->messagetype = $messagetype;
        $data->consumerkey = $consumerkey;
        $data->sharedsecret = $sharedsecret;
        $eventdata = array();
        $eventdata['other'] = array();
        $eventdata['other']['messageid'] = $data->messageid;
        $eventdata['other']['messagetype'] = $messagetype;
        $eventdata['other']['consumerkey'] = $consumerkey;

        // Before firing the event, allow subplugins a chance to handle.
        if (casa_extend_casa_services($data)) {
            break;
        }

        // If an event handler handles the web service, it should set this global to true
        // So this code knows whether to send an "operation not supported" or not.
        global $casawebservicehandled;
        $casawebservicehandled = false;

        try {
            $event = \mod_casa\event\unknown_service_api_called::create($eventdata);
            $event->set_message_data($data);
            $event->trigger();
        } catch (Exception $e) {
            $casawebservicehandled = false;
        }

        if (!$casawebservicehandled) {
            $responsexml = casa_get_response_xml(
                'unsupported',
                'unsupported',
                 casa_parse_message_id($xml),
                 $messagetype
            );

            echo $responsexml->asXML();
        }

        break;
}
