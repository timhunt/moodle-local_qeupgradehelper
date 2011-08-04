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

require_once(dirname(__FILE__) . '/../../config.php');
require_once(dirname(__FILE__) . '/locallib.php');
require_once(dirname(__FILE__) . '/afterupgradelib.php');
require_once($CFG->libdir . '/adminlib.php');

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

function local_qeupgradehelper_get_quiz_for_upgrade() {
    global $DB;
    return $DB->get_record_sql(" SELECT
                quiz.id,
                quiz.name,
                c.shortname,
                c.id AS courseid,
                COUNT(1) AS attemptcount,
                SUM(qsesscounts.num) AS questionattempts

            FROM mdl_quiz_attempts quiza
            JOIN mdl_quiz quiz ON quiz.id = quiza.quiz
            JOIN mdl_course c ON c.id = quiz.course
            LEFT JOIN (
                SELECT attemptid, COUNT(1) AS num
                FROM mdl_question_sessions
                GROUP BY attemptid
            ) qsesscounts ON qsesscounts.attemptid = quiza.uniqueid

            WHERE quiza.preview = 0 AND quiza.needsupgradetonewqe = 1

            GROUP BY quiz.id, quiz.name, c.shortname, c.id

            ORDER BY quiza.timemodified DESC limit 1;
  ");
}

/**
 * This function does the cron process within the time range according to settings.
 */
function local_qeupgradehelper_process($settings) {
    global $CFG;	
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
    while (time() < $stoptime) {
        mtrace('qeupgradehelper: processing ...');
	$quizzes = local_qeupgradehelper_get_quiz_for_upgrade();
	$quizid = $quizzes->id;
	$quizsummary = local_qeupgradehelper_get_quiz($quizid);
	if ($quizsummary) {
		$upgrader = new local_qeupgradehelper_attempt_upgrader(
            	$quizsummary->id, $quizsummary->numtoconvert);
    		$upgrader->convert_all_quiz_attempts(); 
	}
    
        mtrace('qeupgradehelper: Done.');
        return;
    }
}
