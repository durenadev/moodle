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

namespace core_external;

/**
 * Tests for the web services event observers.
 *
 * WS_SERVER is always false in PHPUnit, so the access time logic is tested through
 * {@see observer::log_access_time()}. Events are triggered with the library functions used by the
 * web services (profile_view(), page_view()...) because require_login() updates the access times
 * itself when WS_SERVER is false.
 *
 * @package    core_external
 * @copyright  2026 Daniel Ureña <daniel.urena@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(observer::class)]
final class observer_test extends \advanced_testcase {
    #[\Override]
    public static function setUpBeforeClass(): void {
        global $CFG;
        require_once($CFG->dirroot . '/user/profile/lib.php');
        require_once($CFG->dirroot . '/mod/page/lib.php');
        parent::setUpBeforeClass();
    }

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        // User and course ids are reused between tests, so the access logged in a previous test must be forgotten.
        observer::reset_caches();
    }

    /**
     * Run a callable and return the events it triggered.
     *
     * @param callable $callable
     * @return \core\event\base[]
     */
    private function get_triggered_events(callable $callable): array {
        $sink = $this->redirectEvents();
        $callable();
        $events = $sink->get_events();
        $sink->close();
        $this->assertNotEmpty($events);
        return $events;
    }

    /**
     * Trigger the events of viewing a user profile at site level, as core_user_view_user_profile does.
     *
     * @param \stdClass $user
     */
    private function view_profile(\stdClass $user): void {
        profile_view($user, \context_user::instance($user->id));
    }

    /**
     * Trigger the events of viewing a page activity, as mod_page_view_page does.
     *
     * @param \stdClass $page
     */
    private function view_page(\stdClass $page): void {
        [$course, $cm] = get_course_and_cm_from_instance($page, 'page');
        page_view($page, $course, $cm, \context_module::instance($cm->id));
    }

    /**
     * Test that the observer is registered for all events, after transaction commit.
     */
    public function test_observer_registered(): void {
        $observers = \core\event\manager::get_all_observers();
        $found = array_filter(
            $observers['\core\event\base'],
            fn($o) => $o->callable === '\core_external\observer::update_access_time',
        );
        $this->assertCount(1, $found);
        $this->assertFalse(reset($found)->internal);
    }

    /**
     * Test that access times are not updated outside web service requests (e.g. AJAX).
     */
    public function test_update_access_time_not_ws_request(): void {
        global $DB;
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        // Observers are dispatched normally, but WS_SERVER is false in PHPUnit.
        $this->view_profile($user);

        $this->assertEquals(0, $DB->get_field('user', 'lastaccess', ['id' => $user->id]));
    }

    /**
     * Test that a site level action updates the site access time only.
     */
    public function test_log_access_time_site(): void {
        global $DB;
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $events = $this->get_triggered_events(fn() => $this->view_profile($user));
        $this->assertEquals(0, $DB->get_field('user', 'lastaccess', ['id' => $user->id]));

        $timebefore = time();
        foreach ($events as $event) {
            observer::log_access_time($event);
        }

        $this->assertGreaterThanOrEqual($timebefore, $DB->get_field('user', 'lastaccess', ['id' => $user->id]));
        $this->assertFalse($DB->record_exists('user_lastaccess', ['userid' => $user->id]));
    }

    /**
     * Test that an activity action updates the site and course access times.
     */
    public function test_log_access_time_course(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $user = $this->getDataGenerator()->create_and_enrol($course);
        $this->setUser($user);

        $events = $this->get_triggered_events(fn() => $this->view_page($page));
        $this->assertEquals(0, $DB->get_field('user', 'lastaccess', ['id' => $user->id]));
        $this->assertFalse($DB->record_exists('user_lastaccess', ['userid' => $user->id]));

        $timebefore = time();
        foreach ($events as $event) {
            observer::log_access_time($event);
        }

        $this->assertGreaterThanOrEqual($timebefore, $DB->get_field('user', 'lastaccess', ['id' => $user->id]));
        $this->assertGreaterThanOrEqual(
            $timebefore,
            $DB->get_field('user_lastaccess', 'timeaccess', ['userid' => $user->id, 'courseid' => $course->id]),
        );
    }

    /**
     * Test that events triggered by another user do not update the current user access times.
     */
    public function test_log_access_time_other_user(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $user1 = $this->getDataGenerator()->create_and_enrol($course);
        $user2 = $this->getDataGenerator()->create_and_enrol($course);

        $this->setUser($user1);
        $events = $this->get_triggered_events(fn() => $this->view_page($page));

        $this->setUser($user2);
        foreach ($events as $event) {
            observer::log_access_time($event);
        }

        $this->assertEquals(0, $DB->get_field('user', 'lastaccess', ['id' => $user1->id]));
        $this->assertEquals(0, $DB->get_field('user', 'lastaccess', ['id' => $user2->id]));
        $this->assertEquals(0, $DB->count_records('user_lastaccess'));
    }

    /**
     * Test that the event triggered by every web service request does not update the access times.
     */
    public function test_log_access_time_webservice_function_called(): void {
        global $DB;
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $event = \core\event\webservice_function_called::create([
            'other' => ['function' => 'core_webservice_get_site_info'],
        ]);
        $this->assertEquals($user->id, $event->userid);

        observer::log_access_time($event);

        $this->assertEquals(0, $DB->get_field('user', 'lastaccess', ['id' => $user->id]));
        $this->assertFalse($DB->record_exists('user_lastaccess', ['userid' => $user->id]));
    }

    /**
     * Test that the access to a course is logged only once per request.
     */
    public function test_log_access_time_cached(): void {
        global $DB;
        $this->resetAfterTest();

        $course1 = $this->getDataGenerator()->create_course();
        $course2 = $this->getDataGenerator()->create_course();
        $page1 = $this->getDataGenerator()->create_module('page', ['course' => $course1->id]);
        $page2 = $this->getDataGenerator()->create_module('page', ['course' => $course2->id]);
        $user = $this->getDataGenerator()->create_and_enrol($course1);
        $this->getDataGenerator()->enrol_user($user->id, $course2->id);
        $this->setUser($user);

        // A recent course access (e.g. a concurrent request) makes user_accesstime_log() query the database
        // on every call, because it does not cache it in $USER->currentcourseaccess.
        $timeaccess = time();
        $DB->insert_record('user_lastaccess', ['userid' => $user->id, 'courseid' => $course1->id, 'timeaccess' => $timeaccess]);

        $events1 = $this->get_triggered_events(fn() => $this->view_page($page1));
        $events2 = $this->get_triggered_events(fn() => $this->view_page($page2));

        // First events for the course query the database.
        $reads = $DB->perf_get_reads();
        foreach ($events1 as $event) {
            observer::log_access_time($event);
        }
        $this->assertGreaterThan($reads, $DB->perf_get_reads());

        // Next events for the same course are skipped.
        $reads = $DB->perf_get_reads();
        foreach ($events1 as $event) {
            observer::log_access_time($event);
        }
        $this->assertEquals($reads, $DB->perf_get_reads());

        // Events for another course are still logged.
        foreach ($events2 as $event) {
            observer::log_access_time($event);
        }
        $this->assertTrue($DB->record_exists('user_lastaccess', ['userid' => $user->id, 'courseid' => $course2->id]));

        // A new request checks the course access again.
        observer::reset_caches();
        $reads = $DB->perf_get_reads();
        observer::log_access_time(reset($events1));
        $this->assertGreaterThan($reads, $DB->perf_get_reads());
    }

    /**
     * Test that access times are not updated for guest users.
     */
    public function test_log_access_time_guest(): void {
        global $DB;
        $this->resetAfterTest();

        $this->setGuestUser();
        $guest = guest_user();
        $lastaccess = $DB->get_field('user', 'lastaccess', ['id' => $guest->id]);

        $events = $this->get_triggered_events(fn() => $this->view_profile($guest));
        foreach ($events as $event) {
            observer::log_access_time($event);
        }

        $this->assertEquals($lastaccess, $DB->get_field('user', 'lastaccess', ['id' => $guest->id]));
    }
}
