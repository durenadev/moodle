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
 * Subscription page.
 *
 * @package   tool_mobile
 * @copyright 2020 Moodle Pty Ltd
 * @author    <juan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_mobile\output;

/**
 * Subscription page.
 *
 * @package   tool_mobile
 * @copyright 2020 Moodle Pty Ltd
 * @author    <juan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class subscription implements \renderable, \templatable {

    /**
     * Subscription data.
     *
     * @var array subscription data
     */
    protected $subscriptiondata;

    /**
     * Constructor for the class, sets the subscription data.
     *
     * @param array $subscriptiondata subscription data
     * @return void
     */
    public function __construct(array $subscriptiondata) {
        $this->subscriptiondata = $subscriptiondata;
    }

    /**
     * Exports the data.
     *
     * @param \renderer_base $output
     * @return array with the subscription information
     */
    public function export_for_template(\renderer_base $output): array {
        global $CFG;

        $ms = get_config('tool_mobile');    // Get mobile settings.

        $data = $this->subscriptiondata;
        // Check subscription information to prepare the page data.
        if (empty($data) || empty($data['subscription']) || !isset($data['availableplans'])) {
            // Prepare data for no subscription information case, with a message and a link to the apps portal.
            $datanosub['appsportalurl'] = \tool_mobile\api::MOODLE_APPS_PORTAL_URL;
            $datanosub['mesagenosubscriptioninfo'][] = [
                'message' => get_string('nosubsblocked', 'tool_mobile', $datanosub['appsportalurl']),
                'title' => get_string('nosubswhyhappen', 'tool_mobile'),
                'extraclasses' => 'mt-4 mb-0',
            ];
            return $datanosub;
        }

        $lastupdate = get_config('tool_mobile', 'subscriptioninfoupdated');
        if (empty($lastupdate) || time() - $lastupdate > 10 * 24 * 3600) {
            $data['messageoldcache'] = get_string('showingcacheddata', 'tool_mobile', \tool_mobile\api::MOODLE_APPS_PORTAL_URL);
        }

        $data['appsportalurl'] = \tool_mobile\api::MOODLE_APPS_PORTAL_URL;
        $appsportalupgradeurl = new \moodle_url(\tool_mobile\api::MOODLE_APPS_PORTAL_URL, [
            'sitesecret' => md5(\core\hub\registration::get_secret()),
            'siteurl' => $CFG->wwwroot,
            'origin' => 'moodlelms',
        ]);
        $data['appsportalupgradeurl'] = $appsportalupgradeurl->out(false);
        $appsportalupgradeurl = new \moodle_url(\tool_mobile\api::MOODLE_APPS_PORTAL_URL, [
            'sitesecret' => md5(\core\hub\registration::get_secret()),
            'siteurl' => $CFG->wwwroot,
            'origin' => 'moodlelms',
        ]);
        $data['appsportalupgradeurl'] = $appsportalupgradeurl->out(false);

        // First prepare messages that may come from the WS.
        if (!empty($data['messages'])) {
            foreach ($data['messages'] as $msg) {
                if (empty($msg['code'])) {
                    $data['messages' . $msg['type']][] = ['message' => $msg['message']];
                    continue;
                }
                switch ($msg['code']) {
                    case "deprecatedplan":
                        $data['mesagedeprecation'][] = [
                            'message' => $msg['message'],
                            'titleicon' => [
                                'icon' => 'i/warning',
                                'component' => 'core',
                            ],
                            'title' => get_string('plancontinues', 'tool_mobile', $data['subscription']['name']),
                        ];
                        break;
                    case "missingcredentials":
                        $data['messagemissingcredentials'][] = [
                            'message' => $msg['message'],
                            'extraclasses' => 'mt-4',
                        ];
                        break;

                    case "unregisteredsite":
                        $data['messageunregisteredsite'][] = [
                            'message' => $msg['message'],
                            'titleicon' => [
                                'icon' => 'i/warning',
                                'component' => 'core',
                            ],
                            'buttonstr' => get_string('register', 'admin'),
                            'buttonurl' => new \moodle_url('/admin/registration/index.php'),
                            'extrabuttonclasses' => 'text-dark',
                        ];
                        break;

                    default:
                        $data['messages' . $msg['type']][] = ['message' => $msg['message']];
                        break;
                }
            }
        }
        unset($data['messages']);

        // Derive plan flags for template logic-less checks.
        if (!empty($data['subscription']['plan'])) {
            $data['subscription']['isfree'] = ($data['subscription']['plan'] === 'free');
            $data['subscription']['ispremium'] =
                ($data['subscription']['plan'] === 'premium' || $data['subscription']['plan'] === 'bma');
        }

        if ($data['subscription']['ispremium']) {
            $data['mesagefullaccess'][] = [
                'titleicon' => [
                    'icon' => 'i/checkedcircle',
                    'component' => 'core',
                ],
                'title' => get_string('fullaccessfeatures', 'tool_mobile', $data['subscription']['name']),
            ];
        }

        unset($data['subscription']['deprecationmessage']);

        // Review availableplans.
        foreach ($data['availableplans'] as $plan) {
            if ($plan['plan'] != 'premium') {
                continue;
            }
            $data['tocompareplan'] = $plan;
        }

        // Now prepare statistics information.
        if (!isset($data['messagemissingcredentials'])) {
            if (isset($data['statistics']['notifications'])) {
                $data['notifications'] = $data['statistics']['notifications'];
                unset($data['statistics']['notifications']);

                // Find current month data.
                $data['notifications']['currentactivedevices'] = 0;

                if (isset($data['notifications']['monthly'][0])) {
                    $currentmonth = $data['notifications']['monthly'][0];
                    $data['notifications']['currentactivedevices'] = $currentmonth['activedevices'];
                    if (!empty($currentmonth['limitreachedtime'])) {
                        $data['notifications']['limitreachedtime'] = $currentmonth['limitreachedtime'];
                        $data['notifications']['ignorednotificationswarning'] = [
                            'message' => get_string('notificationslimitreached', 'tool_mobile'),
                            'icon' => [
                                'icon' => 'i/risk_dataloss',
                                'component' => 'core',
                            ],
                            'buttonstr' => get_string('upgradetosubscription', 'tool_mobile', $data['tocompareplan']['name']),
                            'buttonurl' => new \moodle_url('/admin/registration/index.php'),
                            'extraclasses' => 'alert-danger',
                            'extrabuttonclasses' => 'bg-danger text-light',
                        ];
                    }
                    $monthformat = get_string('strftimemonth');
                    foreach ($data['notifications']['monthly'] as $key => $month) {
                        if (!empty($month['year']) && !empty($month['month'])) {
                            $timestamp = make_timestamp((int)$month['year'], (int)$month['month'], 1);
                            $data['notifications']['monthly'][$key]['monthstr'] = userdate($timestamp, $monthformat);
                        }
                    }
                }
                $data['mesagesnotificationsseemore'] = get_string('notificationsseemore', 'tool_mobile', $data['appsportalurl']);
            } else {
                $urlmessagesetting = new \moodle_url('/admin/settings.php', ['section' => 'messagesettingairnotifier']);
                $data['mesagesnonotifications'][] = [
                    'icon' => [
                        'icon' => 'i/warning',
                        'component' => 'core',
                    ],
                    'message' => get_string('notificationsmissingwarning', 'tool_mobile', $urlmessagesetting),
                    'extraclasses' => 'mt-4',
                ];
            }
        }
        $subscribedfeatures = [];
        // Review features.
        foreach ($data['subscription']['features'] as &$feature) {
            // Check the type of features, if it is a limitation or functionality feature.
            if (array_key_exists('limit', $feature)) {
                if (empty($feature['limit'])) {   // Unlimited, no need to calculate current values.
                    $feature['humanstatus'] = get_string('unlimited');
                    $feature['showbar'] = 0;
                    $feature['limit'] = get_string('unlimited');
                    $feature['limit'] = get_string('unlimited');
                }

                switch ($feature['name']) {
                    // Check active devices.
                    case 'pushnotificationsdevices':
                        $feature['status'] = 0;
                        $feature['status'] = 0;
                        if (isset($data['notifications']['currentactivedevices'])) {
                            $feature['status'] = $data['notifications']['currentactivedevices'];
                        }
                        $feature['showstatus'] = 1;
                        $feature['showstatus'] = 1;
                        break;
                    // Check menu items.
                    case 'custommenuitems':
                        $custommenuitems = [];
                        $els = rtrim($ms->custommenuitems, "\n");
                        if (!empty($els)) {
                            $custommenuitems = explode("\n", $els);
                            // Get unique custom menu urls.
                            $custommenuitems = array_flip(
                                array_map(function ($val) {
                                    return explode('|', $val)[1];
                                }, $custommenuitems)
                            );
                        }

                        $customusermenuitems = [];
                        $els = rtrim($ms->customusermenuitems, "\n");
                        if (!empty($els)) {
                            $customusermenuitems = explode("\n", $els);
                            // Get unique custom menu urls.
                            $customusermenuitems = array_flip(
                                array_map(function ($val) {
                                    return explode('|', $val)[1];
                                }, $customusermenuitems)
                            );
                        }

                        $feature['status'] = count($custommenuitems) + count($customusermenuitems);
                        $feature['showstatus'] = 1;
                        $feature['showstatus'] = 1;
                        break;
                    // Check language strings.
                    case 'customlanguagestrings':
                        $langstrings = [];
                        $els = rtrim($ms->customlangstrings, "\n");
                        if (!empty($els)) {
                            $langstrings = explode("\n", $els);
                            // Get unique language string ids.
                            $langstrings = array_flip(
                                array_map(function($val) {
                                    return explode('|', $val)[0];
                                }, $langstrings)
                            );
                        }
                        $feature['status'] = count($langstrings);
                        $feature['showstatus'] = 1;
                        $feature['showstatus'] = 1;
                        break;
                    // Check disabled features strings.
                    case 'disabledfeatures':
                        $feature['status'] = empty($ms->disabledfeatures) ? 0 : count(explode(',', $ms->disabledfeatures));
                        $feature['showstatus'] = 1;
                        $feature['showstatus'] = 1;
                        break;
                }

                $feature['humanstatus'] = '?/' . $feature['limit'];
                // Check if we should display the bar and how.
                if (isset($feature['status'])) {
                    $feature['humanstatus'] = (string)$feature['status'];
                    if (is_int($feature['status']) && is_int($feature['limit'])) {
                        $feature['showbar'] = 1;
                        // Show currentussage with or without currentusage.
                        if (!isset($data['subscription']['currentusage'])) {
                            $data['subscription']['currentusage'] = [];
                        }
                        $feature['humanstatus'] = $feature['status'] . ' / ' . $feature['limit'];
                        $feature['percentage'] = round(($feature['status'] / $feature['limit']) * 100);
                        if ($feature['percentage'] > 100) {
                            $feature['percentage'] = 100;
                        }
                        $feature['barclass'] = 'bg-success';

                        if ($feature['status'] >= ($feature['limit'] / 2)) {
                            $feature['barclass'] = 'bg-warning';
                        }
                        if ($feature['status'] >= $feature['limit']) {
                            $feature['barclass'] = 'bg-danger';
                            if ($feature['status'] > $feature['limit']) {
                                $feature['humanstatus'] .= ' - ' . get_string('subscriptionlimitsurpassed', 'tool_mobile');
                            }
                        }
                    } else {
                        $feature['showbar'] = 0;
                        if ($feature['status'] >= $feature['limit']) {
                            $feature['barclass'] = 'bg-danger';
                            if ($feature['status'] > $feature['limit']) {
                                $feature['humanstatus'] .= ' - ' . get_string('subscriptionlimitsurpassed', 'tool_mobile');
                            }
                        }
                    }
                }
                $subscribedfeatures[$feature['name']]['subscribedlimitstr'] = $feature['limitstr'];
                $subscribedfeatures[$feature['name']]['subscribedenabled'] = $feature['enabled'];
                $subscribedfeatures[$feature['name']]['subscribeddecoration'] =
                    $feature['enabled'] ? 'text-primary' : 'text-danger';
                $subscribedfeatures[$feature['name']]['subscribedlimitstr'] = $feature['limitstr'];
                $subscribedfeatures[$feature['name']]['subscribedenabled'] = $feature['enabled'];
                $subscribedfeatures[$feature['name']]['subscribeddecoration'] =
                    $feature['enabled'] ? 'text-primary' : 'text-danger';
            } else {
                $feature['humanstatus'] = empty($feature['enabled']) ? get_string('notincluded') : get_string('included');

                if (empty($feature['enabled'])) {
                    switch ($feature['name']) {
                        // Check remote themes.
                        case 'remotethemes':
                            if (!empty($CFG->mobilecssurl)) {
                                $feature['message'] = [
                                    'type' => 'danger', 'message' => get_string('subscriptionfeaturenotapplied', 'tool_mobile')];
                            }
                            break;
                        // Check site logo.
                        case 'sitelogo':
                            if ($output->get_logo_url() || $output->get_compact_logo_url()) {
                                $feature['message'] = [
                                    'type' => 'danger', 'message' => get_string('subscriptionfeaturenotapplied', 'tool_mobile')];
                            }
                            break;
                        // Check QR automatic login.
                        case 'qrautomaticlogin':
                            if ($ms->qrcodetype == \tool_mobile\api::QR_CODE_LOGIN) {
                                $feature['message'] = [
                                    'type' => 'danger', 'message' => get_string('subscriptionfeaturenotapplied', 'tool_mobile')];
                            }
                            break;
                    }
                }
                $subscribedfeatures[$feature['name']]['subscribedhumanstatus'] = $feature['humanstatus'];
                $subscribedfeatures[$feature['name']]['subscribedenabled'] = $feature['enabled'] ? 'fa-check' : 'fa-times';
                $subscribedfeatures[$feature['name']]['subscribeddecoration'] =
                    $feature['enabled'] ? 'text-primary' : 'text-danger';
                $subscribedfeatures[$feature['name']]['subscribedhumanstatus'] = $feature['humanstatus'];
                $subscribedfeatures[$feature['name']]['subscribedenabled'] = $feature['enabled'] ? 'fa-check' : 'fa-times';
                $subscribedfeatures[$feature['name']]['subscribeddecoration'] =
                    $feature['enabled'] ? 'text-primary' : 'text-danger';
            }
        }

        usort($data['subscription']['features'],
            function (array $featurea, array $featureb) {
                $isfeaturea = !array_key_exists('limit', $featurea);
                $isfeatureb = !array_key_exists('limit', $featureb);

                if (!$isfeaturea && $isfeatureb) {
                    return 1;
                }
                return 0;
            }
        );

        if (!$data['availableplans']) {
            return $data;
        }
        // Review availableplans features and filter to use premium.
        foreach ($data['tocompareplan']['features'] as &$feature) {
            if (array_key_exists('limit', $feature) && empty($feature['limit'])) {
                $feature['limit'] = 'unlimited';
            }
            $feature['humanstatus'] = $feature['enabled'] ? get_string('included') : get_string('notincluded');
            if (isset($subscribedfeatures[$feature['name']])) {
                $feature = array_merge($feature, $subscribedfeatures[$feature['name']]);
            }
        }
        if (!empty($data['tocompareplan'])) {
            usort(
                $data['tocompareplan']['features'],
                function (array $featurea, array $featureb) {
                    $isfeaturea = !array_key_exists('limit', $featurea);
                    $isfeatureb = !array_key_exists('limit', $featureb);

                    if (!$isfeaturea && $isfeatureb) {
                        return 1;
                    }
                    return 0;
                }
            );
        }
        unset($data['availableplans']);
        return $data;
    }
}
