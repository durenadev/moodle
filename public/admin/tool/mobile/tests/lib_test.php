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
}
