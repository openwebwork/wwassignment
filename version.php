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
 * Version information for the wwassignment type.
 *
 * @package   wwassignment
 * @copyright 2006--2015 Michael E. Gage
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// 3.0+
defined('MOODLE_INTERNAL') || die();
$plugin->version   = 2015030918;  // The current module version (Date: YYYYMMDDXX)
$plugin->requires  = 2014110400;        // Requires this Moodle version
$plugin->cron      = 300;         // Period for cron to check this module (secs) -- every 5 minutes
$plugin->component = 'mod_wwassignment';
$plugin->maturity  = MATURITY_STABLE;

