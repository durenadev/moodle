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
 * Language strings for factor_mobile_autologin.
 *
 * @package     factor_mobile_autologin
 * @copyright   2026 Daniel Urena
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['info'] = 'This factor passes automatically when the user has authenticated via the Moodle mobile app autologin mechanism.';
$string['cachedef_validatedsessions'] = 'Short-lived validation records for mobile autologin sessions.';
$string['pluginname'] = 'Mobile app autologin';
$string['privacy:metadata'] = 'The Mobile app autologin factor does not store any personal data.';
$string['settings:description'] = 'Automatically bypass MFA for users who authenticated via the Moodle mobile app. '
    . 'These users have already completed authentication (including MFA if enabled) within the app itself.';
$string['settings:shortdescription'] = 'Bypass MFA for users arriving via the mobile app autologin.';
$string['summarycondition'] = 'authenticated via mobile app autologin';
