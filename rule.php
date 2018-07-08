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
 * Implementaton of the quizaccess_kill plugin.
 *
 * @package    quizaccess
 * @subpackage kill
 * @copyright  2018 Daniel Thies <dethies@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/quiz/accessrule/accessrulebase.php');


/**
 * A rule stopping the attempt if a question is answered wrong
 *
 * @copyright  2018 Daniel Thies <dethies@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quizaccess_kill extends quiz_access_rule_base {

    public static function make(quiz $quizobj, $timenow, $canignoretimelimits) {
        if ($quizobj->get_quiz()->preferredbehaviour == 'immediatefeedback' &&
                !empty($quizobj->get_quiz()->kill)) {
            return new self($quizobj, $timenow);
        }
    }

    public function description() {
        return get_string('notice', 'quizaccess_kill');
    }

    public function end_time($attempt) {
        if (empty($attempt->uniqueid)) {
            return false;
        }

        $quba = question_engine::load_questions_usage_by_activity($attempt->uniqueid);
        if (empty($quba->get_slots())) {
           return false;
        }

        foreach ($quba->get_slots() as $slot) {
            if ($quba->get_question_state($slot)->is_incorrect() ||
                    $quba->get_question_state($slot)->is_partially_correct()) {
                return $attempt->timestart ;
            }
        }
        return false;
    }

    public static function add_settings_form_fields(
            mod_quiz_mod_form $quizform, MoodleQuickForm $mform) {
        $control = $mform->createElement('selectyesno', 'kill', get_string('kill', 'quizaccess_kill'));
        $mform->disabledIf($control, 'preferredbehaviour', 'neq', 'immediatefeedback');
        $mform->insertElementBefore($control, 'reviewoptionshdr');
        $mform->addHelpButton('kill', 'kill', 'quizaccess_kill');
    }

    public static function delete_settings($quiz) {
        global $DB;
        $DB->delete_records('quizaccess_kill', array('quizid' => $quiz->id));
    }

    public static function save_settings($quiz) {
        global $DB;
        if (empty($quiz->kill)) {
            $DB->delete_records('quizaccess_kill', array('quizid' => $quiz->id));
        } else {
            if (!$DB->record_exists('quizaccess_kill', array('quizid' => $quiz->id))) {
                $record = new stdClass();
                $record->quizid = $quiz->id;
                $record->kill = 1;
                $DB->insert_record('quizaccess_kill', $record);
            }
        }
    }

    public static function get_settings_sql($quizid) {
        return array(
            'COALESCE(kill, 0) AS kill', // Using COALESCE to replace NULL with 0.
            'LEFT JOIN {quizaccess_kill} qa_k ON qa_k.quizid = quiz.id',
            array());
    }
}
