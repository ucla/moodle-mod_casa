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
 * This file contains a class definition for the Tool Proxy resource
 *
 * @package    casaservice_toolproxy
 * @copyright  2014 Vital Source Technologies http://vitalsource.com
 * @author     Stephen Vickers
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


namespace casaservice_toolproxy\local\resource;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/casa/OAuth.php');
require_once($CFG->dirroot . '/mod/casa/TrivialStore.php');

// TODO: Switch to core oauthlib once implemented - MDL-30149.
use moodle\mod\casa as casa;

/**
 * A resource implementing the Tool Proxy.
 *
 * @package    casaservice_toolproxy
 * @since      Moodle 2.8
 * @copyright  2014 Vital Source Technologies http://vitalsource.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class toolproxy extends \mod_casa\local\casaservice\resource_base {

    /**
     * Class constructor.
     *
     * @param casaservice_toolproxy\local\resource\toolproxy $service Service instance
     */
    public function __construct($service) {

        parent::__construct($service);
        $this->id = 'ToolProxy.collection';
        $this->template = '/toolproxy';
        $this->formats[] = 'application/vnd.ims.casa.v2.toolproxy+json';
        $this->methods[] = 'POST';

    }

    /**
     * Execute the request for this resource.
     *
     * @param mod_casa\local\casaservice\response $response  Response object for this request.
     */
    public function execute($response) {

        $ok = $this->check_tool_proxy(null, $response->get_request_data());
        if ($ok) {
            $toolproxy = $this->get_service()->get_tool_proxy();
        } else {
            $toolproxy = null;
            $response->set_code(401);
        }
        $tools = array();

        // Ensure all required elements are present in the Tool Proxy.
        if ($ok) {
            $toolproxyjson = json_decode($response->get_request_data());
            $ok = !empty($toolproxyjson);
            if (!$ok) {
                debugging('Tool proxy is not properly formed JSON');
            } else {
                $ok = isset($toolproxyjson->tool_profile->product_instance->product_info->product_family->vendor->code);
                $ok = $ok && isset($toolproxyjson->security_contract->shared_secret);
                $ok = $ok && isset($toolproxyjson->tool_profile->resource_handler);
                if (!$ok) {
                    debugging('One or more missing elements from tool proxy: vendor code, shared secret or resource handlers');
                }
            }
        }

        // Check all capabilities requested were offered.
        if ($ok) {
            $offeredcapabilities = explode("\n", $toolproxy->capabilityoffered);
            $resources = $toolproxyjson->tool_profile->resource_handler;
            $errors = array();
            foreach ($resources as $resource) {
                if (isset($resource->message)) {
                    foreach ($resource->message as $message) {
                        if (!in_array($message->message_type, $offeredcapabilities)) {
                            $errors[] = $message->message_type;
                        } else if (isset($resource->parameter)) {
                            foreach ($message->parameter as $parameter) {
                                if (isset($parameter->variable) && !in_array($parameter->variable, $offeredcapabilities)) {
                                    $errors[] = $parameter->variable;
                                }
                            }
                        }
                    }
                }
            }
            if (count($errors) > 0) {
                $ok = false;
                debugging('Tool proxy contains capabilities which were not offered: ' . implode(', ', $errors));
            }
        }

        // Check all services requested were offered (only tool services currently supported).
        if ($ok && isset($toolproxyjson->security_contract->tool_service)) {
            $contexts = casa_get_contexts($toolproxyjson);
            $profileservice = casa_get_service_by_name('profile');
            $profileservice->set_tool_proxy($toolproxy);
            $context = $profileservice->get_service_path() . $profileservice->get_resources()[0]->get_path() . '#';
            $offeredservices = explode("\n", $toolproxy->serviceoffered);
            $services = casa_get_services();
            $tpservices = $toolproxyjson->security_contract->tool_service;
            $errors = array();
            foreach ($tpservices as $service) {
                $fqid = casa_get_fqid($contexts, $service->service);
                if (substr($fqid, 0, strlen($context)) !== $context) {
                    $errors[] = $service->service;
                } else {
                    $id = explode('#', $fqid, 2);
                    $aservice = casa_get_service_by_resource_id($services, $id[1]);
                    $classname = explode('\\', get_class($aservice));
                    if (empty($aservice) || !in_array($classname[count($classname) - 1], $offeredservices)) {
                        $errors[] = $service->service;
                    }
                }
            }
            if (count($errors) > 0) {
                $ok = false;
                debugging('Tool proxy contains services which were not offered: ' . implode(', ', $errors));
            }
        }

        // Extract all launchable tools from the resource handlers.
        if ($ok) {
            $resources = $toolproxyjson->tool_profile->resource_handler;
            foreach ($resources as $resource) {
                $found = false;
                $tool = new \stdClass();
                foreach ($resource->message as $message) {
                    if ($message->message_type == 'basic-lti-launch-request') {
                        $found = true;
                        $tool->path = $message->path;
                        $tool->enabled_capability = $message->enabled_capability;
                        $tool->parameter = $message->parameter;
                        break;
                    }
                }
                if (!$found) {
                    continue;
                }

                $tool->name = $resource->resource_name->default_value;
                $tools[] = $tool;
            }
            $ok = count($tools) > 0;
            if (!$ok) {
                debugging('No launchable messages found in tool proxy');
            }
        }

        // Add tools and custom parameters.
        if ($ok) {
            $baseurl = '';
            if (isset($toolproxyjson->tool_profile->base_url_choice[0]->default_base_url)) {
                $baseurl = $toolproxyjson->tool_profile->base_url_choice[0]->default_base_url;
            }
            $securebaseurl = '';
            if (isset($toolproxyjson->tool_profile->base_url_choice[0]->secure_base_url)) {
                $securebaseurl = $toolproxyjson->tool_profile->base_url_choice[0]->secure_base_url;
            }
            foreach ($tools as $tool) {
                $config = new \stdClass();
                $config->casa_toolurl = "{$baseurl}{$tool->path}";
                $config->casa_typename = $tool->name;
                $config->casa_coursevisible = 1;
                $config->casa_forcessl = 0;

                $type = new \stdClass();
                $type->state = CASA_TOOL_STATE_PENDING;
                $type->toolproxyid = $toolproxy->id;
                $type->enabledcapability = implode("\n", $tool->enabled_capability);
                $type->parameter = self::casa_extract_parameters($tool->parameter);

                if (isset($resource->icon_info[0]->default_location->path)) {
                    $iconpath = $resource->icon_info[0]->default_location->path;
                    $type->icon = "{$baseurl}{$iconpath}";
                    if (!empty($securebaseurl)) {
                        $type->secureicon = "{$securebaseurl}{$iconpath}";
                    }
                }
                $ok = $ok && (casa_add_type($type, $config) !== false);
            }
            if (isset($toolproxyjson->custom)) {
                casa_set_tool_settings($toolproxyjson->custom, $toolproxy->id);
            }
        }

        if (!empty($toolproxy)) {
            if ($ok) {
                // If all went OK accept the tool proxy.
                $toolproxy->state = CASA_TOOL_PROXY_STATE_ACCEPTED;
                $toolproxy->toolproxy = $response->get_request_data();
                $toolproxy->secret = $toolproxyjson->security_contract->shared_secret;
                $toolproxy->vendorcode = $toolproxyjson->tool_profile->product_instance->product_info->product_family->vendor->code;

                $url = $this->get_endpoint();
                $body = <<< EOD
{
  "@context" : "http://purl.imsglobal.org/ctx/casa/v2/ToolProxyId",
  "@type" : "ToolProxy",
  "@id" : "{$url}",
  "tool_proxy_guid" : "{$toolproxy->guid}"
}
EOD;
                $response->set_code(201);
                $response->set_content_type('application/vnd.ims.casa.v2.toolproxy.id+json');
                $response->set_body($body);
            } else {
                // Otherwise reject the tool proxy.
                $toolproxy->state = CASA_TOOL_PROXY_STATE_REJECTED;
                $response->set_code(400);
            }
            casa_update_tool_proxy($toolproxy);
        } else {
            $response->set_code(400);
        }
    }

    /**
     * Extracts the message parameters from the tool proxy entry
     *
     * @param array $parameters     Parameter section of a message
     *
     * @return String  containing parameters
     */
    private static function casa_extract_parameters($parameters) {

        $params = array();
        foreach ($parameters as $parameter) {
            if (isset($parameter->variable)) {
                $value = '$' . $parameter->variable;
            } else {
                $value = $parameter->fixed;
                if (strlen($value) > 0) {
                    $first = substr($value, 0, 1);
                    if (($first == '$') || ($first == '\\')) {
                        $value = '\\' . $value;
                    }
                }
            }
            $params[] = "{$parameter->name}={$value}";
        }

        return implode("\n", $params);

    }

}
