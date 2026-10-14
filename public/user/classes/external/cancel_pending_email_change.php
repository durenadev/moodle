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
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use core_external\external_warnings;
use core_user\local\edit_profile_helper;

/**
 * Cancel the current user's pending email change.
 *
 * @package    core_user
 * @copyright  2026 Daniel Ureña <daniel.urena@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 6.0
 */
class cancel_pending_email_change extends external_api {
    /**
     * Describe the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    /**
     * Cancel pending confirmation without changing the confirmed email.
     *
     * @return array updated email status and warnings
     */
    public static function execute(): array {
        global $CFG, $USER;

        require_once($CFG->dirroot . '/user/editlib.php');
        self::validate_parameters(self::execute_parameters(), []);
        self::validate_context(context_user::instance($USER->id));
        $user = edit_profile_helper::get_editable_user();

        cancel_email_update($user->id);

        return [
            'status' => true,
            'email' => $user->email,
            'emailchangepending' => false,
            'warnings' => [],
        ];
    }

    /**
     * Describe the result.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'status' => new external_value(PARAM_BOOL, 'Whether cancellation succeeded'),
            'email' => new external_value(PARAM_RAW, 'The current confirmed email address'),
            'emailchangepending' => new external_value(PARAM_BOOL, 'Whether an email change is pending'),
            'warnings' => new external_warnings(),
        ]);
    }
}
