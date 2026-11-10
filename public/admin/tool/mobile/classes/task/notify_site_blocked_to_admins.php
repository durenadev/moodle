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
use tool_mobile\api;
use tool_mobile\output\site_blocked_message;

/**
 * Scheduled task to notify admins when the site is blocked from the standard Moodle app because of its hosting provider.
 *
 * @package    tool_mobile
 * @copyright  2026 Daniel Ureña <daniel.urena@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class notify_site_blocked_to_admins extends \core\task\scheduled_task {
    /** @var int Minimum time between two notifications while the site stays blocked. */
    public const NOTIFICATION_MIN_INTERVAL = YEARSECS;

    /** @var string Documentation page explaining why the site is blocked. */
    public const DOCS_PAGE = 'Moodle_app_plans';

    /**
     * Return the task name.
     *
     * @return string
     */
    public function get_name() {
        return get_string('tasknotifysiteblockedtoadmins', 'tool_mobile');
    }

    /**
     * Execute the task.
     */
    public function execute() {
        global $CFG;

        mtrace('tool_mobile: Running scheduled site blocked check task...');
        if (empty($CFG->enablemobilewebservice)) {
            mtrace('tool_mobile: task not running, mobile app is not enabled.');
            return;
        }

        $subscriptiondata = api::get_subscription_information(true);
        if ($subscriptiondata === null) {
            mtrace('tool_mobile: no subscription information available, no notification sent to admins.');
            return;
        }

        if (!api::is_site_blocked($subscriptiondata)) {
            // Admins are notified again as soon as the site is blocked in the future.
            unset_config('siteblockedlastnotified', 'tool_mobile');
            mtrace('tool_mobile: site is not blocked, no notification sent to admins.');
            return;
        }

        $now = di::get(clock::class)->time();
        $lastnotified = (int) get_config('tool_mobile', 'siteblockedlastnotified');
        if ($lastnotified > 0 && $now < $lastnotified + self::NOTIFICATION_MIN_INTERVAL) {
            mtrace('tool_mobile: admins already notified at ' . date('Y-m-d H:i:s', $lastnotified)
                . ', no site blocked notification sent.');
            return;
        }

        if (!self::notify_admins()) {
            mtrace('tool_mobile: failed to notify admins about the site being blocked.');
            return;
        }

        set_config('siteblockedlastnotified', $now, 'tool_mobile');
        mtrace('tool_mobile: site blocked notification sent to admins.');
    }

    /**
     * Notify all site admins that the site is blocked from the standard Moodle app.
     *
     * @return bool True if at least one message was attempted.
     */
    protected static function notify_admins(): bool {
        global $PAGE, $SITE;

        $recipients = get_admins();
        if (empty($recipients)) {
            return false;
        }

        $docsurl = new \moodle_url(get_docs_url(self::DOCS_PAGE));
        $subject = get_string('siteblockedsubject', 'tool_mobile');
        $renderable = new site_blocked_message(
            sitename: format_string($SITE->fullname, true, ['context' => \context_system::instance(), 'escape' => false]),
            buttonurl: $docsurl,
        );
        $renderer = $PAGE->get_renderer('tool_mobile');
        $context = $renderable->export_for_template($renderer);
        $fullmessage = $renderer->render_from_template('tool_mobile/site_blocked_message_textemail', $context);
        $fullmessagehtml = $renderer->render($renderable);
        // Same icon as the one displayed in the message, used by the Moodle app to display the notification.
        $customdata = ['notificationiconurl' => $context['iconurl']];

        foreach ($recipients as $recipient) {
            $message = new \core\message\message();
            $message->component = 'tool_mobile';
            $message->name = 'siteblocked';
            $message->userfrom = \core_user::get_noreply_user();
            $message->userto = $recipient;
            $message->subject = $subject;
            $message->fullmessage = $fullmessage;
            $message->fullmessageformat = FORMAT_PLAIN;
            $message->fullmessagehtml = $fullmessagehtml;
            $message->smallmessage = $subject;
            $message->notification = 1;
            $message->contexturl = $docsurl->out(false);
            $message->contexturlname = get_string('learnmore', 'tool_mobile');
            $message->customdata = $customdata;

            message_send($message);
        }

        return true;
    }
}
