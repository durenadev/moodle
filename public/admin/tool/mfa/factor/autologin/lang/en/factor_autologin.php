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
 * Language strings.
 *
 * @package     factor_autologin
 * @copyright   2026 Dani <daniel.urena@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['info'] = 'Automatically verify users who logged in via the mobile app auto-login mechanism.';
$string['pluginname'] = 'Mobile app auto-login';
$string['privacy:metadata'] = 'The Mobile app auto-login factor plugin does not store any personal data.';
$string['settings:description'] = 'Automatically pass MFA for sessions established through the mobile app auto-login endpoint. Users already authenticated in the Moodle mobile app will not be challenged for a second factor when auto-login is used to open embedded content or site links.';
$string['settings:shortdescription'] = 'Allow mobile app auto-login sessions to bypass additional authentication steps.';
$string['summarycondition'] = 'session was established via mobile app auto-login';
