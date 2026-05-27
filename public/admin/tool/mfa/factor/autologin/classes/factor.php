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

namespace factor_autologin;

use stdClass;
use tool_mfa\local\factor\object_factor_base;

/**
 * Mobile app auto-login factor class.
 *
 * @package     factor_autologin
 * @copyright   2026 Dani <daniel.urena@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class factor extends object_factor_base {

    /**
     * Auto-login Factor implementation.
     * Factor is a singleton, can only be one instance.
     *
     * @param stdClass $user the user to check against.
     * @return array
     */
    public function get_all_user_factors(stdClass $user): array {
        global $DB;
        $records = $DB->get_records('tool_mfa', ['userid' => $user->id, 'factor' => $this->name]);

        if (!empty($records)) {
            return $records;
        }

        // Null records returned, build new record.
        $record = [
            'userid' => $user->id,
            'factor' => $this->name,
            'timecreated' => time(),
            'createdfromip' => $user->lastip,
            'timemodified' => time(),
            'revoked' => 0,
        ];
        $record['id'] = $DB->insert_record('tool_mfa', $record, true);
        return [(object) $record];
    }

    /**
     * Auto-login Factor implementation.
     * Factor does not have input.
     *
     * {@inheritDoc}
     */
    public function has_input(): bool {
        return false;
    }

    /**
     * Auto-login Factor implementation.
     * State check is performed here, as there is no form to do it in.
     *
     * {@inheritDoc}
     */
    public function get_state(): string {
        global $SESSION;

        if (!empty($SESSION->tool_mobile_autologin)) {
            return \tool_mfa\plugininfo\factor::STATE_PASS;
        }
        return \tool_mfa\plugininfo\factor::STATE_NEUTRAL;
    }

    /**
     * Auto-login Factor implementation.
     * The state can never be set externally. Always return true.
     *
     * @param string $state the state constant to set
     * @return bool
     */
    public function set_state(string $state): bool {
        return true;
    }

    /**
     * Auto-login Factor implementation.
     *
     * {@inheritDoc}
     */
    public function possible_states(stdClass $user): array {
        return [
            \tool_mfa\plugininfo\factor::STATE_PASS,
            \tool_mfa\plugininfo\factor::STATE_NEUTRAL,
        ];
    }

}
