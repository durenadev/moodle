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

namespace tool_mobile\local\admin;

use core_admin\setting\setting\configtext;

/**
 * Text setting for Matomo analytics that is reset when Matomo analytics is disabled.
 *
 * The tool_mobile/matomoanalytic checkbox must be added to the settings page before any setting
 * using this class, so its new value is already stored when this setting is written.
 *
 * @package    tool_mobile
 * @copyright  2026 Daniel Ureña <daniel.urena@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class matomo_configtext extends configtext {
    #[\Override]
    public function get_setting() {
        $setting = parent::get_setting();
        // Hide values stored while Matomo analytics is disabled (e.g. via CLI). Keep null so new settings are detected.
        if ($setting !== null && !get_config('tool_mobile', 'matomoanalytic')) {
            return '';
        }
        return $setting;
    }

    #[\Override]
    public function write_setting($data) {
        if (!get_config('tool_mobile', 'matomoanalytic')) {
            $data = '';
        }
        return parent::write_setting($data);
    }
}
