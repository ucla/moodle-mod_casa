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
 * Event handlers.
 * 
 * This file contains the event handlers for the Moodle event API.
 * 
 * @package mod_casa
 * @copyright 2013 UC Regents
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$observers = array(
    array (
        'eventname' => '\core\event\course_module_deleted',
        'callback'  => 'mod_casa_privacy_waiver_observer::deleted_mod',
        'internal'  => false, // This means that we get events only after transaction commit.
        'priority'  => 1000,
    ),
    array (
        'eventname' => '\core\event\course_deleted',
        'callback'  => 'mod_casa_privacy_waiver_observer::deleted_course',
        'internal'  => false, // This means that we get events only after transaction commit.
        'priority'  => 1000,
    ),
);
