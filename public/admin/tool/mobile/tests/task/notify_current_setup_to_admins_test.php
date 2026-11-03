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

use tool_mobile\local\current_setup_detection;

/**
 * Tests for the current setup notification task.
 *
 * @package    tool_mobile
 * @copyright  2026 Daniel Ureña <daniel.urena@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(notify_current_setup_to_admins::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\tool_mobile\output\current_setup_message::class)]
final class notify_current_setup_to_admins_test extends \advanced_testcase {
    /** @var string Additional HTML content with Matomo tracking code. */
    private const MATOMO_HTML = '<script>var _paq = window._paq = window._paq || [];</script>';

    #[\Override]
    protected function setUp(): void {
        global $CFG;

        parent::setUp();
        $this->resetAfterTest();
        $CFG->enablemobilewebservice = 1;
    }

    /**
     * Test that nothing is sent when no setup change was detected.
     */
    public function test_execute_without_detection(): void {
        $this->seed_subscription_cache('free');
        set_config('logo', '/logo.png', 'core_admin');

        $sink = $this->redirectMessages();
        $output = $this->capture_task_output();

        $this->assertCount(0, $sink->get_messages());
        $this->assertStringContainsString('tool_mobile: no pending setup changes, no notification sent to admins.', $output);
    }

    /**
     * Test that nothing is sent before the notification delay, and the detection is kept.
     */
    public function test_execute_before_delay(): void {
        $clock = $this->mock_clock_with_frozen();
        $this->seed_subscription_cache('free');
        set_config('logo', '/logo.png', 'core_admin', true);
        $detectedtime = $clock->time();

        $clock->bump(current_setup_detection::NOTIFICATION_DELAY - 1);
        $sink = $this->redirectMessages();
        $output = $this->capture_task_output();

        $this->assertCount(0, $sink->get_messages());
        $this->assertEquals($detectedtime, get_config('tool_mobile', 'currentsetupdetectedtime'));
        $this->assertStringContainsString(
            'tool_mobile: setup change detected at ' . date('Y-m-d H:i:s', $detectedtime) . ', admins will be notified from '
                . date('Y-m-d H:i:s', $detectedtime + current_setup_detection::NOTIFICATION_DELAY) . '.',
            $output,
        );
    }

    /**
     * Test that the notification is sent to admins once the delay has passed.
     */
    public function test_execute_sends_notification_to_admins(): void {
        global $SITE;

        $clock = $this->mock_clock_with_frozen();
        $this->seed_subscription_cache('free', 2);
        set_config('logo', '/logo.png', 'core_admin', true);
        set_config('additionalhtmlhead', self::MATOMO_HTML, null, true);

        $clock->bump(current_setup_detection::NOTIFICATION_DELAY);
        $sink = $this->redirectMessages();
        $output = $this->capture_task_output();

        $messages = $sink->get_messages();
        $this->assertCount(count(get_admins()), $messages);
        foreach ($messages as $message) {
            $this->assertEquals(\core_user::get_noreply_user()->id, $message->useridfrom);
            $this->assertEquals('tool_mobile', $message->component);
            $this->assertEquals('currentsetup', $message->eventtype);
            $this->assertEquals(get_string('currentsetupsubject', 'tool_mobile'), $message->subject);
            $expectedstrings = [
                get_string('currentsetupheading', 'tool_mobile'),
                get_string('currentsetupintro', 'tool_mobile', $SITE->fullname),
                get_string('currentsetupmatomo', 'tool_mobile'),
                get_string('currentsetuplogo', 'tool_mobile'),
                get_string('currentsetupcoursedownloadslimited', 'tool_mobile', ['plan' => 'free', 'limit' => 2]),
                get_string('currentsetupbiometriclogin', 'tool_mobile'),
                get_string('currentsetuppushnotifications', 'tool_mobile'),
                get_string('seeplandetails', 'tool_mobile'),
                '/admin/tool/mobile/subscription.php',
            ];
            foreach ($expectedstrings as $expected) {
                $this->assertStringContainsString($expected, $message->fullmessage);
                $this->assertStringContainsString(s($expected), $message->fullmessagehtml);
            }
            $this->assertStringContainsString('/admin/tool/mobile/pix/currentsetup_chartline.svg', $message->fullmessagehtml);
            $this->assertStringContainsString('/admin/tool/mobile/pix/currentsetup_image.svg', $message->fullmessagehtml);
        }

        $this->assertEquals($clock->time(), get_config('tool_mobile', 'currentsetuplastnotified'));
        $this->assertFalse(get_config('tool_mobile', 'currentsetupdetectedtime'));
        $this->assertStringContainsString('tool_mobile: current setup notification sent to admins (matomo, logo).', $output);

        // It is sent once per detection.
        $output = $this->capture_task_output();
        $this->assertCount(count(get_admins()), $sink->get_messages());
        $this->assertStringContainsString('tool_mobile: no pending setup changes, no notification sent to admins.', $output);
    }

    /**
     * Test the lines included for a Pro plan with only a logo configured.
     */
    public function test_execute_pro_plan_with_logo(): void {
        $clock = $this->mock_clock_with_frozen();
        $this->seed_subscription_cache('pro', 5);
        set_config('logocompact', '/logo.png', 'core_admin', true);

        $clock->bump(current_setup_detection::NOTIFICATION_DELAY);
        $sink = $this->redirectMessages();
        $this->capture_task_output();

        $messages = $sink->get_messages();
        $this->assertCount(count(get_admins()), $messages);
        $message = reset($messages);
        $this->assertStringContainsString(s(get_string('currentsetuplogo', 'tool_mobile')), $message->fullmessagehtml);
        $this->assertStringNotContainsString(s(get_string('currentsetupmatomo', 'tool_mobile')), $message->fullmessagehtml);
        $this->assertStringContainsString(
            s(get_string('currentsetupcoursedownloadslimited', 'tool_mobile', ['plan' => 'pro', 'limit' => 5])),
            $message->fullmessagehtml,
        );
    }

    /**
     * Test that the detection is cancelled if nothing is configured when the delay has passed.
     */
    public function test_execute_cancels_without_setup(): void {
        $clock = $this->mock_clock_with_frozen();
        $this->seed_subscription_cache('free');
        set_config('logo', '/logo.png', 'core_admin', true);
        // Removed without logging the change, so the detection is not cancelled by the observer.
        unset_config('logo', 'core_admin');

        $clock->bump(current_setup_detection::NOTIFICATION_DELAY);
        $sink = $this->redirectMessages();
        $output = $this->capture_task_output();

        $this->assertCount(0, $sink->get_messages());
        $this->assertFalse(get_config('tool_mobile', 'currentsetupdetectedtime'));
        $this->assertStringContainsString(
            'tool_mobile: no logo or Matomo configured, current setup notification cancelled.',
            $output,
        );
    }

    /**
     * Test that admins are notified at most once every six months.
     */
    public function test_execute_cancels_within_minimum_interval(): void {
        $clock = $this->mock_clock_with_frozen();
        $this->seed_subscription_cache('free');
        $lastnotified = $clock->time() - current_setup_detection::NOTIFICATION_MIN_INTERVAL
            + current_setup_detection::NOTIFICATION_DELAY + 1;
        set_config('currentsetuplastnotified', $lastnotified, 'tool_mobile');
        set_config('logo', '/logo.png', 'core_admin', true);

        $clock->bump(current_setup_detection::NOTIFICATION_DELAY);
        $sink = $this->redirectMessages();
        $output = $this->capture_task_output();

        $this->assertCount(0, $sink->get_messages());
        $this->assertFalse(get_config('tool_mobile', 'currentsetupdetectedtime'));
        $this->assertStringContainsString(
            'tool_mobile: admins already notified at ' . date('Y-m-d H:i:s', $lastnotified)
                . ', current setup notification cancelled.',
            $output,
        );

        // A new detection after six months is notified.
        set_config('logo', '', 'core_admin', true);
        set_config('logo', '/logo.png', 'core_admin', true);
        $clock->bump(current_setup_detection::NOTIFICATION_DELAY);
        $this->capture_task_output();
        $this->assertCount(count(get_admins()), $sink->get_messages());
    }

    /**
     * Test that Premium and BMA plans cancel the detection.
     *
     * @param string $plan Current plan.
     */
    #[\PHPUnit\Framework\Attributes\TestWith(['premium'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['bma'])]
    public function test_execute_cancels_for_premium_plans(string $plan): void {
        $clock = $this->mock_clock_with_frozen();
        $this->seed_subscription_cache($plan);
        set_config('logo', '/logo.png', 'core_admin', true);

        $clock->bump(current_setup_detection::NOTIFICATION_DELAY);
        $sink = $this->redirectMessages();
        $output = $this->capture_task_output();

        $this->assertCount(0, $sink->get_messages());
        $this->assertFalse(get_config('tool_mobile', 'currentsetupdetectedtime'));
        $this->assertStringContainsString(
            'tool_mobile: the current plan is ' . $plan . ', current setup notification cancelled.',
            $output,
        );
    }

    /**
     * Test that nothing is sent without plan information, and the detection is kept.
     */
    public function test_execute_keeps_detection_without_plan(): void {
        $clock = $this->mock_clock_with_frozen();
        set_config('logo', '/logo.png', 'core_admin', true);
        $detectedtime = $clock->time();
        $clock->bump(current_setup_detection::NOTIFICATION_DELAY);
        $sink = $this->redirectMessages();

        $output = $this->capture_task_output();

        $this->assertStringContainsString(
            'tool_mobile: no plan information available, no notification sent to admins.',
            $output,
        );
        $this->assertCount(0, $sink->get_messages());
        $this->assertEquals($detectedtime, get_config('tool_mobile', 'currentsetupdetectedtime'));

        // The pending detection is notified once the plan information is available.
        $this->seed_subscription_cache('free');
        $this->capture_task_output();
        $this->assertCount(count(get_admins()), $sink->get_messages());
    }

    /**
     * Seed the subscription cache with the provided plan.
     *
     * @param string $plan Plan identifier.
     * @param ?int $coursedownloadslimit Course downloads limit per device.
     */
    private function seed_subscription_cache(string $plan, ?int $coursedownloadslimit = null): void {
        $cache = \cache::make('tool_mobile', 'subscriptioninfo');
        $cache->set(0, [
            'subscription' => [
                'plan' => $plan,
                'name' => ucfirst($plan),
                'features' => [[
                    'name' => 'coursesdownload',
                    'limit' => $coursedownloadslimit,
                ]],
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
        (new notify_current_setup_to_admins())->execute();

        return (string) ob_get_clean();
    }
}
