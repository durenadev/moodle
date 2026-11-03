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
 * Message about the Moodle app features related to the current site setup.
 *
 * @package    tool_mobile
 * @copyright  2026 Daniel Ureña <daniel.urena@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class current_setup_message implements \renderable, \templatable {
    /** @var string[] Detected setup, list of 'matomo' and 'logo'. */
    protected array $setup;

    /** @var string Site name. */
    protected string $sitename;

    /** @var string Current plan name. */
    protected string $planname;

    /** @var ?int Course downloads limit per device in the current plan, null if unknown or unlimited. */
    protected ?int $coursedownloadslimit;

    /** @var \moodle_url Subscription page URL. */
    protected \moodle_url $buttonurl;

    /**
     * Constructor.
     *
     * @param string[] $setup Detected setup, list of 'matomo' and 'logo'.
     * @param string $sitename Site name.
     * @param string $planname Current plan name.
     * @param ?int $coursedownloadslimit Course downloads limit per device in the current plan, null if unknown or unlimited.
     * @param \moodle_url $buttonurl Subscription page URL.
     */
    public function __construct(
        array $setup,
        string $sitename,
        string $planname,
        ?int $coursedownloadslimit,
        \moodle_url $buttonurl,
    ) {
        $this->setup = $setup;
        $this->sitename = $sitename;
        $this->planname = $planname;
        $this->coursedownloadslimit = $coursedownloadslimit;
        $this->buttonurl = $buttonurl;
    }

    /**
     * Export the template context for the current setup message.
     *
     * @param \core\output\renderer_base $output
     * @return array<string, mixed>
     */
    public function export_for_template(\core\output\renderer_base $output): array {
        $setuplines = [];
        foreach ($this->setup as $setup) {
            $setuplines[] = match ($setup) {
                'matomo' => self::get_line('currentsetupmatomo', 'chartline'),
                'logo' => self::get_line('currentsetuplogo', 'image'),
            };
        }

        if ($this->coursedownloadslimit) {
            $coursedownloads = self::get_line('currentsetupcoursedownloadslimited', 'download', [
                'plan' => \core_text::strtolower($this->planname),
                'limit' => $this->coursedownloadslimit,
            ]);
        } else {
            $coursedownloads = self::get_line('currentsetupcoursedownloads', 'download');
        }

        return [
            'heading' => get_string('currentsetupheading', 'tool_mobile'),
            'intro' => get_string('currentsetupintro', 'tool_mobile', $this->sitename),
            'setuplabel' => get_string('currentsetupsetuplabel', 'tool_mobile'),
            'setuplines' => $setuplines,
            'benefitslabel' => get_string('currentsetupbenefitslabel', 'tool_mobile'),
            'benefitlines' => [
                $coursedownloads,
                self::get_line('currentsetupbiometriclogin', 'biometric'),
                self::get_line('currentsetuppushnotifications', 'bell'),
            ],
            'buttonlabel' => get_string('seeplandetails', 'tool_mobile'),
            'buttonurl' => $this->buttonurl->out(false),
        ];
    }

    /**
     * Get the context of a message line.
     *
     * @param string $identifier Language string identifier.
     * @param string $icon Icon name, from the current setup icons in the plugin pix folder.
     * @param mixed $a Language string placeholders.
     * @return array<string, string>
     */
    protected static function get_line(string $identifier, string $icon, mixed $a = null): array {
        return [
            'text' => get_string($identifier, 'tool_mobile', $a),
            'iconurl' => (new \moodle_url('/admin/tool/mobile/pix/currentsetup_' . $icon . '.svg'))->out(false),
        ];
    }
}
