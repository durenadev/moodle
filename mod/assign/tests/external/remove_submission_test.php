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

namespace mod_assign\external;

use mod_assign_test_generator;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once("$CFG->dirroot/mod/assign/tests/generator.php");
require_once("$CFG->dirroot/mod/assign/tests/fixtures/event_mod_assign_fixtures.php");
require_once("$CFG->dirroot/mod/assign/tests/externallib_advanced_testcase.php");

/**
 * Test the remove submission external function.
 *
 * @package    mod_assign
 * @category   test
 * @covers     \mod_assign\external\remove_submission
 * @copyright  2024 Daniel Ureña <durenadev@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class remove_submission_test extends \mod_assign\externallib_advanced_testcase {
    // Use the generator helper.
    use mod_assign_test_generator;

    /**
     * Called before every test.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Prepare and add submission.
     *
     * @return array
     */
    protected function prepare_and_add_submissions(): array {
        global $DB;
        $course   = $this->getDataGenerator()->create_course();
        $teacher  = $this->getDataGenerator()->create_and_enrol($course, 'teacher');
        $student1 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $student2 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $student3 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $assign   = $this->create_instance($course);
        $this->add_submission($student1, $assign);
        $this->submit_for_grading($student1, $assign);
        $this->add_submission($student2, $assign);
        $this->submit_for_grading($student2, $assign);
        return [$course, $student1, $student2, $student3, $teacher, $assign];
    }

    /**
     * Test remove submission by WS with invalid assign id.
     *
     */
    public function test_remove_submission_with_invalid_assign_id(): void {
        $this->expectException(\dml_exception::class);
        [$course, $student1, $student2, $student3, $teacher, $assign] = $this->prepare_and_add_submissions();
        remove_submission::execute(123, $student1->id);
    }

    /**
     * Test remove submission by WS.
     *
     */
    public function test_remove_submission(): void {
        global $DB;
        [$course, $student1, $student2, $student3, $teacher, $assign] = $this->prepare_and_add_submissions();
        $submission1 = $assign->get_user_submission($student1->id, 0);
        $submission2 = $assign->get_user_submission($student2->id, 0);

        $result = remove_submission::execute($assign->get_instance()->id, $student1->id);
        $this->assertTrue($result['status']);
        $this->assertEmpty($result['warnings']);

        $result = remove_submission::execute($assign->get_instance()->id, $student2->id);
        $this->assertTrue($result['status']);
        $this->assertEmpty($result['warnings']);

        // Make sure submissions were removed.
        $submission1query = $DB->get_record('assign_submission', ['id' => $submission1->id]);
        $submission2query = $DB->get_record('assign_submission', ['id' => $submission2->id]);
        $this->assertEquals(ASSIGN_SUBMISSION_STATUS_NEW, $submission1query->status);
        $this->assertEquals(ASSIGN_SUBMISSION_STATUS_NEW, $submission2query->status);
    }

    /**
     * Test remove submission by WS with invalid user id.
     *
     */
    public function test_remove_submission_with_invalid_user_id(): void {
        [$course, $student1, $student2, $student3, $teacher, $assign] = $this->prepare_and_add_submissions();
        $result = remove_submission::execute($assign->get_instance()->id, 123);
        $this->assertFalse($result['status']);
        $this->assertEquals('submissionempty', $result['warnings'][0]['warningcode']);
    }

    /**
     * Test user can remove own submission.
     *
     */
    public function test_remove_own_submission(): void {
        global $DB;
        [$course, $student1, $student2, $student3, $teacher, $assign] = $this->prepare_and_add_submissions();
        $this->setUser($student3);

        // Remove own submission when user has no submission to remove.
        $result = remove_submission::execute($assign->get_instance()->id, $student3->id);
        $this->assertFalse($result['status']);
        $this->assertNotEmpty($result['warnings']);

        $this->add_submission($student3, $assign);
        // Remove own submission.
        $result = remove_submission::execute($assign->get_instance()->id, $student3->id);
        $this->assertTrue($result['status']);
        $this->assertEmpty($result['warnings']);

        // Make sure submission was removed.
        $submission      = $assign->get_user_submission($student3->id, 0);
        $submissionquery = $DB->get_record('assign_submission', ['id' => $submission->id]);
        $this->assertEquals(ASSIGN_SUBMISSION_STATUS_NEW, $submissionquery->status);
    }
}
