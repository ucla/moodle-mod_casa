CASA plugin for Moodle
=============

This plugin helps with the integration of CASA (Community App Sharing
Architecture: http://www.imsglobal.org/casa/) into Moodle.

It is a rebranding of the existing LTI module with added support for LTI Content
Item. See: https://tracker.moodle.org/browse/MDL-49609

Installation
-----------

1. Add to your /mod folder either by downloading this plugin or adding it as a git submodule.
2. On your CASA instance, create a new LTI Consumer by providing Name, Key, Secret.
3. On your Moodle instance go to Site administration > Plugins > Activity modules > CASA > Manage external tool types
4. Click on "Add external tool configuration"
5. Add the Name, Key, Secret from your CASA instance.
6. For "Tool base URL", enter in your CASA URL + '/lti/launch'.
7. Click "Show more" and make sure "Show tool type when creating tool instances" and "Tool supports Content-Item message" are selected.
8. Click "Save changes".
9. Setup is done and when an instructor chooses CASA from the list of activities they will get redirected to your CASA instance and can add web applications.