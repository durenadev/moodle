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
use tool_mobile\local\current_setup_detection;
use tool_mobile\output\current_setup_message;

/**
 * Scheduled task to notify admins about the Moodle app features related to their current site setup.
 *
 * @package    tool_mobile
 * @copyright  2026 Daniel Ureña <daniel.urena@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class notify_current_setup_to_admins extends \core\task\scheduled_task {
    /** @var string[] Plans receiving this notification. */
    private const NOTIFIED_PLANS = ['free', 'pro'];

    /**
     * Return the task name.
     *
     * @return string
     */
    public function get_name() {
        return get_string('tasknotifycurrentsetuptoadmins', 'tool_mobile');
    }

    /**
     * Execute the task.
     */
    public function execute() {
        global $CFG;

        mtrace('tool_mobile: Running scheduled current setup check task...');
        if (empty($CFG->enablemobilewebservice)) {
            mtrace('tool_mobile: task not running, mobile app is not enabled.');
            return;
        }

        $detectedtime = (int) get_config('tool_mobile', 'currentsetupdetectedtime');
        if ($detectedtime <= 0) {
            mtrace('tool_mobile: no pending setup changes, no notification sent to admins.');
            return;
        }

        $now = di::get(clock::class)->time();
        $notifytime = $detectedtime + current_setup_detection::NOTIFICATION_DELAY;
        if ($now < $notifytime) {
            mtrace('tool_mobile: setup change detected at ' . date('Y-m-d H:i:s', $detectedtime)
                . ', admins will be notified from ' . date('Y-m-d H:i:s', $notifytime) . '.');
            return;
        }

        $setup = current_setup_detection::get_detected_setup();
        if (empty($setup)) {
            self::cancel_detection('no logo or Matomo configured');
            return;
        }

        $lastnotified = (int) get_config('tool_mobile', 'currentsetuplastnotified');
        if ($lastnotified > 0 && $now < $lastnotified + current_setup_detection::NOTIFICATION_MIN_INTERVAL) {
            self::cancel_detection('admins already notified at ' . date('Y-m-d H:i:s', $lastnotified));
            return;
        }

        // The detection is kept so it can be notified once the plan information is available.
        $subscriptiondata = api::get_subscription_information(true);
        $plan = api::get_normalized_plan($subscriptiondata, false);
        if ($plan === null) {
            mtrace('tool_mobile: no plan information available, no notification sent to admins.');
            return;
        }

        if (!in_array($plan, self::NOTIFIED_PLANS)) {
            self::cancel_detection('the current plan is ' . $plan);
            return;
        }

        if (!self::notify_admins($subscriptiondata, $setup)) {
            mtrace('tool_mobile: failed to notify admins about the current setup.');
            return;
        }

        set_config('currentsetuplastnotified', $now, 'tool_mobile');
        unset_config('currentsetupdetectedtime', 'tool_mobile');
        mtrace('tool_mobile: current setup notification sent to admins (' . implode(', ', $setup) . ').');
    }

    /**
     * Cancel the pending detection, so it is not notified.
     *
     * @param string $reason Reason to display in the task log.
     */
    protected static function cancel_detection(string $reason): void {
        unset_config('currentsetupdetectedtime', 'tool_mobile');
        mtrace('tool_mobile: ' . $reason . ', current setup notification cancelled.');
    }

    /**
     * Notify all site admins about the Moodle app features related to the current setup.
     *
     * @param array $subscriptiondata Subscription information returned by the Apps Portal API.
     * @param string[] $setup Detected setup, list of 'matomo' and 'logo'.
     * @return bool True if at least one message was attempted.
     */
    protected static function notify_admins(array $subscriptiondata, array $setup): bool {
        global $PAGE, $SITE;

        $recipients = get_admins();
        if (empty($recipients)) {
            return false;
        }

        $subscriptionurl = new \moodle_url('/admin/tool/mobile/subscription.php');
        $subject = get_string('currentsetupsubject', 'tool_mobile');
        $renderable = new current_setup_message(
            setup: $setup,
            sitename: format_string($SITE->fullname, true, ['context' => \context_system::instance(), 'escape' => false]),
            planname: $subscriptiondata['subscription']['name'] ?? $subscriptiondata['subscription']['plan'],
            coursedownloadslimit: api::get_course_downloads_limit($subscriptiondata),
            buttonurl: $subscriptionurl,
        );
        $renderer = $PAGE->get_renderer('tool_mobile');
        $context = $renderable->export_for_template($renderer);
        $fullmessage = $renderer->render_from_template('tool_mobile/current_setup_message_textemail', $context);
        $fullmessagehtml = $renderer->render($renderable);

        foreach ($recipients as $recipient) {
            $message = new \core\message\message();
            $message->component = 'tool_mobile';
            $message->name = 'currentsetup';
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
