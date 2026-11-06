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

namespace tool_mobile\output;

/**
 * Message about the mobile services being turned off on the site.
 *
 * @package    tool_mobile
 * @copyright  2026 Daniel Ureña <daniel.urena@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mobile_services_off_message implements \renderable, \templatable {
    /** @var string Site name. */
    protected string $sitename;

    /** @var \moodle_url Mobile services setting URL. */
    protected \moodle_url $buttonurl;

    /**
     * Constructor.
     *
     * @param string $sitename Site name.
     * @param \moodle_url $buttonurl Mobile services setting URL.
     */
    public function __construct(
        string $sitename,
        \moodle_url $buttonurl,
    ) {
        $this->sitename = $sitename;
        $this->buttonurl = $buttonurl;
    }

    /**
     * Export the template context for the mobile services off message.
     *
     * @param \core\output\renderer_base $output
     * @return array<string, string>
     */
    public function export_for_template(\core\output\renderer_base $output): array {
        return [
            'heading' => get_string('mobileservicesoffheading', 'tool_mobile'),
            'iconurl' => (new \moodle_url('/admin/tool/mobile/pix/mobileusershare_mobile.svg'))->out(false),
            'intro' => get_string('currentsetupintro', 'tool_mobile', $this->sitename),
            'body' => get_string('mobileservicesoffbody', 'tool_mobile'),
            'footernote' => get_string('mobileservicesoffdismiss', 'tool_mobile'),
            'buttonlabel' => get_string('enablethissetting', 'tool_mobile'),
            'buttonurl' => $this->buttonurl->out(false),
        ];
    }
}
