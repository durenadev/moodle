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
 * Settings for the mobile autologin MFA factor.
 *
 * @package     factor_mobile_autologin
 * @copyright   2026 Daniel Urena
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_heading('factor_mobile_autologin/description', '',
        new lang_string('settings:description', 'factor_mobile_autologin')));
    $settings->add(new admin_setting_heading('factor_mobile_autologin/settings',
        new lang_string('settings', 'moodle'), ''));

    // Enabled by default (1) since mobile autologin already implies completed MFA in the app.
    $enabled = new admin_setting_configcheckbox('factor_mobile_autologin/enabled',
        new lang_string('settings:enablefactor', 'tool_mfa'),
        new lang_string('settings:enablefactor_help', 'tool_mfa'), 1);
    $enabled->set_updatedcallback(function() {
        \tool_mfa\manager::do_factor_action(
            'mobile_autologin',
            get_config('factor_mobile_autologin', 'enabled') ? 'enable' : 'disable'
        );
    });
    $settings->add($enabled);

    $settings->add(new admin_setting_configtext('factor_mobile_autologin/weight',
        new lang_string('settings:weight', 'tool_mfa'),
        new lang_string('settings:weight_help', 'tool_mfa'), 100, PARAM_INT));
}
