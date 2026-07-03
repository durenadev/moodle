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
 * Tests for the push notification limit task.
 *
 * @covers \tool_mobile\task\notify_push_notification_limit_to_admins
 * @package    tool_mobile
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class notify_push_notification_limit_to_admins_test extends \advanced_testcase {
    /**
     * Test that the push limit message provider is registered.
     */
    public function test_push_limit_message_provider_is_registered(): void {
        $this->resetAfterTest(true);

        message_update_providers('tool_mobile');
        $providers = message_get_providers_from_db('tool_mobile');

        $this->assertArrayHasKey('pushlimitreached', $providers);
        $this->assertEquals('tool_mobile', $providers['pushlimitreached']->component);
        $this->assertEquals('pushlimitreached', $providers['pushlimitreached']->name);
    }

    /**
     * Test that a message is sent to admins when the monthly limit has been reached.
     */
    public function test_execute_sends_notification_to_admins(): void {
        global $CFG;

        $this->resetAfterTest(true);
        $this->setAdminUser();
        $CFG->enablemobilewebservice = 1;

        $this->seed_subscription_cache(1234567890);

        $sink = $this->redirectMessages();
        $task = new \tool_mobile\task\notify_push_notification_limit_to_admins();
        $task->execute();

        $messages = $sink->get_messages();
        $expectedrecipientids = array_map(static function ($admin) {
            return (int) $admin->id;
        }, get_admins());

        $this->assertCount(count($expectedrecipientids), $messages);

        $actualrecipientids = [];
        foreach ($messages as $message) {
            $actualrecipientids[] = (int) $message->useridto;
            $this->assertEquals(\core_user::get_noreply_user()->id, $message->useridfrom);
            $this->assertEquals('tool_mobile', $message->component);
            $this->assertEquals('pushlimitreached', $message->eventtype);
            $this->assertEquals(get_string('checkpushnotificationlimitssubject', 'tool_mobile'), $message->subject);
            $this->assertStringContainsString('/admin/tool/mobile/subscription.php', $message->fullmessage);
            $this->assertStringContainsString(
                'Your monthly device limit for push notifications has been reached',
                $message->fullmessagehtml
            );
            $this->assertStringContainsString('60 / 50', $message->fullmessagehtml);
            $this->assertStringContainsString('Upgrade your plan', $message->fullmessagehtml);
            $this->assertStringContainsString('/admin/tool/mobile/pix/push_notification.svg', $message->fullmessagehtml);
            $this->assertStringContainsString('tool-mobile-push-limit-alert-icon', $message->fullmessagehtml);
            $this->assertStringContainsString('/pix/i/risk_xss.svg', $message->fullmessagehtml);
            $this->assertStringContainsString('tool-mobile-push-limit-card', $message->fullmessagehtml);
        }

        sort($expectedrecipientids);
        sort($actualrecipientids);
        $this->assertSame($expectedrecipientids, $actualrecipientids);
        $this->assertEquals('1234567890', get_config('tool_mobile', 'pushnotificationlimitlastnotified'));
    }

    /**
     * Test that the same limit reached event is not notified twice.
     */
    public function test_execute_does_not_send_duplicate_notification(): void {
        global $CFG;

        $this->resetAfterTest(true);
        $this->setAdminUser();
        $CFG->enablemobilewebservice = 1;

        $this->seed_subscription_cache(1234567890);

        $sink = $this->redirectMessages();
        $task = new \tool_mobile\task\notify_push_notification_limit_to_admins();
        $task->execute();
        $task->execute();

        $messages = $sink->get_messages();
        $this->assertCount(count(get_admins()), $messages);
    }

    /**
     * Test that only the current month statistics are considered.
     */
    public function test_get_current_month_notification_stats_ignores_other_months(): void {
        $stats = notify_push_notification_limit_to_admins::get_current_month_notification_stats([
            'statistics' => [
                'notifications' => [
                    'monthly' => [
                        [
                            'year' => 2026,
                            'month' => 6,
                            'limitreachedtime' => 111,
                        ],
                        [
                            'year' => 2026,
                            'month' => 7,
                            'limitreachedtime' => 222,
                            'activedevices' => 60,
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertNotNull($stats);
        $this->assertSame(222, $stats['limitreachedtime']);
        $this->assertSame(7, $stats['month']);
    }

    /**
     * Seed the subscription cache with current month notification statistics.
     *
     * @param int $limitreachedtime The timestamp used to identify the limit reached event.
     */
    private function seed_subscription_cache(int $limitreachedtime): void {
        $cache = \cache::make('tool_mobile', 'subscriptioninfo');
        $cache->set(0, [
            'statistics' => [
                'notifications' => [
                    'monthly' => [[
                        'year' => 2026,
                        'month' => 7,
                        'sentnotifications' => 120,
                        'ignorednotifications' => 15,
                        'newdevices' => 8,
                        'activedevices' => 60,
                        'limitreachedtime' => $limitreachedtime,
                    ]],
                ],
            ],
            'subscription' => [
                'features' => [[
                    'name' => 'pushnotificationsdevices',
                    'limit' => 50,
                ]],
            ],
        ]);
    }
}
