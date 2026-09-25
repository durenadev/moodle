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
 * Tests for the mobile subscription page output.
 *
 * @package   tool_mobile
 * @copyright 2026 Moodle Pty Ltd
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(subscription::class)]
final class subscription_test extends \advanced_testcase {
    /**
     * Test parsing of non-blank, trimmed configuration lines.
     *
     * @param string $config raw setting value
     * @param string[] $expected expected lines
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('get_line_values_provider')]
    public function test_get_line_values(string $config, array $expected): void {
        $method = new \ReflectionMethod(subscription::class, 'get_line_values');
        $this->assertSame($expected, $method->invoke(null, $config));
    }

    /**
     * Data provider for test_get_line_values.
     *
     * @return \Generator
     */
    public static function get_line_values_provider(): \Generator {
        yield 'empty setting' => ['', []];
        yield 'blank lines' => ["\n  \n\t\n", []];
        yield 'trimmed lines and Windows line endings' => [
            "  first value  \r\n\r\n second value \n",
            ['first value', 'second value'],
        ];
    }

    /**
     * Test that custom menu item URLs are trimmed and unique.
     *
     * @param string $config raw setting value
     * @param string[] $expected expected unique URLs
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('get_unique_menu_item_urls_provider')]
    public function test_get_unique_menu_item_urls(string $config, array $expected): void {
        $method = new \ReflectionMethod(subscription::class, 'get_unique_menu_item_urls');
        $this->assertSame($expected, $method->invoke(null, $config));
    }

    /**
     * Data provider for test_get_unique_menu_item_urls.
     *
     * @return \Generator
     */
    public static function get_unique_menu_item_urls_provider(): \Generator {
        yield 'duplicate URLs with spaces' => [
            "Grades | https://example.com/grades | embedded\nGrades again| https://example.com/grades \n",
            ['https://example.com/grades'],
        ];
        yield 'Windows line ending and malformed entries' => [
            "Grades|https://example.com/grades\r\nMissing URL|\r\nBlank line\r\n",
            ['https://example.com/grades'],
        ];
        yield 'empty setting' => ['', []];
    }

    /**
     * Test that custom language string identifiers are trimmed and unique.
     *
     * @param string $config raw setting value
     * @param string[] $expected expected unique identifiers
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('get_unique_customlangstring_ids_provider')]
    public function test_get_unique_customlangstring_ids(string $config, array $expected): void {
        $method = new \ReflectionMethod(subscription::class, 'get_unique_customlangstring_ids');
        $this->assertSame($expected, $method->invoke(null, $config));
    }

    /**
     * Data provider for test_get_unique_customlangstring_ids.
     *
     * @return \Generator
     */
    public static function get_unique_customlangstring_ids_provider(): \Generator {
        yield 'duplicate identifiers with spaces' => [
            " mystring |First value|en\nmystring|Second value|en\r\n",
            ['mystring'],
        ];
        yield 'blank lines and empty setting' => ["\n \n", []];
    }

    /**
     * Export the subscription page and return the status calculated for a feature.
     *
     * @param string $featurename feature name as returned by the Apps Portal
     * @return int
     */
    private function get_feature_status(string $featurename): int {
        global $PAGE;

        $subscriptiondata = [
            'subscription' => [
                'name' => 'Free',
                'plan' => 'free',
                'features' => [
                    ['name' => 'custommenuitems', 'limit' => 10],
                    ['name' => 'customlanguagestrings', 'limit' => 10],
                ],
            ],
            'availableplans' => [],
        ];

        $data = (new subscription($subscriptiondata))->export_for_template($PAGE->get_renderer('tool_mobile'));
        foreach ($data['subscription']['features'] as $feature) {
            if ($feature['name'] === $featurename) {
                return $feature['status'];
            }
        }
        $this->fail("Feature {$featurename} not found in exported data");
    }

    /**
     * Test the custom menu items count shown on the subscription page.
     *
     * @param string $menuitems tool_mobile/custommenuitems value
     * @param string $usermenuitems tool_mobile/customusermenuitems value
     * @param int $expected expected status
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('custom_menu_items_provider')]
    public function test_export_custom_menu_items_count(string $menuitems, string $usermenuitems, int $expected): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('custommenuitems', $menuitems, 'tool_mobile');
        set_config('customusermenuitems', $usermenuitems, 'tool_mobile');
        set_config('customlangstrings', '', 'tool_mobile');

        $this->assertSame($expected, $this->get_feature_status('custommenuitems'));
    }

    /**
     * Data provider for test_export_custom_menu_items_count.
     *
     * @return \Generator
     */
    public static function custom_menu_items_provider(): \Generator {
        yield 'empty settings' => ['', '', 0];
        yield 'blank lines between items' => [
            "Grades|https://example.com/grades|embedded\n\n  \nHelp|https://example.com/help|browser\n",
            "Profile|https://example.com/profile|embedded\n\n",
            3,
        ];
        yield 'duplicate URLs with spaces count once' => [
            "Grades | https://example.com/grades | embedded\nGrades again|https://example.com/grades|browser",
            '',
            1,
        ];
        yield 'Windows line endings and lines without URL' => [
            "Grades|https://example.com/grades\r\n\r\nNo URL|\r\nJust text\r\n",
            "Profile|https://example.com/profile\r\n",
            2,
        ];
    }

    /**
     * Test the custom language strings count shown on the subscription page.
     *
     * @param string $langstrings tool_mobile/customlangstrings value
     * @param int $expected expected status
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('custom_lang_strings_provider')]
    public function test_export_custom_lang_strings_count(string $langstrings, int $expected): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('custommenuitems', '', 'tool_mobile');
        set_config('customusermenuitems', '', 'tool_mobile');
        set_config('customlangstrings', $langstrings, 'tool_mobile');

        $this->assertSame($expected, $this->get_feature_status('customlanguagestrings'));
    }

    /**
     * Data provider for test_export_custom_lang_strings_count.
     *
     * @return \Generator
     */
    public static function custom_lang_strings_provider(): \Generator {
        yield 'empty setting' => ['', 0];
        yield 'blank lines between strings' => [
            "core.login|Log in|en\n\n  \ncore.logout|Log out|en\n",
            2,
        ];
        yield 'same string id in several languages counts once' => [
            "core.login|Log in|en\ncore.login|Entrar|es",
            1,
        ];
        // Identifiers are trimmed after splitting on the pipe.
        yield 'same string id with spaces around the pipe counts once' => [
            "core.login|Log in|en\ncore.login | Entrar | es",
            1,
        ];
        yield 'Windows line endings' => [
            "core.login|Log in|en\r\n\r\ncore.logout|Log out|en\r\n",
            2,
        ];
    }
}
