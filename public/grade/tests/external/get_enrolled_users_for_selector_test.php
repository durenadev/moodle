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

/**
 * Unit tests for the core_grades\external\get_enrolled_users_for_selector.
 *
 * @package    core_grades
 * @category   external
 * @copyright  2025 Daniel Ureña
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core_grades\external;

use core_grades\external\get_enrolled_users_for_selector;
use core_external\external_api;
use core_user;

/**
 * Unit tests for the core_grades\external\get_enrolled_users_for_selector.
 *
 * @package    core_grades
 * @category   external
 * @copyright  2025 Daniel Ureña
 * @covers     \core_grades\external\get_enrolled_users_for_selector
 */
final class get_enrolled_users_for_selector_test extends \core_external\tests\externallib_testcase {
    public function test_get_enrolled_users_for_selector(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        // Create course and users.
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        // Create an activity to ensure grade items exist.
        $assign = $generator->create_module('assign', ['course' => $course->id]);
        $user1 = $generator->create_user([
            'firstname' => 'Ana',
            'lastname' => 'García',
        ]);
        $user2 = $generator->create_user([
            'firstname' => 'Luis',
            'lastname' => 'Martínez',
        ]);

        // Enrol users in course.
        $generator->enrol_user($user1->id, $course->id);
        $generator->enrol_user($user2->id, $course->id);

        // Call the external function.
        $result = get_enrolled_users_for_selector::execute($course->id, 0);
        $result = external_api::clean_returnvalue(get_enrolled_users_for_selector::execute_returns(), $result);

        // Assert users are returned.
        $userids = array_map(function ($u) {
            return $u['id'];
        }, $result['users']);
        $this->assertCount(2, $userids);

        // Assert some fields and compare initials.
        foreach ($result['users'] as $user) {
            $this->assertArrayHasKey('fullname', $user);
            $this->assertArrayHasKey('initials', $user);
            $this->assertArrayHasKey('profileimageurl', $user);
            // Testing initials.
            if ($user['id'] == $user1->id) {
                $expectedinitials = core_user::get_initials($user1);
                $this->assertEquals($expectedinitials, $user['initials']);
            }
            if ($user['id'] == $user2->id) {
                $expectedinitials = core_user::get_initials($user2);
                $this->assertEquals($expectedinitials, $user['initials']);
            }
        }

        // Assert no warnings.
        $this->assertEmpty($result['warnings']);
    }
}
