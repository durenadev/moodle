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

use context_user;
use core_external\external_api;
use core_external\external_format_value;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use core_external\external_warnings;
use core_external\util;
use core_user\local\edit_profile_helper;

/**
 * Get the settings and field definitions needed to build the "edit profile" form for the current user.
 *
 * @package    core_user
 * @copyright  2026 Daniel Ureña <daniel.urena@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 6.0
 */
class get_edit_profile_settings extends external_api {
    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    /**
     * Get the edit profile settings of the current user.
     *
     * @return array settings and warnings
     */
    public static function execute(): array {
        global $CFG, $PAGE, $USER;

        require_once($CFG->dirroot . '/user/editlib.php');
        require_once($CFG->dirroot . '/user/profile/lib.php');

        self::validate_parameters(self::execute_parameters(), []);

        $context = context_user::instance($USER->id);
        self::validate_context($context);
        // We need this to make work the format text functions.
        $PAGE->set_context($context);

        $user = edit_profile_helper::get_editable_user();

        $result = [];
        $result['namefields'] = useredit_get_required_name_fields();
        $result['additionalnamefields'] = array_values(useredit_get_enabled_name_fields());

        if (!empty($CFG->passwordpolicy)) {
            $result['passwordpolicy'] = print_password_policy();
        }
        if (!empty($CFG->defaultcity)) {
            $result['defaultcity'] = $CFG->defaultcity;
        }
        if (!empty($CFG->country)) {
            $result['country'] = $CFG->country;
        }

        $result['fields'] = [];
        foreach (edit_profile_helper::get_standard_fields($user) as $name => $field) {
            $result['fields'][] = ['name' => $name] + $field;
        }

        $result['profilefields'] = [];
        foreach (profile_get_user_fields_with_data($user->id) as $fieldobject) {
            if (!$fieldobject->is_editable()) {
                continue;
            }
            $fielddata = $fieldobject->get_field_config_for_external();
            $fielddata['categoryname'] = util::format_string($fieldobject->get_category_name(), $context->id);
            $fielddata['name'] = util::format_string($fielddata['name'], $context->id);
            [$fielddata['description'], $fielddata['descriptionformat']] =
                util::format_text($fielddata['description'], $fielddata['descriptionformat'], $context->id);
            [$fielddata['defaultdata'], $fielddata['defaultdataformat']] =
                util::format_text($fielddata['defaultdata'], $fielddata['defaultdataformat'], $context->id);
            $fielddata['locked'] = (int) edit_profile_helper::is_custom_field_locked($user, $fieldobject);
            $fielddata['value'] = (string) $fieldobject->data;
            $fielddata['fieldname'] = $fieldobject->inputname;
            $result['profilefields'][] = $fielddata;
        }

        $newemail = get_user_preferences('newemail', null, $user->id);
        $pending = !empty($CFG->emailchangeconfirmation) && !empty($newemail);
        $result['emailchangepending'] = $pending;
        if ($pending) {
            $result['pendingemail'] = $newemail;
        }
        $result['warnings'] = [];
        return $result;
    }

    /**
     * Returns description of method result value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'namefields' => new external_multiple_structure(
                new external_value(PARAM_NOTAGS, 'The order of the required name fields')
            ),
            'additionalnamefields' => new external_multiple_structure(
                new external_value(PARAM_NOTAGS, 'Additional name fields enabled in the site'),
                'Additional name fields',
            ),
            'passwordpolicy' => new external_value(PARAM_RAW, 'Password policy', VALUE_OPTIONAL),
            'defaultcity' => new external_value(PARAM_NOTAGS, 'Default city', VALUE_OPTIONAL),
            'country' => new external_value(PARAM_ALPHA, 'Default country', VALUE_OPTIONAL),
            'fields' => new external_multiple_structure(
                new external_single_structure([
                    'name' => new external_value(PARAM_ALPHANUMEXT, 'Field name'),
                    'type' => new external_value(PARAM_ALPHANUMEXT, 'Parameter type used to validate the field value'),
                    'required' => new external_value(PARAM_BOOL, 'Whether the field is required'),
                    'locked' => new external_value(PARAM_BOOL, 'Whether the field is locked (not editable)'),
                ]),
                'Standard profile fields',
            ),
            'profilefields' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'Profile field id', VALUE_OPTIONAL),
                    'shortname' => new external_value(PARAM_ALPHANUMEXT, 'Profile field shortname', VALUE_OPTIONAL),
                    'fieldname' => new external_value(PARAM_ALPHANUMEXT, 'Name of the input used to send the value'),
                    'name' => new external_value(PARAM_RAW, 'Profile field name', VALUE_OPTIONAL),
                    'datatype' => new external_value(PARAM_ALPHANUMEXT, 'Profile field datatype', VALUE_OPTIONAL),
                    'description' => new external_value(PARAM_RAW, 'Profile field description', VALUE_OPTIONAL),
                    'descriptionformat' => new external_format_value('description'),
                    'categoryid' => new external_value(PARAM_INT, 'Profile field category id', VALUE_OPTIONAL),
                    'categoryname' => new external_value(PARAM_RAW, 'Profile field category name', VALUE_OPTIONAL),
                    'sortorder' => new external_value(PARAM_INT, 'Profile field sort order', VALUE_OPTIONAL),
                    'required' => new external_value(PARAM_INT, 'Profile field required', VALUE_OPTIONAL),
                    'locked' => new external_value(PARAM_INT, 'Profile field locked for the current user', VALUE_OPTIONAL),
                    'visible' => new external_value(PARAM_INT, 'Profile field visible', VALUE_OPTIONAL),
                    'forceunique' => new external_value(PARAM_INT, 'Profile field unique', VALUE_OPTIONAL),
                    'signup' => new external_value(PARAM_INT, 'Profile field in signup form', VALUE_OPTIONAL),
                    'defaultdata' => new external_value(PARAM_RAW, 'Profile field default data', VALUE_OPTIONAL),
                    'defaultdataformat' => new external_format_value('defaultdata'),
                    'param1' => new external_value(PARAM_RAW, 'Profile field settings', VALUE_OPTIONAL),
                    'param2' => new external_value(PARAM_RAW, 'Profile field settings', VALUE_OPTIONAL),
                    'param3' => new external_value(PARAM_RAW, 'Profile field settings', VALUE_OPTIONAL),
                    'param4' => new external_value(PARAM_RAW, 'Profile field settings', VALUE_OPTIONAL),
                    'param5' => new external_value(PARAM_RAW, 'Profile field settings', VALUE_OPTIONAL),
                    'value' => new external_value(PARAM_RAW, 'Current value of the field for the user'),
                ]),
                'Editable custom profile fields',
            ),
            'emailchangepending' => new external_value(PARAM_BOOL, 'Whether an email change is waiting for confirmation'),
            'pendingemail' => new external_value(PARAM_RAW, 'The new email address pending confirmation', VALUE_OPTIONAL),
            'warnings' => new external_warnings(),
        ]);
    }
}
