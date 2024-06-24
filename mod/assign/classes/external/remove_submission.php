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

namespace mod_assign\external;

use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use core_external\external_warnings;

/**
 * External function to remove an assignment submission.
 *
 * @package     mod_assign
 * @category    external
 * @copyright   2024 Daniel Ureña <durenadev@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since       Moodle 4.5
 */
class remove_submission extends external_api {

    /**
     * Describes the parameters for remove submission.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters ([
                'userid' => new external_value(PARAM_INT, 'User id'),
                'assignid' => new external_value(PARAM_INT, 'Assignment instance id'),
            ]
        );
    }

    /**
     * Call to remove submission.
     *
     * @param int $userid User ID.
     * @param int $assignid Assignment ID.
     * @return array
     */
    public static function execute(int $userid, int $assignid): array {
        $result = $warnings = $errors = [];

        [
            'assignid' => $assignid,
            'userids'  => $userids
        ] = self::validate_parameters(self::execute_parameters(), [
            'assignid' => $assignid,
            'userids'  => $userids,
        ]);

        // Validate and get the assign.
        list($assign, $course, $cm, $context) = self::validate_assign($assignid);

        foreach ($userids as $userid) {
            if (!$assign->get_user_submission($userid, false)) {
                $errors[] = "Userid {$userid} error: No submission to remove";
            } else {
                $assign->remove_submission($userid);
            }
        }

        $errors = !empty($assign->get_error_messages()) ? array_merge($errors, $assign->get_error_messages()) : $errors;

        foreach ($errors as $errormsg) {
            $warnings[] = self::generate_warning(
                $assignid,
                'couldnotremovesubmission',
                $errormsg
            );
        }

        $result['status']   = empty($errors);
        $result['warnings'] = $warnings;
        return $result;
    }



        global $DB, $USER;

        $result = $warnings = [];
        $submission = null;

        [
            'assignid' => $assignid,
        ] = self::validate_parameters(self::execute_parameters(), [
            'assignid' => $assignid,
        ]);

        list($assignment, $course, $cm, $context) = self::validate_assign($assignid);

        $assignment->update_effective_access($USER->id);
        $latestsubmission = external_api::get_user_or_group_submission($assignment, $USER->id);
        if (!$assignment->submissions_open($USER->id)) {
            $warnings[] = self::generate_warning($assignid,
                'submissionnotopen',
                get_string('submissionnotopen', 'assign'));
        }

        if (!$assignment->is_time_limit_enabled()) {
            $warnings[] = self::generate_warning($assignid,
                'timelimitnotenabled',
                get_string('timelimitnotenabled', 'assign'));
        } else if ($assignment->is_attempt_in_progress()) {
            $warnings[] = self::generate_warning($assignid,
                'opensubmissionexists',
                get_string('opensubmissionexists', 'assign'));
        }

        if (empty($warnings)) {
            // If there is an open submission with no start time, use latest submission, otherwise create a new submission.
            if (!empty($latestsubmission)
                    && $latestsubmission->status !== ASSIGN_SUBMISSION_STATUS_SUBMITTED
                    && empty($latestsubmission->timestarted)) {
                $submission = $latestsubmission;
            } else {
                $submission = external_api::get_user_or_group_submission($assignment, $USER->id, 0, true);
            }

            // Set the start time of the submission.
            $submission->timestarted = time();
            $DB->update_record('assign_submission', $submission);
        }

        $result['submissionid'] = $submission ? $submission->id : 0;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Describes the remove submissions return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'status' => new external_value(PARAM_BOOL, 'True if the submission was successfully removed and false if was not.'),
            'warnings' => new external_warnings(),
        ]);
    }


}
