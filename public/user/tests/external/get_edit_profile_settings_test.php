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
 * Tests for the get_edit_profile_settings web service.
 *
 * @package    core_user
 * @copyright  2026 Daniel Ureña <daniel.urena@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \core_user\external\get_edit_profile_settings
 * @covers     \core_user\local\edit_profile_helper
 */
final class get_edit_profile_settings_test extends \advanced_testcase {
    /**
     * Get the settings of the current user.
     *
     * @return array
     */
    protected function get_settings(): array {
        $result = get_edit_profile_settings::execute();
        return external_api::clean_returnvalue(get_edit_profile_settings::execute_returns(), $result);
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

    public function test_get_settings(): void {
        $this->resetAfterTest();
        $this->getDataGenerator()->create_custom_profile_field([
            'datatype' => 'text',
            'shortname' => 'editable',
            'name' => 'Editable field',
        ]);
        $this->getDataGenerator()->create_custom_profile_field([
            'datatype' => 'text',
            'shortname' => 'lockedfield',
            'name' => 'Locked field',
            'locked' => 1,
        ]);
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $result = $this->get_settings();
        $this->assertContains('firstname', $result['namefields']);
        $fields = array_column($result['fields'], null, 'name');
        $this->assertFalse($fields['email']['locked']);
        $this->assertTrue($fields['email']['required']);
        $custom = array_column($result['profilefields'], null, 'shortname');
        $this->assertEquals(0, $custom['editable']['locked']);
        $this->assertEquals(1, $custom['lockedfield']['locked']);
        $this->assertFalse($result['emailchangepending']);
    }

    public function test_get_settings_auth_locked_field(): void {
        $this->resetAfterTest();
        set_config('field_lock_city', 'locked', 'auth_manual');
        $this->setUser($this->getDataGenerator()->create_user());

        $fields = array_column($this->get_settings()['fields'], null, 'name');
        $this->assertTrue($fields['city']['locked']);
        $this->assertFalse($fields['phone1']['locked']);
    }

    public function test_guest_cannot_get_settings(): void {
        $this->resetAfterTest();
        $this->setGuestUser();
        $this->expectException(\moodle_exception::class);
        get_edit_profile_settings::execute();
    }

    public function test_auth_without_profile_editing(): void {
        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());
        $this->mock_auth_without_profile_editing();
        $this->expectException(\moodle_exception::class);
        get_edit_profile_settings::execute();
    }

    public function test_forced_timezone_is_locked(): void {
        $this->resetAfterTest();
        set_config('forcetimezone', 'Australia/Perth');
        $this->setUser($this->getDataGenerator()->create_user());

        $fields = array_column($this->get_settings()['fields'], null, 'name');
        $this->assertTrue($fields['timezone']['locked']);
    }
}
