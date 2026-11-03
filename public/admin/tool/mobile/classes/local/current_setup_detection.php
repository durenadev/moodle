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
use tool_mobile\api;

/**
 * Detection of site settings that can also be used in the Moodle app with a Premium plan.
 *
 * A detection starts when an admin configures a logo or Matomo, and it is notified to admins after a delay
 * by the {@see \tool_mobile\task\notify_current_setup_to_admins} task. Any later change leaving nothing
 * configured cancels the pending detection.
 *
 * @package    tool_mobile
 * @copyright  2026 Daniel Ureña <daniel.urena@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class current_setup_detection {
    /** @var int Time to wait since a setting change is detected before notifying admins. */
    public const NOTIFICATION_DELAY = 6 * WEEKSECS;

    /** @var int Minimum time between two notifications. */
    public const NOTIFICATION_MIN_INTERVAL = YEARSECS / 2;

    /** @var string[] Core settings that may contain Matomo tracking code. */
    private const MATOMO_SETTINGS = [
        'additionalhtmlhead',
        'additionalhtmltopofbody',
        'additionalhtmlfooter',
    ];

    /** @var string[] Logo settings of the core_admin component. */
    private const LOGO_SETTINGS = [
        'logo',
        'logocompact',
    ];

    /**
     * Get the detectable settings currently configured on the site.
     *
     * @param array $overrides Setting values to use instead of the stored ones, keyed by "component/name".
     * @return string[] List of 'matomo' and 'logo', in display order.
     */
    public static function get_detected_setup(array $overrides = []): array {
        global $CFG;

        $setup = [];
        foreach (self::MATOMO_SETTINGS as $name) {
            $content = self::get_setting_value('core', $name, $CFG->{$name} ?? null, $overrides);
            if (api::contains_matomo_tracking($content)) {
                $setup[] = 'matomo';
                break;
            }
        }

        foreach (self::LOGO_SETTINGS as $name) {
            if (!empty(self::get_setting_value('core_admin', $name, get_config('core_admin', $name), $overrides))) {
                $setup[] = 'logo';
                break;
            }
        }

        return $setup;
    }

    /**
     * Review a setting change, starting or cancelling the pending detection.
     *
     * The change may be logged before or after the new value is stored, so both values are passed as overrides.
     *
     * @param ?string $component Component of the setting, empty for core settings.
     * @param string $name Setting name.
     * @param ?string $oldvalue Previous value of the setting, null if it was not set.
     * @param ?string $value New value of the setting, null if it is being removed.
     */
    public static function setting_changed(?string $component, string $name, ?string $oldvalue, ?string $value): void {
        $component = empty($component) ? 'core' : $component;
        if (!self::is_detectable_setting($component, $name)) {
            return;
        }

        $key = $component . '/' . $name;
        $previoussetup = self::get_detected_setup([$key => $oldvalue]);
        $newsetup = self::get_detected_setup([$key => $value]);
        if (empty($newsetup)) {
            unset_config('currentsetupdetectedtime', 'tool_mobile');
            return;
        }

        // Only something newly configured starts a detection, and later changes keep the original start time.
        if (array_diff($newsetup, $previoussetup) && empty(get_config('tool_mobile', 'currentsetupdetectedtime'))) {
            set_config('currentsetupdetectedtime', di::get(clock::class)->time(), 'tool_mobile');
        }
    }

    /**
     * Whether the setting affects the detected setup.
     *
     * @param string $component Component of the setting, 'core' for core settings.
     * @param string $name Setting name.
     * @return bool
     */
    private static function is_detectable_setting(string $component, string $name): bool {
        return ($component === 'core' && in_array($name, self::MATOMO_SETTINGS)) ||
            ($component === 'core_admin' && in_array($name, self::LOGO_SETTINGS));
    }

    /**
     * Get a setting value, giving priority to the provided overrides.
     *
     * @param string $component Component of the setting, 'core' for core settings.
     * @param string $name Setting name.
     * @param mixed $storedvalue Value currently stored.
     * @param array $overrides Setting values keyed by "component/name".
     * @return ?string
     */
    private static function get_setting_value(string $component, string $name, mixed $storedvalue, array $overrides): ?string {
        $key = $component . '/' . $name;
        if (array_key_exists($key, $overrides)) {
            return $overrides[$key];
        }

        return $storedvalue === false || $storedvalue === null ? null : (string) $storedvalue;
    }
}
