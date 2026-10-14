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

namespace core_user\external;

use core_external\external_api;

/**
 * Tests for cancelling pending email changes.
 *
 * @package    core_user
 * @copyright  2026 Daniel Ureña <daniel.urena@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(cancel_pending_email_change::class)]
final class cancel_pending_email_change_test extends \advanced_testcase {
    public function test_cancel_pending_change(): void {
        global $DB;
        $this->resetAfterTest();
        set_config('emailchangeconfirmation', 1);
        $user = $this->getDataGenerator()->create_user(['email' => 'confirmed@example.com']);
        $otheruser = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        set_user_preference('newemail', 'pending@example.com', $user->id);
        set_user_preference('newemailattemptsleft', 3, $user->id);
        $key = create_user_key('core_user/email_change', $user->id);
        set_user_preference('newemail', 'otherpending@example.com', $otheruser->id);
        $otherkey = create_user_key('core_user/email_change', $otheruser->id);

        $result = external_api::clean_returnvalue(
            cancel_pending_email_change::execute_returns(),
            cancel_pending_email_change::execute(),
        );

        $this->assertSame([
            'status' => true,
            'email' => 'confirmed@example.com',
            'emailchangepending' => false,
            'warnings' => [],
        ], $result);
        $this->assertNull(get_user_preferences('newemail', null, $user->id));
        $this->assertNull(get_user_preferences('newemailattemptsleft', null, $user->id));
        $this->assertFalse($DB->record_exists('user_private_key', ['value' => $key]));
        $this->assertSame('confirmed@example.com', $DB->get_field('user', 'email', ['id' => $user->id]));
        $this->assertSame('otherpending@example.com', get_user_preferences('newemail', null, $otheruser->id));
        $this->assertTrue($DB->record_exists('user_private_key', ['value' => $otherkey]));

        $settings = get_edit_profile_settings::execute();
        $this->assertFalse($settings['emailchangepending']);
        $this->assertArrayNotHasKey('pendingemail', $settings);
        $fields = array_column($settings['fields'], null, 'name');
        $this->assertFalse($fields['email']['locked']);
        $this->assertSame($result, cancel_pending_email_change::execute());
    }

    public function test_cancel_without_pending_change(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $result = cancel_pending_email_change::execute();
        $this->assertTrue($result['status']);
        $this->assertFalse($result['emailchangepending']);
        $this->assertSame($user->email, $result['email']);
        $this->assertEmpty($result['warnings']);
    }

    public function test_permission_required(): void {
        global $CFG;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $context = \context_system::instance();
        assign_capability('moodle/user:editownprofile', CAP_PROHIBIT, $CFG->defaultuserroleid, $context->id, true);
        $this->setUser($user);
        $this->assertFalse(has_capability('moodle/user:editownprofile', $context));
        set_user_preference('newemail', 'pending@example.com', $user->id);

        try {
            cancel_pending_email_change::execute();
            $this->fail('Cancellation must require permission to edit the profile.');
        } catch (\moodle_exception $exception) {
            $this->assertSame('cannotedityourprofile', $exception->errorcode);
        }
        $this->assertSame('pending@example.com', get_user_preferences('newemail', null, $user->id));
    }

    public function test_guest_cannot_cancel(): void {
        $this->resetAfterTest();
        $this->setGuestUser();
        $this->expectException(\moodle_exception::class);
        cancel_pending_email_change::execute();
    }
}
