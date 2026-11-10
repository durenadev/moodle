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

/**
 * Tests for the site blocked notification task.
 *
 * @package    tool_mobile
 * @copyright  2026 Daniel Ureña <daniel.urena@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(notify_site_blocked_to_admins::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\tool_mobile\output\site_blocked_message::class)]
final class notify_site_blocked_to_admins_test extends \advanced_testcase {
    #[\Override]
    protected function setUp(): void {
        global $CFG;

        parent::setUp();
        $this->resetAfterTest();
        $CFG->enablemobilewebservice = 1;
        $this->set_cached_subscription(true);
    }

    /**
     * Test that nothing is sent while the mobile services are disabled.
     */
    public function test_execute_mobile_services_disabled(): void {
        global $CFG;

        $CFG->enablemobilewebservice = 0;

        $sink = $this->redirectMessages();
        $output = $this->capture_task_output();

        $this->assertCount(0, $sink->get_messages());
        $this->assertStringContainsString('tool_mobile: task not running, mobile app is not enabled.', $output);
    }

    /**
     * Test that nothing is sent, and the last notification is kept, when there is no subscription information.
     */
    public function test_execute_no_subscription_information(): void {
        \cache::make('tool_mobile', 'subscriptioninfo')->delete(0);
        set_config('siteblockedlastnotified', 1, 'tool_mobile');

        $sink = $this->redirectMessages();
        $output = $this->capture_task_output();

        $this->assertCount(0, $sink->get_messages());
        $this->assertEquals(1, get_config('tool_mobile', 'siteblockedlastnotified'));
        $this->assertStringContainsString(
            'tool_mobile: no subscription information available, no notification sent to admins.',
            $output,
        );
    }

    /**
     * Test that nothing is sent while the site is not blocked, and the last notification is forgotten.
     */
    public function test_execute_site_not_blocked(): void {
        $this->set_cached_subscription(false);
        set_config('siteblockedlastnotified', 1, 'tool_mobile');

        $sink = $this->redirectMessages();
        $output = $this->capture_task_output();

        $this->assertCount(0, $sink->get_messages());
        $this->assertFalse(get_config('tool_mobile', 'siteblockedlastnotified'));
        $this->assertStringContainsString('tool_mobile: site is not blocked, no notification sent to admins.', $output);
    }

    /**
     * Test that the notification is sent to admins when the site is blocked.
     */
    public function test_execute_sends_notification_to_admins(): void {
        global $SITE;

        $clock = $this->mock_clock_with_frozen();

        $sink = $this->redirectMessages();
        $output = $this->capture_task_output();

        $messages = $sink->get_messages();
        $this->assertCount(count(get_admins()), $messages);
        $docsurl = new \moodle_url(get_docs_url(notify_site_blocked_to_admins::DOCS_PAGE));
        $iconurl = new \moodle_url('/admin/tool/mobile/pix/mobileusershare_mobile.svg');
        foreach ($messages as $message) {
            $this->assertEquals(\core_user::get_noreply_user()->id, $message->useridfrom);
            $this->assertEquals('tool_mobile', $message->component);
            $this->assertEquals('siteblocked', $message->eventtype);
            $this->assertEquals(get_string('siteblockedsubject', 'tool_mobile'), $message->subject);
            $this->assertEquals($docsurl->out(false), $message->contexturl);
            $expectedstrings = [
                get_string('siteblockedheading', 'tool_mobile'),
                get_string('currentsetupintro', 'tool_mobile', $SITE->fullname),
                get_string('siteblockedbody', 'tool_mobile'),
                get_string('siteblockedmanaged', 'tool_mobile'),
                get_string('learnmore', 'tool_mobile'),
                $docsurl->out(false),
            ];
            foreach ($expectedstrings as $expected) {
                $this->assertStringContainsString($expected, $message->fullmessage);
                $this->assertStringContainsString(s($expected), $message->fullmessagehtml);
            }
            $this->assertStringContainsString(s($iconurl->out(false)), $message->fullmessagehtml);
            $this->assertEquals($iconurl->out(false), json_decode($message->customdata)->notificationiconurl);
        }

        $this->assertEquals($clock->time(), get_config('tool_mobile', 'siteblockedlastnotified'));
        $this->assertStringContainsString('tool_mobile: site blocked notification sent to admins.', $output);
    }

    /**
     * Test that admins are notified at most once a year while the site stays blocked.
     */
    public function test_execute_within_minimum_interval(): void {
        $clock = $this->mock_clock_with_frozen();
        $lastnotified = $clock->time() - notify_site_blocked_to_admins::NOTIFICATION_MIN_INTERVAL + 1;
        set_config('siteblockedlastnotified', $lastnotified, 'tool_mobile');

        $sink = $this->redirectMessages();
        $output = $this->capture_task_output();

        $this->assertCount(0, $sink->get_messages());
        $this->assertStringContainsString(
            'tool_mobile: admins already notified at ' . date('Y-m-d H:i:s', $lastnotified)
                . ', no site blocked notification sent.',
            $output,
        );

        // It is notified again once the minimum interval has passed.
        $clock->bump();
        $this->capture_task_output();
        $this->assertCount(count(get_admins()), $sink->get_messages());
    }

    /**
     * Test that admins are notified again as soon as the site is blocked after being unblocked.
     */
    public function test_execute_blocked_again(): void {
        $this->mock_clock_with_frozen();
        $sink = $this->redirectMessages();
        $this->capture_task_output();
        $this->assertCount(count(get_admins()), $sink->get_messages());

        $this->set_cached_subscription(false);
        $this->capture_task_output();
        $this->set_cached_subscription(true);
        $this->capture_task_output();

        $this->assertCount(2 * count(get_admins()), $sink->get_messages());
    }

    /**
     * Store the subscription information in the cache, as returned by the Apps Portal.
     *
     * @param bool $blocked Whether the site is blocked from the standard Moodle app.
     */
    private function set_cached_subscription(bool $blocked): void {
        $subscription = ['plan' => 'free'];
        if ($blocked) {
            $subscription['restrictaccess'] = ['enforce' => true, 'message' => 'The site is no longer supported in this app.'];
        }
        \cache::make('tool_mobile', 'subscriptioninfo')->set(0, ['subscription' => $subscription, 'availableplans' => []]);
    }

    /**
     * Capture the output produced by the task.
     *
     * @return string
     */
    private function capture_task_output(): string {
        ob_start();
        (new notify_site_blocked_to_admins())->execute();

        return (string) ob_get_clean();
    }
}
