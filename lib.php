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
 * Lib functions (cron) to automatically complete the question engine upgrade
 * if it was not done all at once during the main upgrade.
 *
 * @package    local
 * @subpackage qeupgradehelper
 * @copyright  2011 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Standard cron function
 */
function local_qeupgradehelper_cron() {
    $settings = get_config('local_qeupgradehelper');
    if (empty($settings->cronenabled)) {
        return;
    }

    mtrace('qeupgradehelper: local_qeupgradehelper_cron() started at '. date('H:i:s'));
    try {
        local_qeupgradehelper_process($settings);
    } catch (Exception $e) {
        mtrace('qeupgradehelper: local_qeupgradehelper_cron() failed with an exception:');
        mtrace($e->getMessage());
    }
    mtrace('qeupgradehelper: local_qeupgradehelper_cron() finished at ' . date('H:i:s'));
}

/**
 * This function does the cron process within the time range according to settings.
 */
function local_qeupgradehelper_process($settings) {
    require_once(dirname(__FILE__) . '/locallib.php');
    if (!local_qeupgradehelper_is_upgraded()) {
        mtrace('qeupgradehelper: site not yet upgraded. Doing nothing.');
        return;
    }

    $hour = (int) date('H');
    if ($hour < $settings->starthour || $hour >= $settings->stophour) {
        mtrace('qeupgradehelper: not between starthour and stophour, so doing nothing (hour = ' .
                $hour . ').');
        return;
    }

    $stoptime = time() + $settings->procesingtime;
    if (local_qeupgradehelper_is_upgraded()) {
        require_once(dirname(__FILE__).'/afterupgradelib.php');
    }
    while (time() < $stoptime) {
        mtrace('qeupgradehelper: processing ...');
        $quiz = local_qeupgradehelper_get_quiz_for_upgrade();
        if ($quiz) {
            $quizid = $quiz->id;
            $quizsummary = local_qeupgradehelper_get_quiz($quizid);
            if ($quizsummary) {
                $upgrader = new local_qeupgradehelper_attempt_upgrader(
                $quizsummary->id, $quizsummary->numtoconvert);
                $upgrader->convert_all_quiz_attempts();
            }
        }
        // TODO
        mtrace('qeupgradehelper: Done.');
        return;
    }
}