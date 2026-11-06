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

use core\clock;
use core\di;
use tool_mobile\output\mobile_services_off_message;

/**
 * Scheduled task to notify admins when the mobile services are turned off on the site.
 *
 * @package    tool_mobile
 * @copyright  2026 Daniel Ureña <daniel.urena@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class notify_mobile_services_off_to_admins extends \core\task\scheduled_task {
    /** @var int Minimum time between two notifications while the mobile services stay off. */
    public const NOTIFICATION_MIN_INTERVAL = YEARSECS;

    /**
     * Return the task name.
     *
     * @return string
     */
    public function get_name() {
        return get_string('tasknotifymobileservicesofftoadmins', 'tool_mobile');
    }

    /**
     * Execute the task.
     */
    public function execute() {
        global $CFG;

        mtrace('tool_mobile: Running scheduled mobile services off check task...');
        if (!empty($CFG->enablemobilewebservice)) {
            // Admins are notified again as soon as the mobile services are turned off in the future.
            unset_config('mobileservicesofflastnotified', 'tool_mobile');
            mtrace('tool_mobile: mobile services are enabled, no notification sent to admins.');
            return;
        }

        $now = di::get(clock::class)->time();
        $lastnotified = (int) get_config('tool_mobile', 'mobileservicesofflastnotified');
        if ($lastnotified > 0 && $now < $lastnotified + self::NOTIFICATION_MIN_INTERVAL) {
            mtrace('tool_mobile: admins already notified at ' . date('Y-m-d H:i:s', $lastnotified)
                . ', no mobile services off notification sent.');
            return;
        }

        if (!self::notify_admins()) {
            mtrace('tool_mobile: failed to notify admins about the mobile services being off.');
            return;
        }

        set_config('mobileservicesofflastnotified', $now, 'tool_mobile');
        mtrace('tool_mobile: mobile services off notification sent to admins.');
    }

    /**
     * Notify all site admins that the mobile services are turned off.
     *
     * @return bool True if at least one message was attempted.
     */
    protected static function notify_admins(): bool {
        global $PAGE, $SITE;

        $recipients = get_admins();
        if (empty($recipients)) {
            return false;
        }

        $settingurl = new \moodle_url('/admin/search.php', ['query' => 'enablemobilewebservice']);
        $subject = get_string('mobileservicesoffsubject', 'tool_mobile');
        $renderable = new mobile_services_off_message(
            sitename: format_string($SITE->fullname, true, ['context' => \context_system::instance(), 'escape' => false]),
            buttonurl: $settingurl,
        );
        $renderer = $PAGE->get_renderer('tool_mobile');
        $context = $renderable->export_for_template($renderer);
        $fullmessage = $renderer->render_from_template('tool_mobile/mobile_services_off_message_textemail', $context);
        $fullmessagehtml = $renderer->render($renderable);
        // Same icon as the one displayed in the message, used by the Moodle app to display the notification.
        $customdata = ['notificationiconurl' => $context['iconurl']];

        foreach ($recipients as $recipient) {
            $message = new \core\message\message();
            $message->component = 'tool_mobile';
            $message->name = 'mobileservicesoff';
            $message->userfrom = \core_user::get_noreply_user();
            $message->userto = $recipient;
            $message->subject = $subject;
            $message->fullmessage = $fullmessage;
            $message->fullmessageformat = FORMAT_PLAIN;
            $message->fullmessagehtml = $fullmessagehtml;
            $message->smallmessage = $subject;
            $message->notification = 1;
            $message->contexturl = $settingurl->out(false);
            $message->contexturlname = get_string('enablemobilewebservice', 'admin');
            $message->customdata = $customdata;

            message_send($message);
        }

        return true;
    }
}
