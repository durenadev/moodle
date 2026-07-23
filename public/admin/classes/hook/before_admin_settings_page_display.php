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

namespace core_admin\hook;

/**
 * Hook to allow subscribers to add contextual HTML content before an admin settings page is displayed.
 *
 * @package    core_admin
 * @copyright  2026 Daniel Urena <daniel.urena@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\core\attribute\tags('output', 'admin')]
#[\core\attribute\label('Allows plugins to add contextual HTML content before an admin settings page is displayed.')]
final class before_admin_settings_page_display {
    /**
     * Hook to allow subscribers to add HTML content before an admin settings page is displayed.
     *
     * @param string $section The admin settings section identifier (e.g. 'logos', 'additionalhtml').
     * @param string $output Initial output.
     */
    public function __construct(
        /** @var string The admin settings section identifier. */
        public readonly string $section,
        /** @var string The collected output. */
        private string $output = '',
    ) {
    }

    /**
     * Plugins implementing a callback for this hook can add any HTML content before the settings page.
     *
     * Listeners should check {@see self::$section} first and return early for sections they do not
     * care about, to avoid unnecessary work on pages that are not relevant to them.
     *
     * @param null|string $output
     */
    public function add_html(?string $output): void {
        if ($output) {
            $this->output .= $output;
        }
    }

    /**
     * Returns all HTML added by the plugins.
     *
     * @return string
     */
    public function get_output(): string {
        return $this->output;
    }
}
