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

namespace core_user\external;

use context_system;
use context_user;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use core_external\external_warnings;
use core_user;
use core_user\local\edit_profile_helper;
use invalid_parameter_exception;

/**
 * Update the profile (standard and custom fields) of the current user.
 *
 * @package    core_user
 * @copyright  2026 Daniel Ureña <daniel.urena@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 6.0
 */
class update_profile extends external_api {
    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'fields' => new external_multiple_structure(
                new external_single_structure([
                    'name' => new external_value(PARAM_ALPHANUMEXT, 'The name of the standard field (e.g. firstname, email)'),
                    'value' => new external_value(PARAM_RAW, 'The new value'),
                ]),
                'Standard profile fields to update',
                VALUE_DEFAULT,
                [],
            ),
            'customfields' => new external_multiple_structure(
                new external_single_structure([
                    'name' => new external_value(PARAM_ALPHANUMEXT, 'The input name of the custom field (profile_field_shortname)'),
                    'value' => new external_value(PARAM_RAW, 'Custom field value, can be an encoded json if required'),
                ]),
                'Custom profile fields to update',
                VALUE_DEFAULT,
                [],
            ),
        ]);
    }

    /**
     * Update the profile of the current user.
     *
     * @param array $fields standard fields (name, value)
     * @param array $customfields custom fields (name, value)
     * @return array status and warnings
     */
    public static function execute(array $fields = [], array $customfields = []): array {
        global $CFG, $DB, $USER, $PAGE, $OUTPUT, $SITE;

        require_once($CFG->dirroot . '/user/editlib.php');
        require_once($CFG->dirroot . '/user/profile/lib.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'fields' => $fields,
            'customfields' => $customfields,
        ]);

        $context = context_user::instance($USER->id);
        self::validate_context($context);
        $PAGE->set_context($context);

        $user = edit_profile_helper::get_editable_user();
        $standardfields = edit_profile_helper::get_standard_fields($user);
        $customobjects = [];
        foreach (profile_get_user_fields_with_data($user->id) as $fieldobject) {
            $customobjects[$fieldobject->inputname] = $fieldobject;
        }

        $warnings = [];
        $addwarning = function (string $item, string $code, string $message) use (&$warnings) {
            $warnings[] = ['item' => $item, 'warningcode' => $code, 'message' => $message];
        };

        $usernew = new \stdClass();
        $usernew->id = $user->id;

        foreach ($params['fields'] as $field) {
            $name = $field['name'];
            if (!isset($standardfields[$name])) {
                $addwarning($name, 'fieldnotallowed', get_string('profilefieldnotallowed', 'core_user'));
                continue;
            }
            if ($standardfields[$name]['locked']) {
                $addwarning($name, 'fieldlocked', get_string('profilefieldlocked', 'core_user'));
                continue;
            }
            try {
                $value = validate_param($field['value'], $standardfields[$name]['type'], NULL_NOT_ALLOWED);
            } catch (invalid_parameter_exception $e) {
                $addwarning($name, 'invalidvalue', $e->getMessage());
                continue;
            }
            if (is_string($value)) {
                $value = trim($value);
            }
            // The web form uses selects for these fields, so only accept the values it offers.
            if ($name === 'country' && $value !== '' && !array_key_exists($value, get_string_manager()->get_list_of_countries())) {
                $addwarning($name, 'invalidvalue', get_string('invaliddata', 'error'));
                continue;
            }
            if ($name === 'timezone' && !array_key_exists($value, \core_date::get_list_of_timezones(null, true))) {
                $addwarning($name, 'invalidvalue', get_string('invaliddata', 'error'));
                continue;
            }
            if ($standardfields[$name]['required'] && $value === '') {
                $addwarning($name, 'required', get_string('required'));
                continue;
            }
            $usernew->{$name} = $value;
        }

        // Validate the email, same rules as user_edit_form::validation().
        $emailchanged = false;
        if (isset($usernew->email) && $usernew->email !== $user->email) {
            if (!validate_email($usernew->email)) {
                $addwarning('email', 'invalidemail', get_string('invalidemail'));
            } else {
                $emailchanged = true;
                if (empty($CFG->allowaccountssameemail)) {
                    $select = $DB->sql_equal('email', ':email', false) . ' AND mnethostid = :mnethostid AND id <> :userid';
                    $emailparams = [
                        'email' => $usernew->email,
                        'mnethostid' => $CFG->mnet_localhost_id,
                        'userid' => $user->id,
                    ];
                    if ($DB->record_exists_select('user', $select, $emailparams)) {
                        $addwarning('email', 'emailexists', get_string('emailexists'));
                    }
                }
                if (!empty($CFG->verifychangedemail) && !has_capability('moodle/user:update', context_system::instance())) {
                    $errorstr = email_is_not_allowed($usernew->email);
                    if ($errorstr !== false) {
                        $addwarning('email', 'emailnotallowed', $errorstr);
                    }
                }
            }
        }

        // Custom profile fields.
        foreach ($params['customfields'] as $customfield) {
            $name = $customfield['name'];
            if (!isset($customobjects[$name]) || !$customobjects[$name]->is_editable()) {
                $addwarning($name, 'fieldnotallowed', get_string('profilefieldnotallowed', 'core_user'));
                continue;
            }
            $fieldobject = $customobjects[$name];
            if (edit_profile_helper::is_custom_field_locked($user, $fieldobject)) {
                $addwarning($name, 'fieldlocked', get_string('profilefieldlocked', 'core_user'));
                continue;
            }
            [$type, $allownull] = $fieldobject->get_field_properties();
            try {
                validate_param($customfield['value'], $type, $allownull);
            } catch (invalid_parameter_exception $e) {
                $addwarning($name, 'invalidvalue', $e->getMessage());
                continue;
            }
            // Some profile fields (like textarea) use an array, which is sent json encoded.
            $datadecoded = json_decode($customfield['value'], true);
            if (is_array($datadecoded) && json_last_error() == JSON_ERROR_NONE) {
                $usernew->{$name} = $datadecoded;
            } else {
                $usernew->{$name} = $customfield['value'];
            }
            $submitted = is_array($usernew->{$name}) ? ($usernew->{$name}['text'] ?? '') : $usernew->{$name};
            if ($fieldobject->is_required() && trim((string) $submitted) === '' && $submitted !== '0') {
                $addwarning($name, 'required', get_string('required'));
                continue;
            }
            foreach ($fieldobject->edit_validate_field($usernew) as $errorfield => $message) {
                $addwarning($errorfield, 'invalidvalue', $message);
            }
        }

        if (!empty($warnings)) {
            $addwarning('profile', 'profilenotupdated', get_string('profilenotupdated', 'core_user'));
            return ['status' => false, 'warnings' => $warnings];
        }

        // Same flow as user/edit.php: users without moodle/user:update need to confirm the new email address.
        $emailkey = null;
        $newemail = null;
        $needsconfirmation = $emailchanged
            && !empty($CFG->emailchangeconfirmation)
            && !has_capability('moodle/user:update', context_system::instance());
        if ($needsconfirmation) {
            $validuntil = time() + 600;
            $emailkey = create_user_key('core_user/email_change', $user->id, null, null, $validuntil);
            set_user_preference('newemail', $usernew->email, $user->id);
            set_user_preference('newemailattemptsleft', 3, $user->id);

            $newemail = $usernew->email;
            $usernew->email = $user->email;
            $emailchanged = false;
        }

        $usernew->timemodified = time();

        $authplugin = \core\di::get(\core\authentication::class)->get_plugin($user->auth);
        // Pass a true old $user here.
        if (!$authplugin->user_update($user, $usernew)) {
            throw new \moodle_exception('cannotupdateprofile');
        }

        \core\user::update_user($usernew, false, false);
        if ($emailchanged) {
            useredit_update_bounces($user, $usernew);
        }
        profile_save_data($usernew);

        \core\event\user_updated::create_from_userid($user->id)->trigger();

        if ($newemail !== null) {
            $tempuser = $DB->get_record('user', ['id' => $user->id], '*', MUST_EXIST);
            $tempuser->email = $newemail;

            $a = new \stdClass();
            $a->url = $CFG->wwwroot . '/user/emailupdate.php?key=' . $emailkey . '&id=' . $user->id;
            $a->site = format_string($SITE->fullname, true, ['context' => \context_course::instance(SITEID)]);
            foreach (core_user::get_name_placeholders($user) as $field => $value) {
                $a->{$field} = $value;
            }
            $a->supportemail = $OUTPUT->supportemail();

            // Email confirmation directly rather than using messaging so they will definitely get an email.
            $emailsent = email_to_user(
                $tempuser,
                core_user::get_noreply_user(),
                get_string('emailupdatetitle', 'auth', $a),
                get_string('emailupdatemessage', 'auth', $a),
            );
            if ($emailsent) {
                $a = new \stdClass();
                $a->oldemail = $user->email;
                $a->newemail = $newemail;
                $addwarning('email', 'emailchangepending', get_string('auth_changingemailaddress', 'auth', $a));
            } else {
                // The rest of the profile is already saved, so report the problem instead of failing.
                $addwarning('email', 'emailnotsent', get_string('profileemailnotsent', 'core_user'));
            }
        }

        // Refresh the session user, as user/edit.php does.
        $updated = $DB->get_record('user', ['id' => $user->id], '*', MUST_EXIST);
        foreach ((array) $updated as $variable => $value) {
            if ($variable === 'description' || $variable === 'password') {
                continue;
            }
            $USER->{$variable} = $value;
        }
        profile_load_custom_fields($USER);

        return ['status' => true, 'warnings' => $warnings];
    }

    /**
     * Returns description of method result value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'status' => new external_value(PARAM_BOOL, 'Whether the profile was updated'),
            'warnings' => new external_warnings(),
        ]);
    }
}
