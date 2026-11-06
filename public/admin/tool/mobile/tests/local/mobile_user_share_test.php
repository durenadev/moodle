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

namespace tool_mobile\local;

/**
 * Tests for the share of active users that use the Moodle app.
 *
 * @package    tool_mobile
 * @copyright  2026 Daniel Ureña <daniel.urena@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(mobile_user_share::class)]
final class mobile_user_share_test extends \advanced_testcase {
    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Test the active users and Moodle app users counted in the current month.
     */
    public function test_get_usage(): void {
        global $CFG, $DB;

        $timezone = \core_date::get_server_timezone_object();
        $this->mock_clock_with_frozen_datetime(new \DateTimeImmutable('2026-11-15 12:00:00', $timezone));
        // Only the accesses since the beginning of the current month are counted.
        $recent = (new \DateTimeImmutable('2026-11-01 00:00:00', $timezone))->getTimestamp();
        $old = $recent - 1;
        $generator = $this->getDataGenerator();
        $DB->set_field('user', 'lastaccess', $recent, ['id' => $CFG->siteguest]);

        // Active users only on the site.
        $generator->create_user(['lastaccess' => $recent]);
        $generator->create_user(['lastaccess' => $old]);

        // Active users of the Moodle app, with or without a recent site access, counted once with several tokens.
        $this->create_app_token($generator->create_user(['lastaccess' => $old])->id, $recent);
        $user = $generator->create_user(['lastaccess' => $recent]);
        $this->create_app_token($user->id, $recent);
        $this->create_app_token($user->id, $old);

        // Inactive users of the Moodle app, or using other services.
        $this->create_app_token($generator->create_user(['lastaccess' => $old])->id, $old);
        $this->create_app_token($generator->create_user(['suspended' => 1])->id, $recent);
        $service = $this->getDataGenerator()->get_plugin_generator('core_webservice')->create_service([
            'name' => 'Test service',
            'shortname' => 'testservice',
        ]);
        $this->create_app_token($generator->create_user()->id, $recent, $service);

        $this->assertSame(['activeusers' => 3, 'appusers' => 2, 'percentage' => 66], mobile_user_share::get_usage());
    }

    /**
     * Test the usage without active users.
     */
    public function test_get_usage_without_active_users(): void {
        $this->mock_clock_with_frozen();

        $this->assertSame(['activeusers' => 0, 'appusers' => 0, 'percentage' => 0], mobile_user_share::get_usage());
    }

    /**
     * Test the threshold and the minimum number of Moodle app users.
     *
     * @param int $activeusers Active users.
     * @param int $appusers Active users of the Moodle app.
     * @param bool $expected Whether the threshold is exceeded.
     */
    #[\PHPUnit\Framework\Attributes\TestWith([100, 51, true])]
    #[\PHPUnit\Framework\Attributes\TestWith([99, 50, true])]
    #[\PHPUnit\Framework\Attributes\TestWith([100, 50, false])]
    #[\PHPUnit\Framework\Attributes\TestWith([49, 49, false])]
    public function test_exceeds_threshold(int $activeusers, int $appusers, bool $expected): void {
        $usage = [
            'activeusers' => $activeusers,
            'appusers' => $appusers,
            'percentage' => (int) floor($appusers * 100 / $activeusers),
        ];

        $this->assertSame($expected, mobile_user_share::exceeds_threshold($usage));
    }

    /**
     * Test whether admins were notified within the minimum time between two notifications.
     */
    public function test_notified_recently(): void {
        $clock = $this->mock_clock_with_frozen();
        $this->assertFalse(mobile_user_share::notified_recently());

        set_config('mobileusersharelastnotified', $clock->time(), 'tool_mobile');
        $this->assertTrue(mobile_user_share::notified_recently());

        $clock->bump(mobile_user_share::NOTIFICATION_MIN_INTERVAL - 1);
        $this->assertTrue(mobile_user_share::notified_recently());

        $clock->bump();
        $this->assertFalse(mobile_user_share::notified_recently());
    }

    /**
     * Create a token for the user, last used at the given time.
     *
     * @param int $userid User id.
     * @param int $lastaccess Last time the token was used.
     * @param ?\stdClass $service Service of the token, the official mobile service by default.
     */
    private function create_app_token(int $userid, int $lastaccess, ?\stdClass $service = null): void {
        global $DB;

        $service ??= $DB->get_record('external_services', ['shortname' => MOODLE_OFFICIAL_MOBILE_SERVICE], '*', MUST_EXIST);
        $token = \core_external\util::generate_token(EXTERNAL_TOKEN_PERMANENT, $service, $userid, \context_system::instance());
        $DB->set_field('external_tokens', 'lastaccess', $lastaccess, ['token' => $token]);
    }
}
