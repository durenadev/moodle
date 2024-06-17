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

namespace core_badges\external;

use externallib_advanced_testcase;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/webservice/tests/helpers.php');
require_once($CFG->libdir . '/badgeslib.php');

/**
 * Tests for external function get_badge.
 *
 * @package    core_badges
 * @category   external
 * @copyright  2024 Daniel Ureña <durenadev@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 4.3
 * @coversDefaultClass \core_badges\external\get_badge
 */
final class get_badge_test extends externallib_advanced_testcase {
    /**
     * Prepare the test.
     *
     * @return array
     */
    private function prepare_test_data(): array {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        // Setup test data.
        $course = $this->getDataGenerator()->create_course();

        // Mock up a site badge.
        $now = time();
        $badge = new \stdClass();
        $badge->id = null;
        $badge->name = "Test badge site";
        $badge->description  = "Testing badges site";
        $badge->timecreated  = $now;
        $badge->timemodified = $now;
        $badge->usercreated  = 2;
        $badge->usermodified = 2;
        $badge->expiredate    = null;
        $badge->expireperiod  = null;
        $badge->type = BADGE_TYPE_SITE;
        $badge->courseid = null;
        $badge->messagesubject = "Test message subject for badge";
        $badge->message = "Test message body for badge";
        $badge->attachment = 1;
        $badge->notification = 0;
        $badge->status = BADGE_STATUS_ACTIVE;
        $badge->version = '1';
        $badge->language = 'en';
        $badge->imageauthorname = 'Image author';
        $badge->imageauthoremail = 'imageauthor@example.com';
        $badge->imageauthorurl = 'http://image-author-url.domain.co.nz';
        $badge->imagecaption = 'Caption';

        $badgeid   = $DB->insert_record('badge', $badge, true);
        $badge->id = $badgeid;

        $context           = \context_system::instance();
        $badge->badgeurl   = \moodle_url::make_webservice_pluginfile_url(
            $context->id,
            'badges',
            'badgeimage',
            $badge->id,
            '/',
            'f3'
        )->out(false);
        $badge->status = BADGE_STATUS_ACTIVE_LOCKED;

        $sitebadges[] = (array) $badge;

        return [
            'sitebadges'   => $sitebadges,
        ];
    }

    /**
     * Test get user badge by hash.
     * These are a basic tests since the badges_get_my_user_badges used by the external function already has unit tests.
     * @covers ::execute
     */
    public function test_get_badge(): void {
        $data = $this->prepare_test_data();

        set_config('enablebadges', 0);
        // Sitebadge 1.
        $result = get_badge::execute($data['sitebadges'][0]['id']);
        $result = \core_external\external_api::clean_returnvalue(get_badge::execute_returns(), $result);
        $this->assertEmpty($result['badge']);
        $this->assertNotEmpty($result['warnings']);
        $this->assertEquals('badgesdisabled', $result['warnings'][0]['warningcode']);

        set_config('enablebadges', 1);
        // Sitebadge 1.
        $result = get_badge::execute($data['sitebadges'][0]['id']);
        $result = \core_external\external_api::clean_returnvalue(get_badge::execute_returns(), $result);
        $this->assertEquals($data['sitebadges'][0]['name'], $result['badge'][0]['name']);
        $this->assertEmpty($result['warnings']);

        // Wrong hash.
        $result = get_badge::execute(34);
        $result = \core_external\external_api::clean_returnvalue(get_badge::execute_returns(), $result);
        $this->assertEmpty($result['badge']);
        $this->assertNotEmpty($result['warnings']);
        $this->assertEquals('badgeawardnotfound', $result['warnings'][0]['warningcode']);
    }
}
