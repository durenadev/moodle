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
 * Tests for the update_profile web service.
 *
 * @package    core_user
 * @copyright  2026 Daniel Ureña <daniel.urena@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \core_user\external\update_profile
 */
final class update_profile_test extends \advanced_testcase {
    /**
     * Update the profile of the current user.
     *
     * @param array $fields
     * @param array $customfields
     * @return array
     */
    protected function update(array $fields, array $customfields = []): array {
        $result = update_profile::execute($fields, $customfields);
        return external_api::clean_returnvalue(update_profile::execute_returns(), $result);
    }

    /**
     * Make the auth plugins report that they cannot edit the profile.
     */
    protected function mock_auth_without_profile_editing(): void {
        $plugin = $this->createMock(\auth_plugin_base::class);
        $plugin->method('can_edit_profile')->willReturn(false);
        $authentication = $this->createMock(\core\authentication::class);
        $authentication->method('get_plugin')->willReturn($plugin);
        \core\di::set(\core\authentication::class, $authentication);
    }

    public function test_update_profile(): void {
        global $DB;
        $this->resetAfterTest();
        $this->getDataGenerator()->create_custom_profile_field([
            'datatype' => 'text',
            'shortname' => 'hobby',
            'name' => 'Hobby',
        ]);
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $result = $this->update(
            [['name' => 'firstname', 'value' => 'Newname'], ['name' => 'city', 'value' => 'Perth']],
            [['name' => 'profile_field_hobby', 'value' => 'Chess']],
        );
        $this->assertTrue($result['status']);
        $this->assertEmpty($result['warnings']);
        $record = $DB->get_record('user', ['id' => $user->id]);
        $this->assertEquals('Newname', $record->firstname);
        $this->assertEquals('Perth', $record->city);
        $this->assertEquals('Chess', $DB->get_field('user_info_data', 'data', ['userid' => $user->id]));
    }

    public function test_update_profile_locked_and_invalid_fields(): void {
        global $DB;
        $this->resetAfterTest();
        set_config('field_lock_city', 'locked', 'auth_manual');
        $this->getDataGenerator()->create_custom_profile_field([
            'datatype' => 'text',
            'shortname' => 'lockedfield',
            'name' => 'Locked field',
            'locked' => 1,
        ]);
        $user = $this->getDataGenerator()->create_user(['city' => 'Sydney']);
        $this->setUser($user);

        $result = $this->update(
            [
                ['name' => 'city', 'value' => 'Perth'],
                ['name' => 'username', 'value' => 'other'],
                ['name' => 'firstname', 'value' => ''],
            ],
            [['name' => 'profile_field_lockedfield', 'value' => 'x']],
        );
        $this->assertFalse($result['status']);
        $codes = array_column($result['warnings'], 'warningcode', 'item');
        $this->assertEquals('fieldlocked', $codes['city']);
        $this->assertEquals('fieldnotallowed', $codes['username']);
        $this->assertEquals('required', $codes['firstname']);
        $this->assertEquals('fieldlocked', $codes['profile_field_lockedfield']);
        $this->assertEquals('Sydney', $DB->get_field('user', 'city', ['id' => $user->id]));
    }

    public function test_update_email_with_confirmation(): void {
        global $DB;
        $this->resetAfterTest();
        set_config('emailchangeconfirmation', 1);
        $user = $this->getDataGenerator()->create_user(['email' => 'old@example.com']);
        $this->setUser($user);

        $sink = $this->redirectEmails();
        $settings = external_api::clean_returnvalue(
            get_edit_profile_settings::execute_returns(),
            get_edit_profile_settings::execute(),
        );
        $this->assertFalse($settings['emailchangepending']);
        $fields = array_column($settings['fields'], null, 'name');
        $this->assertFalse($fields['email']['locked']);
        $result = $this->update([['name' => 'email', 'value' => 'new@example.com']]);
        $this->assertTrue($result['status']);
        $this->assertEquals('emailchangepending', $result['warnings'][0]['warningcode']);
        $this->assertCount(1, $sink->get_messages());
        $this->assertEquals('old@example.com', $DB->get_field('user', 'email', ['id' => $user->id]));

        $settings = external_api::clean_returnvalue(
            get_edit_profile_settings::execute_returns(),
            get_edit_profile_settings::execute(),
        );
        $this->assertTrue($settings['emailchangepending']);
        $this->assertEquals('new@example.com', $settings['pendingemail']);
        $fields = array_column($settings['fields'], null, 'name');
        $this->assertTrue($fields['email']['locked']);

        $result = $this->update([['name' => 'email', 'value' => 'replacement@example.com']]);
        $this->assertFalse($result['status']);
        $this->assertEquals('fieldlocked', $result['warnings'][0]['warningcode']);
        $this->assertEquals('profilenotupdated', $result['warnings'][1]['warningcode']);
        $this->assertEquals('new@example.com', get_user_preferences('newemail', null, $user->id));
        $this->assertEquals('old@example.com', $DB->get_field('user', 'email', ['id' => $user->id]));
        $this->assertCount(1, $sink->get_messages());
        $sink->close();
    }

    public function test_update_email_without_confirmation(): void {
        global $DB;
        $this->resetAfterTest();
        set_config('emailchangeconfirmation', 0);
        $user = $this->getDataGenerator()->create_user(['email' => 'old@example.com']);
        $this->setUser($user);

        $result = $this->update([['name' => 'email', 'value' => 'new@example.com']]);
        $this->assertTrue($result['status']);
        $this->assertEmpty($result['warnings']);
        $this->assertEquals('new@example.com', $DB->get_field('user', 'email', ['id' => $user->id]));
    }

    public function test_update_email_invalid_or_used(): void {
        $this->resetAfterTest();
        $this->getDataGenerator()->create_user(['email' => 'used@example.com']);
        $this->setUser($this->getDataGenerator()->create_user());

        $result = $this->update([['name' => 'email', 'value' => 'used@example.com']]);
        $this->assertFalse($result['status']);
        $this->assertEquals('emailexists', $result['warnings'][0]['warningcode']);

        $result = $this->update([['name' => 'email', 'value' => 'notanemail']]);
        $this->assertFalse($result['status']);
        $this->assertEquals('invalidemail', $result['warnings'][0]['warningcode']);
    }

    public function test_update_email_not_sent_does_not_throw(): void {
        global $DB;
        $this->resetAfterTest();
        set_config('emailchangeconfirmation', 1);
        $user = $this->getDataGenerator()->create_user(['email' => 'old@example.com']);
        $this->setUser($user);
        \core\di::get(\core\hook\manager::class)->phpunit_redirect_hook(
            \core\hook\email\before_email_to_user::class,
            function (\core\hook\email\before_email_to_user $hook): void {
                $hook->email->add_block_reason('Simulated email failure');
            },
        );

        $result = $this->update([['name' => 'city', 'value' => 'Perth'], ['name' => 'email', 'value' => 'new@example.com']]);
        $this->assertDebuggingCalled('email_to_user: blocked by hook subscriber: Simulated email failure', DEBUG_DEVELOPER);
        $this->assertTrue($result['status']);
        $this->assertEquals('emailnotsent', $result['warnings'][0]['warningcode']);
        $this->assertEquals('Perth', $DB->get_field('user', 'city', ['id' => $user->id]));
    }

    public function test_update_email_domain_restriction(): void {
        $this->resetAfterTest();
        set_config('verifychangedemail', 1);
        set_config('denyemailaddresses', 'blocked.example');
        $this->setUser($this->getDataGenerator()->create_user());

        $result = $this->update([['name' => 'email', 'value' => 'someone@blocked.example']]);
        $this->assertFalse($result['status']);
        $this->assertEquals('emailnotallowed', $result['warnings'][0]['warningcode']);
    }

    public function test_update_country_and_timezone_validation(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $result = $this->update([['name' => 'country', 'value' => 'ZZ'], ['name' => 'timezone', 'value' => 'Not/AZone']]);
        $this->assertFalse($result['status']);
        $codes = array_column($result['warnings'], 'warningcode', 'item');
        $this->assertEquals('invalidvalue', $codes['country']);
        $this->assertEquals('invalidvalue', $codes['timezone']);

        $result = $this->update([['name' => 'country', 'value' => 'AU'], ['name' => 'timezone', 'value' => 'Australia/Perth']]);
        $this->assertTrue($result['status']);
        $this->assertEquals('AU', $DB->get_field('user', 'country', ['id' => $user->id]));
    }

    public function test_update_forced_timezone_is_locked(): void {
        $this->resetAfterTest();
        set_config('forcetimezone', 'Australia/Perth');
        $this->setUser($this->getDataGenerator()->create_user());

        $result = $this->update([['name' => 'timezone', 'value' => 'Europe/Madrid']]);
        $this->assertFalse($result['status']);
        $this->assertEquals('fieldlocked', $result['warnings'][0]['warningcode']);
    }

    public function test_update_json_custom_field(): void {
        global $DB;
        $this->resetAfterTest();
        $this->getDataGenerator()->create_custom_profile_field([
            'datatype' => 'textarea',
            'shortname' => 'bio',
            'name' => 'Biography',
        ]);
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $value = json_encode(['text' => 'Hello', 'format' => FORMAT_HTML]);
        $result = $this->update([], [['name' => 'profile_field_bio', 'value' => $value]]);
        $this->assertTrue($result['status']);
        $this->assertEquals('Hello', $DB->get_field('user_info_data', 'data', ['userid' => $user->id]));
    }

    public function test_update_profile_auth_cannot_edit(): void {
        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());
        $this->mock_auth_without_profile_editing();
        $this->expectException(\moodle_exception::class);
        update_profile::execute([['name' => 'city', 'value' => 'x']]);
    }

    public function test_guest_cannot_update(): void {
        $this->resetAfterTest();
        $this->setGuestUser();
        $this->expectException(\moodle_exception::class);
        update_profile::execute([['name' => 'city', 'value' => 'x']]);
    }
}
