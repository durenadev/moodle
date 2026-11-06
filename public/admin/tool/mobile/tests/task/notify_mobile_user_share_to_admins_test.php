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

namespace tool_mobile\task;

use tool_mobile\local\mobile_user_share;

/**
 * Tests for the mobile user share notification task.
 *
 * @package    tool_mobile
 * @copyright  2026 Daniel Ureña <daniel.urena@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(notify_mobile_user_share_to_admins::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\tool_mobile\output\mobile_user_share_message::class)]
final class notify_mobile_user_share_to_admins_test extends \advanced_testcase {
    #[\Override]
    protected function setUp(): void {
        global $CFG;

        parent::setUp();
        $this->resetAfterTest();
        $CFG->enablemobilewebservice = 1;
    }

    /**
     * Test that nothing is sent while the usage does not exceed the threshold.
     *
     * @param int $webusers Active users only on the site.
     * @param int $appusers Active users of the Moodle app.
     * @param string $expectedusage Usage displayed in the task log.
     */
    #[\PHPUnit\Framework\Attributes\TestWith([50, 50, '50% of active users (50 of 100)'])]
    #[\PHPUnit\Framework\Attributes\TestWith([0, 49, '100% of active users (49 of 49)'])]
    public function test_execute_below_threshold(int $webusers, int $appusers, string $expectedusage): void {
        $this->mock_clock_with_frozen();
        $this->seed_subscription_cache('free');
        $this->create_active_users($webusers, $appusers);

        $sink = $this->redirectMessages();
        $output = $this->capture_task_output();

        $this->assertCount(0, $sink->get_messages());
        $this->assertFalse(get_config('tool_mobile', 'mobileusersharelastnotified'));
        $this->assertStringContainsString(
            'tool_mobile: ' . $expectedusage . ' use the Moodle app, no notification sent to admins.',
            $output,
        );
    }

    /**
     * Test that the notification is sent to admins when the usage exceeds the threshold.
     *
     * @param string $plan Current plan.
     */
    #[\PHPUnit\Framework\Attributes\TestWith(['free'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['pro'])]
    public function test_execute_sends_notification_to_admins(string $plan): void {
        global $SITE;

        $clock = $this->mock_clock_with_frozen();
        $this->seed_subscription_cache($plan);
        $this->create_active_users(38, 62);

        $sink = $this->redirectMessages();
        $output = $this->capture_task_output();

        $messages = $sink->get_messages();
        $this->assertCount(count(get_admins()), $messages);
        foreach ($messages as $message) {
            $this->assertEquals(\core_user::get_noreply_user()->id, $message->useridfrom);
            $this->assertEquals('tool_mobile', $message->component);
            $this->assertEquals('mobileusershare', $message->eventtype);
            $this->assertEquals(get_string('mobileusersharesubject', 'tool_mobile', 62), $message->subject);
            $expectedstrings = [
                get_string('mobileusershareheading', 'tool_mobile'),
                get_string('currentsetupintro', 'tool_mobile', $SITE->fullname),
                get_string('mobileusershareusage', 'tool_mobile', ['percentage' => 62, 'users' => 62]),
                get_string('mobileusersharebenefits', 'tool_mobile'),
                get_string('seeplandetails', 'tool_mobile'),
                '/admin/tool/mobile/subscription.php',
            ];
            foreach ($expectedstrings as $expected) {
                $this->assertStringContainsString($expected, $message->fullmessage);
                $this->assertStringContainsString(s($expected), $message->fullmessagehtml);
            }
            $this->assertStringContainsString('/admin/tool/mobile/pix/mobileusershare_mobile.svg', $message->fullmessagehtml);
        }

        $this->assertEquals($clock->time(), get_config('tool_mobile', 'mobileusersharelastnotified'));
        $this->assertStringContainsString(
            'tool_mobile: mobile user share notification sent to admins (62% of active users (62 of 100)).',
            $output,
        );
    }

    /**
     * Test that admins are notified at most once a year while the usage exceeds the threshold.
     */
    public function test_execute_within_minimum_interval(): void {
        $clock = $this->mock_clock_with_frozen();
        $this->seed_subscription_cache('free');
        $this->create_active_users(0, 50);
        $lastnotified = $clock->time() - mobile_user_share::NOTIFICATION_MIN_INTERVAL + 1;
        set_config('mobileusersharelastnotified', $lastnotified, 'tool_mobile');

        $sink = $this->redirectMessages();
        $output = $this->capture_task_output();

        $this->assertCount(0, $sink->get_messages());
        $this->assertStringContainsString(
            'tool_mobile: admins already notified at ' . date('Y-m-d H:i:s', $lastnotified)
                . ', no mobile user share notification sent.',
            $output,
        );

        // It is notified again once the minimum interval has passed.
        $clock->bump();
        $this->capture_task_output();
        $this->assertCount(count(get_admins()), $sink->get_messages());
    }

    /**
     * Test that nothing is sent for Premium and BMA plans.
     *
     * @param string $plan Current plan.
     */
    #[\PHPUnit\Framework\Attributes\TestWith(['premium'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['bma'])]
    public function test_execute_skips_premium_plans(string $plan): void {
        $this->mock_clock_with_frozen();
        $this->seed_subscription_cache($plan);
        $this->create_active_users(0, 50);

        $sink = $this->redirectMessages();
        $output = $this->capture_task_output();

        $this->assertCount(0, $sink->get_messages());
        $this->assertFalse(get_config('tool_mobile', 'mobileusersharelastnotified'));
        $this->assertStringContainsString(
            'tool_mobile: the current plan is ' . $plan . ', no mobile user share notification sent.',
            $output,
        );
    }

    /**
     * Test that nothing is sent without plan information.
     */
    public function test_execute_without_plan(): void {
        $this->mock_clock_with_frozen();
        $this->create_active_users(0, 50);

        $sink = $this->redirectMessages();
        $output = $this->capture_task_output();

        $this->assertCount(0, $sink->get_messages());
        $this->assertFalse(get_config('tool_mobile', 'mobileusersharelastnotified'));
        $this->assertStringContainsString(
            'tool_mobile: no plan information available, no notification sent to admins.',
            $output,
        );

        // It is notified once the plan information is available.
        $this->seed_subscription_cache('free');
        $this->capture_task_output();
        $this->assertCount(count(get_admins()), $sink->get_messages());
    }

    /**
     * Create users active on the site, and users active in the Moodle app.
     *
     * @param int $webusers Number of users active only on the site.
     * @param int $appusers Number of users active in the Moodle app.
     */
    private function create_active_users(int $webusers, int $appusers): void {
        global $DB;

        $now = \core\di::get(\core\clock::class)->time();
        $generator = $this->getDataGenerator();
        for ($i = 0; $i < $webusers; $i++) {
            $generator->create_user(['lastaccess' => $now]);
        }

        $service = $DB->get_record('external_services', ['shortname' => MOODLE_OFFICIAL_MOBILE_SERVICE], '*', MUST_EXIST);
        $context = \context_system::instance();
        for ($i = 0; $i < $appusers; $i++) {
            $user = $generator->create_user();
            $token = \core_external\util::generate_token(EXTERNAL_TOKEN_PERMANENT, $service, $user->id, $context);
            $DB->set_field('external_tokens', 'lastaccess', $now, ['token' => $token]);
        }
    }

    /**
     * Seed the subscription cache with the provided plan.
     *
     * @param string $plan Plan identifier.
     */
    private function seed_subscription_cache(string $plan): void {
        $cache = \cache::make('tool_mobile', 'subscriptioninfo');
        $cache->set(0, [
            'subscription' => [
                'plan' => $plan,
                'name' => ucfirst($plan),
                'features' => [],
            ],
        ]);
    }

    /**
     * Capture the output produced by the task.
     *
     * @return string
     */
    private function capture_task_output(): string {
        ob_start();
        (new notify_mobile_user_share_to_admins())->execute();

        return (string) ob_get_clean();
    }
}
