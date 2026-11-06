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
 * Tests for the mobile services off notification task.
 *
 * @package    tool_mobile
 * @copyright  2026 Daniel Ureña <daniel.urena@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(notify_mobile_services_off_to_admins::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\tool_mobile\output\mobile_services_off_message::class)]
final class notify_mobile_services_off_to_admins_test extends \advanced_testcase {
    #[\Override]
    protected function setUp(): void {
        global $CFG;

        parent::setUp();
        $this->resetAfterTest();
        $CFG->enablemobilewebservice = 0;
    }

    /**
     * Test that nothing is sent while the mobile services are enabled, and the last notification is forgotten.
     */
    public function test_execute_mobile_services_enabled(): void {
        global $CFG;

        $CFG->enablemobilewebservice = 1;
        set_config('mobileservicesofflastnotified', 1, 'tool_mobile');

        $sink = $this->redirectMessages();
        $output = $this->capture_task_output();

        $this->assertCount(0, $sink->get_messages());
        $this->assertFalse(get_config('tool_mobile', 'mobileservicesofflastnotified'));
        $this->assertStringContainsString(
            'tool_mobile: mobile services are enabled, no notification sent to admins.',
            $output,
        );
    }

    /**
     * Test that the notification is sent to admins when the mobile services are turned off.
     */
    public function test_execute_sends_notification_to_admins(): void {
        global $SITE;

        $clock = $this->mock_clock_with_frozen();

        $sink = $this->redirectMessages();
        $output = $this->capture_task_output();

        $messages = $sink->get_messages();
        $this->assertCount(count(get_admins()), $messages);
        $settingurl = new \moodle_url('/admin/search.php', ['query' => 'enablemobilewebservice']);
        $iconurl = new \moodle_url('/admin/tool/mobile/pix/mobileservicesoff_warning.svg');
        foreach ($messages as $message) {
            $this->assertEquals(\core_user::get_noreply_user()->id, $message->useridfrom);
            $this->assertEquals('tool_mobile', $message->component);
            $this->assertEquals('mobileservicesoff', $message->eventtype);
            $this->assertEquals(get_string('mobileservicesoffsubject', 'tool_mobile'), $message->subject);
            $this->assertEquals($settingurl->out(false), $message->contexturl);
            $expectedstrings = [
                get_string('mobileservicesoffheading', 'tool_mobile'),
                get_string('currentsetupintro', 'tool_mobile', $SITE->fullname),
                get_string('mobileservicesoffbody', 'tool_mobile'),
                get_string('mobileservicesoffdismiss', 'tool_mobile'),
                get_string('enablethissetting', 'tool_mobile'),
                $settingurl->out(false),
            ];
            foreach ($expectedstrings as $expected) {
                $this->assertStringContainsString($expected, $message->fullmessage);
                $this->assertStringContainsString(s($expected), $message->fullmessagehtml);
            }
            $this->assertStringContainsString(s($iconurl->out(false)), $message->fullmessagehtml);
            $this->assertEquals($iconurl->out(false), json_decode($message->customdata)->notificationiconurl);
        }

        $this->assertEquals($clock->time(), get_config('tool_mobile', 'mobileservicesofflastnotified'));
        $this->assertStringContainsString('tool_mobile: mobile services off notification sent to admins.', $output);
    }

    /**
     * Test that admins are notified at most once a year while the mobile services stay off.
     */
    public function test_execute_within_minimum_interval(): void {
        $clock = $this->mock_clock_with_frozen();
        $lastnotified = $clock->time() - notify_mobile_services_off_to_admins::NOTIFICATION_MIN_INTERVAL + 1;
        set_config('mobileservicesofflastnotified', $lastnotified, 'tool_mobile');

        $sink = $this->redirectMessages();
        $output = $this->capture_task_output();

        $this->assertCount(0, $sink->get_messages());
        $this->assertStringContainsString(
            'tool_mobile: admins already notified at ' . date('Y-m-d H:i:s', $lastnotified)
                . ', no mobile services off notification sent.',
            $output,
        );

        // It is notified again once the minimum interval has passed.
        $clock->bump();
        $this->capture_task_output();
        $this->assertCount(count(get_admins()), $sink->get_messages());
    }

    /**
     * Test that admins are notified again as soon as the mobile services are turned off after being enabled.
     */
    public function test_execute_turned_off_again(): void {
        global $CFG;

        $this->mock_clock_with_frozen();
        $sink = $this->redirectMessages();
        $this->capture_task_output();
        $this->assertCount(count(get_admins()), $sink->get_messages());

        $CFG->enablemobilewebservice = 1;
        $this->capture_task_output();
        $CFG->enablemobilewebservice = 0;
        $this->capture_task_output();

        $this->assertCount(2 * count(get_admins()), $sink->get_messages());
    }

    /**
     * Capture the output produced by the task.
     *
     * @return string
     */
    private function capture_task_output(): string {
        ob_start();
        (new notify_mobile_services_off_to_admins())->execute();

        return (string) ob_get_clean();
    }
}
