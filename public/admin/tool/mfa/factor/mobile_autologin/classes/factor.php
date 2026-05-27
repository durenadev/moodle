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

namespace factor_mobile_autologin;

use stdClass;
use tool_mfa\local\factor\object_factor_base;

/**
 * Mobile app autologin MFA factor.
 *
 * This factor automatically passes MFA when a user authenticates
 * via the mobile app autologin mechanism (admin/tool/mobile/autologin.php).
 * Users authenticated through the mobile app have already completed MFA
 * within the app itself, so prompting again provides no security benefit.
 *
 * @package     factor_mobile_autologin
 * @copyright   2026 Daniel Urena
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class factor extends object_factor_base {

    /**
     * Mobile autologin factor implementation.
     * Factor is a singleton, only one instance per user.
     *
     * {@inheritDoc}
     */
    public function get_all_user_factors(stdClass $user): array {
        global $DB;

        $records = $DB->get_records('tool_mfa', ['userid' => $user->id, 'factor' => $this->name]);

        if (!empty($records)) {
            return $records;
        }

        // No record exists yet, create one on demand.
        $record = [
            'userid'        => $user->id,
            'factor'        => $this->name,
            'timecreated'   => time(),
            'createdfromip' => $user->lastip,
            'timemodified'  => time(),
            'revoked'       => 0,
        ];
        $record['id'] = $DB->insert_record('tool_mfa', $record, true);
        return [(object) $record];
    }

    /**
     * Mobile autologin factor does not require any user input.
     *
     * {@inheritDoc}
     */
    public function has_input(): bool {
        return false;
    }

    /**
     * Mobile autologin factor does not require user setup.
     *
     * {@inheritDoc}
     */
    public function has_setup(): bool {
        return false;
    }

    /**
     * Returns STATE_PASS when the current authenticated session was recently
     * validated by the mobile autologin endpoint.
     *
     * {@inheritDoc}
     */
    public function get_state(): string {
        global $USER;

        if (!empty($USER->id) && \factor_mobile_autologin\local\autologin_validator::is_validated(
                (int)$USER->id,
                session_id()
            )) {
            return \tool_mfa\plugininfo\factor::STATE_PASS;
        }

        return \tool_mfa\plugininfo\factor::STATE_NEUTRAL;
    }

    /**
     * Returns possible states this factor can return.
     *
     * @param stdClass $user
     * @return array
     */
    public function possible_states(stdClass $user): array {
        return [
            \tool_mfa\plugininfo\factor::STATE_PASS,
            \tool_mfa\plugininfo\factor::STATE_NEUTRAL,
        ];
    }
}
