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

namespace factor_mobile_autologin\local;

use cache;

/**
 * Validates whether a session was created by mobile autologin recently.
 *
 * @package     factor_mobile_autologin
 * @copyright   2026 Daniel Urena
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class autologin_validator {
    /** @var string Cache definition name. */
    private const CACHE_DEFINITION = 'validatedsessions';

    /** @var int Validity window in seconds for mobile autologin signal. */
    private const VALIDITY_TTL = 120;

    /**
     * Store a short-lived validation for this user and session.
     *
     * @param int $userid
     * @param string $sessionid
     * @return void
     */
    public static function mark_validated(int $userid, string $sessionid): void {
        if ($userid <= 0 || $sessionid === '') {
            return;
        }

        self::cache()->set(self::cache_key($userid, $sessionid), time());
    }

    /**
     * Check whether the user/session pair was validated recently.
     *
     * @param int $userid
     * @param string $sessionid
     * @return bool
     */
    public static function is_validated(int $userid, string $sessionid): bool {
        if ($userid <= 0 || $sessionid === '') {
            return false;
        }

        $key = self::cache_key($userid, $sessionid);
        $timestamp = self::cache()->get($key);

        if (!is_int($timestamp)) {
            return false;
        }

        if ((time() - $timestamp) > self::VALIDITY_TTL) {
            self::cache()->delete($key);
            return false;
        }

        return true;
    }

    /**
     * Build a cache key from user and session.
     *
     * @param int $userid
     * @param string $sessionid
     * @return string
     */
    private static function cache_key(int $userid, string $sessionid): string {
        return hash('sha256', $userid . '|' . $sessionid);
    }

    /**
     * Get plugin cache instance.
     *
     * @return cache
     */
    private static function cache(): cache {
        return cache::make('factor_mobile_autologin', self::CACHE_DEFINITION);
    }
}
