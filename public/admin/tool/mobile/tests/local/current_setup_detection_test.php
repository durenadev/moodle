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
 * Tests for the current setup detection.
 *
 * @package    tool_mobile
 * @copyright  2026 Daniel Ureña <daniel.urena@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(current_setup_detection::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(observer::class)]
final class current_setup_detection_test extends \advanced_testcase {
    /** @var string Additional HTML content with Matomo tracking code. */
    private const MATOMO_HTML = '<script>var _paq = window._paq = window._paq || [];</script>';

    /**
     * Test the detected setup with the stored settings and with overrides.
     */
    public function test_get_detected_setup(): void {
        $this->resetAfterTest();

        $this->assertSame([], current_setup_detection::get_detected_setup());

        set_config('logocompact', '/logo.png', 'core_admin');
        $this->assertSame(['logo'], current_setup_detection::get_detected_setup());

        set_config('additionalhtmlfooter', self::MATOMO_HTML);
        $this->assertSame(
            ['matomo', 'logo'],
            current_setup_detection::get_detected_setup(),
        );

        set_config('additionalhtmlhead', '<script>console.log("no tracking")</script>');
        $this->assertSame(
            ['logo'],
            current_setup_detection::get_detected_setup(['core/additionalhtmlfooter' => '']),
        );
        $this->assertSame(
            ['matomo'],
            current_setup_detection::get_detected_setup(['core_admin/logocompact' => null]),
        );
    }

    /**
     * Test that configuring a logo or Matomo starts a detection, logged before or after storing the value.
     */
    public function test_setting_changed_starts_detection(): void {
        $this->resetAfterTest();
        $clock = $this->mock_clock_with_frozen();

        // Logged before storing the value, as set_config() does.
        set_config('additionalhtmlhead', self::MATOMO_HTML, null, true);
        $this->assertEquals($clock->time(), get_config('tool_mobile', 'currentsetupdetectedtime'));

        unset_config('currentsetupdetectedtime', 'tool_mobile');
        unset_config('additionalhtmlhead');

        // Logged after storing the value, as admin settings do.
        set_config('logo', '/logo.png', 'core_admin');
        add_to_config_log('logo', null, '/logo.png', 'core_admin');
        $this->assertEquals($clock->time(), get_config('tool_mobile', 'currentsetupdetectedtime'));
    }

    /**
     * Test that changes not configuring anything new do not start a detection.
     */
    public function test_setting_changed_ignores_changes_without_new_setup(): void {
        $this->resetAfterTest();
        $this->mock_clock_with_frozen();

        set_config('additionalhtmlhead', '<script>console.log("no tracking")</script>', null, true);
        set_config('logo', '/logo.png', 'core_admin');
        set_config('logo', '/newlogo.png', 'core_admin', true);
        set_config('logocompact', '/logo.png', 'core_admin', true);
        set_config('custommenuitems', self::MATOMO_HTML, 'tool_mobile', true);

        $this->assertFalse(get_config('tool_mobile', 'currentsetupdetectedtime'));
    }

    /**
     * Test that later changes keep the detection start time, and leaving nothing configured cancels it.
     */
    public function test_setting_changed_keeps_or_cancels_detection(): void {
        $this->resetAfterTest();
        $clock = $this->mock_clock_with_frozen();

        set_config('logo', '/logo.png', 'core_admin', true);
        $detectedtime = $clock->time();

        $clock->bump(WEEKSECS);
        set_config('additionalhtmlfooter', self::MATOMO_HTML, null, true);
        set_config('logo', '', 'core_admin', true);
        $this->assertEquals($detectedtime, get_config('tool_mobile', 'currentsetupdetectedtime'));

        unset_config('additionalhtmlfooter', null, true);
        $this->assertFalse(get_config('tool_mobile', 'currentsetupdetectedtime'));

        $clock->bump(WEEKSECS);
        set_config('additionalhtmlfooter', self::MATOMO_HTML, null, true);
        $this->assertEquals($clock->time(), get_config('tool_mobile', 'currentsetupdetectedtime'));
    }
}
