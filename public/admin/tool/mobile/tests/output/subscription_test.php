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

namespace tool_mobile\output;

use tool_mobile\local\mobile_user_share;

/**
 * Tests for the subscription page.
 *
 * @package    tool_mobile
 * @copyright  2026 Daniel Ureña <daniel.urena@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(subscription::class)]
final class subscription_test extends \advanced_testcase {
    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    /**
     * Test that the Moodle app usage is displayed, alone in the current setup, until a year after the notification.
     */
    public function test_mobile_user_share_description(): void {
        $clock = $this->mock_clock_with_frozen();
        set_config('mobileusersharelastnotified', $clock->time(), 'tool_mobile');

        $clock->bump(mobile_user_share::NOTIFICATION_MIN_INTERVAL - 1);
        $data = $this->export_subscription('free');

        $this->assertEquals([
            'mobileusersharedescription' => get_string('mobileusersharecontext', 'tool_mobile', mobile_user_share::THRESHOLD),
        ], $data['morecurrentsetup']);

        $clock->bump();
        $data = $this->export_subscription('free');

        $this->assertArrayNotHasKey('morecurrentsetup', $data);
    }

    /**
     * Test that the Moodle app usage is displayed with the other items of the current setup.
     */
    public function test_mobile_user_share_description_with_current_setup(): void {
        global $CFG;

        $CFG->downloadcoursecontentallowed = 1;
        $clock = $this->mock_clock_with_frozen();
        set_config('mobileusersharelastnotified', $clock->time(), 'tool_mobile');

        $data = $this->export_subscription('pro');

        $this->assertEquals(
            get_string('mobileusersharecontext', 'tool_mobile', mobile_user_share::THRESHOLD),
            $data['morecurrentsetup']['mobileusersharedescription'],
        );
        $this->assertEquals(1, $data['morecurrentsetup']['coursedownloadsinapp']);
    }

    /**
     * Test that the Moodle app usage is not displayed when admins were never notified.
     */
    public function test_mobile_user_share_description_not_notified(): void {
        global $CFG;

        $CFG->downloadcoursecontentallowed = 1;

        $data = $this->export_subscription('free');

        $this->assertArrayNotHasKey('mobileusersharedescription', $data['morecurrentsetup']);
    }

    /**
     * Test that the Moodle app usage is not displayed for Premium plans.
     */
    public function test_mobile_user_share_description_premium(): void {
        $clock = $this->mock_clock_with_frozen();
        set_config('mobileusersharelastnotified', $clock->time(), 'tool_mobile');

        $data = $this->export_subscription('premium');

        $this->assertArrayNotHasKey('morecurrentsetup', $data);
    }

    /**
     * Export the subscription page for the provided plan.
     *
     * @param string $plan Plan identifier.
     * @return array
     */
    private function export_subscription(string $plan): array {
        global $PAGE;

        $subscription = new subscription([
            'subscription' => [
                'plan' => $plan,
                'name' => ucfirst($plan),
                'features' => [],
            ],
            'availableplans' => [],
        ]);

        return $subscription->export_for_template($PAGE->get_renderer('tool_mobile'));
    }
}
