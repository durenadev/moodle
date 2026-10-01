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

/**
 * Tests for the Matomo analytics text setting.
 *
 * @package    tool_mobile
 * @copyright  2026 Daniel Ureña <daniel.urena@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(matomo_configtext::class)]
final class matomo_configtext_test extends \advanced_testcase {
    /**
     * Test that values are stored only while Matomo analytics is enabled.
     */
    public function test_write_setting(): void {
        $this->resetAfterTest();

        $urlsetting = new matomo_configtext('tool_mobile/matomoanalyticurl', 'URL', '', '', PARAM_URL);
        $idsetting = new matomo_configtext('tool_mobile/matomoanalyticwebsiteid', 'ID', '', '', PARAM_RAW);

        // Enabled: values are stored.
        set_config('matomoanalytic', 1, 'tool_mobile');
        $this->assertSame('', $urlsetting->write_setting('https://matomo.example.com'));
        $this->assertSame('', $idsetting->write_setting('42'));
        $this->assertSame('https://matomo.example.com', get_config('tool_mobile', 'matomoanalyticurl'));
        $this->assertSame('42', get_config('tool_mobile', 'matomoanalyticwebsiteid'));

        // Disabled: submitted values are discarded and stored values are reset.
        set_config('matomoanalytic', 0, 'tool_mobile');
        $this->assertSame('', $urlsetting->write_setting('https://matomo.example.com'));
        $this->assertSame('', $idsetting->write_setting('42'));
        $this->assertSame('', get_config('tool_mobile', 'matomoanalyticurl'));
        $this->assertSame('', get_config('tool_mobile', 'matomoanalyticwebsiteid'));
    }

    /**
     * Test that stored values are only shown while Matomo analytics is enabled.
     */
    public function test_get_setting(): void {
        $this->resetAfterTest();

        $urlsetting = new matomo_configtext('tool_mobile/matomoanalyticurl', 'URL', '', '', PARAM_URL);

        // Not configured yet: null is kept so the setting is detected as new.
        unset_config('matomoanalyticurl', 'tool_mobile');
        $this->assertNull($urlsetting->get_setting());

        // Values stored without the admin form (e.g. via CLI).
        set_config('matomoanalyticurl', 'https://matomo.example.com', 'tool_mobile');

        set_config('matomoanalytic', 1, 'tool_mobile');
        $this->assertSame('https://matomo.example.com', $urlsetting->get_setting());

        set_config('matomoanalytic', 0, 'tool_mobile');
        $this->assertSame('', $urlsetting->get_setting());
    }
}
