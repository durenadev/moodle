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

namespace core_external;

use core\event\base;

/**
 * Event observers for web services.
 *
 * @package    core_external
 * @copyright  2026 Daniel Ureña <daniel.urena@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {
    /**
     * @var bool[] Accesses already logged in this request, indexed by "userid:courseid".
     */
    private static array $loggedaccess = [];

    /**
     * Update the user access times when the user performs an action through a web service.
     *
     * Access times are not updated by require_login() during web service requests, because many of them are
     * background calls (prefetch, synchronisation...). Actions done by the user (viewing a page, submitting...)
     * trigger events, so they are used to keep the site and course access times up to date.
     *
     * @param base $event The event.
     */
    public static function update_access_time(base $event): void {
        if (!WS_SERVER) {
            return;
        }
        self::log_access_time($event);
    }

    /**
     * Update the site and course access times of the current user for the given event.
     *
     * Only public for testing purposes (WS_SERVER is always false in PHPUnit), use {@see self::update_access_time()} instead.
     *
     * @param base $event The event.
     */
    public static function log_access_time(base $event): void {
        global $USER;

        // Triggered by every web service request, including background ones (prefetch, sync...).
        if ($event instanceof \core\event\webservice_function_called) {
            return;
        }

        // Only actions performed by the current user count as an access.
        if (empty($event->userid) || $event->userid != $USER->id) {
            return;
        }

        // Courseid 0 is logged as a site access.
        $courseid = empty($event->courseid) ? SITEID : (int) $event->courseid;

        // A request can trigger several events for the same course, log the access only once per request,
        // because user_accesstime_log() queries the database when the course access was recently updated.
        $key = $USER->id . ':' . $courseid;
        if (isset(self::$loggedaccess[$key])) {
            return;
        }

        user_accesstime_log($courseid);
        self::$loggedaccess[$key] = true;
    }

    /**
     * Reset the access times logged in this request.
     *
     * Only for testing purposes, the cache lives during a single web service request.
     */
    public static function reset_caches(): void {
        self::$loggedaccess = [];
    }
}
