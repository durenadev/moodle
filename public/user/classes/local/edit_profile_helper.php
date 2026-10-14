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

namespace core_user\local;

use context_system;
use core_user;

/**
 * Shared logic for the edit profile web services (get_edit_profile_settings and update_profile).
 *
 * @package    core_user
 * @copyright  2026 Daniel Ureña <daniel.urena@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class edit_profile_helper {
    /**
     * Standard user fields (besides the name fields) that can be edited with the profile form.
     */
    const STANDARD_FIELDS = [
        'email', 'maildisplay', 'city', 'country', 'timezone', 'idnumber', 'institution', 'department',
        'phone1', 'phone2', 'address',
    ];

    /**
     * Returns the user record of the current user, making sure it can be edited.
     *
     * @return \stdClass the user record
     * @throws \moodle_exception if the profile cannot be edited
     */
    public static function get_editable_user(): \stdClass {
        global $USER;

        if (isguestuser() || !isloggedin()) {
            throw new \moodle_exception('guestnoeditprofile');
        }
        $user = core_user::get_user($USER->id, '*', MUST_EXIST);
        if (is_mnet_remote_user($user)) {
            throw new \moodle_exception('noprofileedit', 'auth');
        }
        $authplugin = \core\di::get(\core\authentication::class)->get_plugin($user->auth);
        if (!$authplugin->can_edit_profile() || $authplugin->edit_profile_url()) {
            throw new \moodle_exception('noprofileedit', 'auth');
        }
        if (!has_capability('moodle/user:editownprofile', context_system::instance())) {
            throw new \moodle_exception('cannotedityourprofile');
        }
        return $user;
    }

    /**
     * Whether a field is locked for the user by the auth plugin configuration.
     *
     * Follows the logic of user_edit_form::definition_after_data().
     *
     * @param \stdClass $user the user
     * @param string $field the field name (profile_field_xxx for custom fields)
     * @param mixed $value the current value of the field
     * @return bool
     */
    public static function is_locked_by_auth(\stdClass $user, string $field, $value): bool {
        $authplugin = \core\di::get(\core\authentication::class)->get_plugin($user->auth);
        $configvariable = 'field_lock_' . $field;
        if (!isset($authplugin->config->{$configvariable})) {
            return false;
        }
        if ($authplugin->config->{$configvariable} === 'locked') {
            return true;
        }
        return $authplugin->config->{$configvariable} === 'unlockedifempty' && $value != '';
    }

    /**
     * Whether a custom profile field is locked for the user.
     *
     * Follows the logic of profile_field_base::edit_field_set_locked() and user_edit_form::definition_after_data().
     *
     * @param \stdClass $user the user
     * @param \profile_field_base $fieldobject the field
     * @return bool
     */
    public static function is_custom_field_locked(\stdClass $user, \profile_field_base $fieldobject): bool {
        if ($fieldobject->is_locked() && !has_capability('moodle/user:update', context_system::instance())) {
            return true;
        }
        return self::is_locked_by_auth($user, $fieldobject->inputname, $fieldobject->data);
    }

    /**
     * Get the standard fields that can be edited, with their type, required and locked status.
     *
     * @param \stdClass $user the user being edited
     * @return array indexed by field name, each item with keys type, required and locked
     */
    public static function get_standard_fields(\stdClass $user): array {
        global $CFG;

        require_once($CFG->dirroot . '/user/editlib.php');

        $names = array_merge(
            array_fill_keys(useredit_get_required_name_fields(), true),
            array_fill_keys(useredit_get_enabled_name_fields(), false),
            array_fill_keys(self::STANDARD_FIELDS, false),
        );
        $names['email'] = true;
        // Names shown in the "additional names" section are editable too.
        foreach (useredit_get_disabled_name_fields() as $name) {
            $names[$name] = false;
        }

        $pendingemail = !empty($CFG->emailchangeconfirmation) && !empty(get_user_preferences('newemail', null, $user->id));

        $fields = [];
        foreach ($names as $name => $required) {
            $locked = self::is_locked_by_auth($user, $name, $user->{$name} ?? '');
            if ($name === 'email' && $pendingemail) {
                // The web form does not show the email field when a change is pending.
                $locked = true;
            }
            if ($name === 'timezone' && isset($CFG->forcetimezone) && $CFG->forcetimezone != 99) {
                $locked = true;
            }
            $fields[$name] = [
                'type' => (string) core_user::get_property_type($name),
                'required' => $required,
                'locked' => $locked,
            ];
        }
        return $fields;
    }
}
