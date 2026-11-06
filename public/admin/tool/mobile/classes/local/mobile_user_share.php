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

use core\clock;
use core\di;

/**
 * Share of the site active users that use the Moodle app.
 *
 * The usage is a snapshot of the current month: a user is active if they accessed the site or the Moodle app since the
 * beginning of the month, and a user uses the Moodle app if their token for the official mobile service was used since then.
 *
 * @package    tool_mobile
 * @copyright  2026 Daniel Ureña <daniel.urena@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mobile_user_share {
    /** @var int Percentage of active users that must be exceeded. */
    public const THRESHOLD = 50;

    /** @var int Minimum number of active Moodle app users. */
    public const MIN_APP_USERS = 50;

    /** @var int Minimum time between two notifications. */
    public const NOTIFICATION_MIN_INTERVAL = YEARSECS;

    /**
     * Get the usage of the Moodle app by the site active users in the current month.
     *
     * @return array{activeusers: int, appusers: int, percentage: int}
     */
    public static function get_usage(): array {
        global $CFG, $DB;

        $since = di::get(clock::class)->now()->modify('midnight first day of this month')->getTimestamp();
        $apptokensql = "SELECT 1
                          FROM {external_tokens} t
                          JOIN {external_services} s ON s.id = t.externalserviceid
                         WHERE t.userid = u.id
                               AND s.shortname = :service
                               AND t.lastaccess >= :tokensince";
        $userssql = "SELECT COUNT(1)
                       FROM {user} u
                      WHERE u.deleted = 0
                            AND u.suspended = 0
                            AND u.id <> :guestid";
        $params = [
            'service' => MOODLE_OFFICIAL_MOBILE_SERVICE,
            'tokensince' => $since,
            'guestid' => $CFG->siteguest,
        ];

        // The app requests do not always update the user last access, so app users are always considered active.
        $activeusers = $DB->count_records_sql(
            "$userssql AND (u.lastaccess >= :usersince OR EXISTS ($apptokensql))",
            $params + ['usersince' => $since],
        );
        $appusers = $DB->count_records_sql("$userssql AND EXISTS ($apptokensql)", $params);

        return [
            'activeusers' => $activeusers,
            'appusers' => $appusers,
            'percentage' => $activeusers > 0 ? (int) floor($appusers * 100 / $activeusers) : 0,
        ];
    }

    /**
     * Whether the Moodle app usage exceeds the threshold to notify admins.
     *
     * @param array{activeusers: int, appusers: int, percentage: int} $usage Usage returned by {@see self::get_usage()}.
     * @return bool
     */
    public static function exceeds_threshold(array $usage): bool {
        return $usage['appusers'] >= self::MIN_APP_USERS && $usage['appusers'] * 100 > $usage['activeusers'] * self::THRESHOLD;
    }

    /**
     * Whether admins were notified about the Moodle app usage within the minimum time between two notifications.
     *
     * @return bool
     */
    public static function notified_recently(): bool {
        $lastnotified = (int) get_config('tool_mobile', 'mobileusersharelastnotified');

        return $lastnotified > 0 && di::get(clock::class)->time() < $lastnotified + self::NOTIFICATION_MIN_INTERVAL;
    }
}
