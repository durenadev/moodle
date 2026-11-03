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

namespace tool_mobile\local;

/**
 * Event observers for tool_mobile.
 *
 * @package    tool_mobile
 * @copyright  2026 Daniel Ureña <daniel.urena@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {
    /**
     * Review setting changes that may start or cancel a current setup detection.
     *
     * @param \core\event\config_log_created $event
     */
    public static function config_log_created(\core\event\config_log_created $event): void {
        if (during_initial_install()) {
            return;
        }

        $oldvalue = $event->other['oldvalue'];
        $value = $event->other['value'];
        current_setup_detection::setting_changed(
            $event->other['plugin'],
            $event->other['name'],
            $oldvalue === null ? null : (string) $oldvalue,
            $value === null ? null : (string) $value,
        );
    }
}
