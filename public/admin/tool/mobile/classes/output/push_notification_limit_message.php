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

/**
 * HTML message helper for push notification limit alerts.
 *
 * @package    tool_mobile
 * @copyright  2026 Daniel Ureña <daniel.urena@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_mobile\output;

/**
 * HTML message helper for push notification limit alerts.
 *
 * @copyright  2026 Daniel Ureña <daniel.urena@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class push_notification_limit_message {
    /**
     * Render the push notification limit HTML message.
     *
     * @param \stdClass $data Message placeholders.
     * @return string
     */
    public static function render(\stdClass $data): string {
        global $OUTPUT;

        return $OUTPUT->render_from_template('tool_mobile/push_notification_limit_message', self::export_context($data));
    }

    /**
     * Export the template context for the push notification limit HTML message.
     *
     * @param \stdClass $data Message placeholders.
     * @return array<string, mixed>
     */
    public static function export_context(\stdClass $data): array {
        $currentdevices = (int) $data->currentactivedevices;
        $ignorednotifications = (int) $data->ignorednotifications;
        $devicelimit = $data->devicelimit !== null ? (int) $data->devicelimit : null;
        $ratiolabel = $devicelimit !== null ? $currentdevices . ' / ' . $devicelimit : (string) $currentdevices;
        $progresswidth = 100;
        if ($devicelimit !== null && $devicelimit > 0) {
            $progresswidth = min(100, (int) ceil(($currentdevices / $devicelimit) * 100));
        }

        $subheading = get_string('checkpushnotificationlimitshtmlmissedzero', 'tool_mobile');
        if ($ignorednotifications === 1) {
            $subheading = get_string('checkpushnotificationlimitshtmlmissedsingle', 'tool_mobile');
        } else if ($ignorednotifications > 1) {
            $subheading = get_string('checkpushnotificationlimitshtmlmissedmultiple', 'tool_mobile', $ignorednotifications);
        }

        return [
            'heading' => get_string('checkpushnotificationlimitshtmlheading', 'tool_mobile'),
            'subheading' => $subheading,
            'metriclabel' => get_string('checkpushnotificationlimitshtmlmetriclabel', 'tool_mobile'),
            'limitlabel' => get_string('checkpushnotificationlimitshtmllimitlabel', 'tool_mobile'),
            'footer' => get_string('checkpushnotificationlimitshtmlfooter', 'tool_mobile'),
            'buttonlabel' => get_string('checkpushnotificationlimitshtmlbutton', 'tool_mobile'),
            'buttonurl' => $data->url,
            'ratiolabel' => $ratiolabel,
            'progresswidth' => $progresswidth,
            'illustrationurl' => (new \moodle_url('/admin/tool/mobile/pix/push_notification.svg'))->out(false),
            'alerticonurl' => (new \moodle_url('/pix/i/risk_xss.svg'))->out(false),
        ];
    }
}
