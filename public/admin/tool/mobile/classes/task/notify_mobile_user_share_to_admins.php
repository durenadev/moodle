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
use tool_mobile\local\mobile_user_share;
use tool_mobile\output\mobile_user_share_message;

/**
 * Scheduled task to notify admins when the share of active users that use the Moodle app exceeds the threshold.
 *
 * @package    tool_mobile
 * @copyright  2026 Daniel Ureña <daniel.urena@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class notify_mobile_user_share_to_admins extends \core\task\scheduled_task {
    /** @var string[] Plans receiving this notification. */
    private const NOTIFIED_PLANS = ['free', 'pro'];

    /**
     * Return the task name.
     *
     * @return string
     */
    public function get_name() {
        return get_string('tasknotifymobileusersharetoadmins', 'tool_mobile');
    }

    /**
     * Execute the task.
     */
    public function execute() {
        global $CFG;

        mtrace('tool_mobile: Running scheduled mobile user share check task...');
        if (empty($CFG->enablemobilewebservice)) {
            mtrace('tool_mobile: task not running, mobile app is not enabled.');
            return;
        }

        $usage = mobile_user_share::get_usage();
        $usagedescription = $usage['percentage'] . '% of active users ('
            . $usage['appusers'] . ' of ' . $usage['activeusers'] . ')';
        if (!mobile_user_share::exceeds_threshold($usage)) {
            mtrace('tool_mobile: ' . $usagedescription . ' use the Moodle app, no notification sent to admins.');
            return;
        }

        if (mobile_user_share::notified_recently()) {
            $lastnotified = (int) get_config('tool_mobile', 'mobileusersharelastnotified');
            mtrace('tool_mobile: admins already notified at ' . date('Y-m-d H:i:s', $lastnotified)
                . ', no mobile user share notification sent.');
            return;
        }

        $subscriptiondata = api::get_subscription_information(true);
        $plan = api::get_normalized_plan($subscriptiondata, false);
        if ($plan === null) {
            mtrace('tool_mobile: no plan information available, no notification sent to admins.');
            return;
        }

        if (!in_array($plan, self::NOTIFIED_PLANS)) {
            mtrace('tool_mobile: the current plan is ' . $plan . ', no mobile user share notification sent.');
            return;
        }

        if (!self::notify_admins($usage)) {
            mtrace('tool_mobile: failed to notify admins about the mobile user share.');
            return;
        }

        set_config('mobileusersharelastnotified', di::get(clock::class)->time(), 'tool_mobile');
        mtrace('tool_mobile: mobile user share notification sent to admins (' . $usagedescription . ').');
    }

    /**
     * Notify all site admins about the share of active users that use the Moodle app.
     *
     * @param array{activeusers: int, appusers: int, percentage: int} $usage Moodle app usage.
     * @return bool True if at least one message was attempted.
     */
    protected static function notify_admins(array $usage): bool {
        global $PAGE, $SITE;

        $recipients = get_admins();
        if (empty($recipients)) {
            return false;
        }

        $subscriptionurl = new \moodle_url('/admin/tool/mobile/subscription.php');
        $subject = get_string('mobileusersharesubject', 'tool_mobile', $usage['percentage']);
        $renderable = new mobile_user_share_message(
            percentage: $usage['percentage'],
            appusers: $usage['appusers'],
            sitename: format_string($SITE->fullname, true, ['context' => \context_system::instance(), 'escape' => false]),
            buttonurl: $subscriptionurl,
        );
        $renderer = $PAGE->get_renderer('tool_mobile');
        $context = $renderable->export_for_template($renderer);
        $fullmessage = $renderer->render_from_template('tool_mobile/mobile_user_share_message_textemail', $context);
        $fullmessagehtml = $renderer->render($renderable);

        foreach ($recipients as $recipient) {
            $message = new \core\message\message();
            $message->component = 'tool_mobile';
            $message->name = 'mobileusershare';
            $message->userfrom = \core_user::get_noreply_user();
            $message->userto = $recipient;
            $message->subject = $subject;
            $message->fullmessage = $fullmessage;
            $message->fullmessageformat = FORMAT_PLAIN;
            $message->fullmessagehtml = $fullmessagehtml;
            $message->smallmessage = $subject;
            $message->notification = 1;
            $message->contexturl = $subscriptionurl->out(false);
            $message->contexturlname = get_string('mobileappsubscription', 'tool_mobile');

            message_send($message);
        }

        return true;
    }
}
