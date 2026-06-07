<?php
// This file is part of Moodle - http://moodle.org/
//
// @package   theme_iiidem2
// @copyright 2026 IIIDEM
// @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace theme_iiidem2\output\mod_quiz;

use mod_quiz\access_manager;
use mod_quiz\quiz_attempt;

defined('MOODLE_INTERNAL') || die();

/**
 * Quiz renderer — custom MCQ attempt UI.
 *
 * @package theme_iiidem2
 */
class renderer extends \mod_quiz\output\renderer {

    /**
     * Use custom attempt output on mod/quiz/attempt.php.
     *
     * @return bool
     */
    protected function use_custom_mcq_ui(): bool {
        return \theme_iiidem2_use_custom_quiz_ui($this->page);
    }

    /**
     * @param quiz_attempt $attemptobj
     * @param int $page
     * @param access_manager $accessmanager
     * @param array $messages
     * @param array $slots
     * @param int $id
     * @param int $nextpage
     * @return string
     */
    public function attempt_page($attemptobj, $page, $accessmanager, $messages, $slots, $id,
            $nextpage) {
        if (!$this->use_custom_mcq_ui()) {
            return parent::attempt_page($attemptobj, $page, $accessmanager, $messages, $slots, $id, $nextpage);
        }

        $output = '';
        $output .= $this->header();
        $output .= $this->quiz_notices($messages);
        $output .= $this->countdown_timer($attemptobj, time());
        $output .= $this->attempt_form($attemptobj, $page, $slots, $id, $nextpage);
        $output .= $this->footer();
        return $output;
    }

    /**
     * @param string|\moodle_url $quizviewurl
     * @return string
     */
    public function during_attempt_tertiary_nav($quizviewurl): string {
        if ($this->use_custom_mcq_ui()) {
            return '';
        }
        return parent::during_attempt_tertiary_nav($quizviewurl);
    }
}
