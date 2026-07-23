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

namespace tool_mobile;

/**
 * Tests for tool_mobile lib functions.
 *
 * @package    tool_mobile
 * @copyright  2026 Daniel Ureña <daniel.urena@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \\tool_mobile_is_premium_or_bma_plan
 * @covers \\tool_mobile_contains_matomo_tracking
 * @covers \\tool_mobile_has_matomo_additional_html
 * @covers \\tool_mobile_is_premium_or_bma_plan
 */
final class lib_test extends \advanced_testcase {
    /**
     * Test Premium and BMA plan detection.
     */
    public function test_is_premium_or_bma_plan(): void {
        $this->resetAfterTest(true);

        $this->assertTrue(\tool_mobile_is_premium_or_bma_plan([
            'subscription' => ['plan' => 'premium'],
        ]));
        $this->assertTrue(\tool_mobile_is_premium_or_bma_plan([
            'subscription' => ['plan' => ' BMA '],
        ]));
        $this->assertFalse(\tool_mobile_is_premium_or_bma_plan([
            'subscription' => ['plan' => 'free'],
        ]));
        $this->assertFalse(\tool_mobile_is_premium_or_bma_plan([
            'subscription' => [],
        ]));
        $this->assertFalse(\tool_mobile_is_premium_or_bma_plan(null));

        $cache = \cache::make('tool_mobile', 'subscriptioninfo');
        $cache->set(0, [
            'subscription' => ['plan' => ' Premium '],
        ]);

        $this->assertTrue(\tool_mobile_is_premium_or_bma_plan(null));
        $this->assertTrue(\tool_mobile_is_premium_or_bma_plan([
            'subscription' => ['plan' => 123],
        ]));
    }

    /**
     * Test Matomo detection against common identifiers.
     */
    public function test_contains_matomo_tracking(): void {
        $samples = [
            "var _paq = window._paq || [];",
            '<script src="https://example.com/matomo.js"></script>',
            '<img src="https://example.com/matomo.php?idsite=1">',
            '<script src="https://example.com/piwik.js"></script>',
            '<img src="https://example.com/piwik.php?idsite=1">',
            'trackPageView',
            'enableLinkTracking',
            'setTrackerUrl',
            'setSiteId',
            'TRACKPAGEVIEW',
        ];

        foreach ($samples as $sample) {
            $this->assertTrue(\tool_mobile_contains_matomo_tracking($sample));
        }

        $this->assertFalse(\tool_mobile_contains_matomo_tracking('Google Analytics content only'));
        $this->assertFalse(\tool_mobile_contains_matomo_tracking(''));
        $this->assertFalse(\tool_mobile_contains_matomo_tracking(null));
    }

    /**
     * Test Matomo detection in the Additional HTML settings.
     */
    public function test_has_matomo_additional_html(): void {
        global $CFG;

        $this->resetAfterTest(true);

        set_config('additionalhtmlhead', '<script>console.log("no matomo")</script>');
        set_config('additionalhtmltopofbody', '<script>var _paq = window._paq || [];</script>');
        set_config('additionalhtmlfooter', '');
        $CFG->additionalhtmlhead = '<script>console.log("no matomo")</script>';
        $CFG->additionalhtmltopofbody = '<script>var _paq = window._paq || [];</script>';
        $CFG->additionalhtmlfooter = '';

        $this->assertTrue(\tool_mobile_has_matomo_additional_html());

        set_config('additionalhtmlhead', '');
        set_config('additionalhtmltopofbody', '');
        set_config('additionalhtmlfooter', '');
        $CFG->additionalhtmlhead = '';
        $CFG->additionalhtmltopofbody = '';
        $CFG->additionalhtmlfooter = '';

        $this->assertFalse(\tool_mobile_has_matomo_additional_html());
    }

    /**
     * Test Premium and BMA plan detection.
     */
    public function test_is_premium_or_bma_plan(): void {
        $this->assertTrue(\tool_mobile_is_premium_or_bma_plan([
            'subscription' => ['plan' => 'premium'],
        ]));
        $this->assertTrue(\tool_mobile_is_premium_or_bma_plan([
            'subscription' => ['plan' => ' BMA '],
        ]));
        $this->assertFalse(\tool_mobile_is_premium_or_bma_plan([
            'subscription' => ['plan' => 'free'],
        ]));
        $this->assertFalse(\tool_mobile_is_premium_or_bma_plan([
            'subscription' => [],
        ]));
        $this->assertFalse(\tool_mobile_is_premium_or_bma_plan(null));
    }
}
