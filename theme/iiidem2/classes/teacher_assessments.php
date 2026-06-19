<?php
// This file is part of Moodle - http://moodle.org/

namespace theme_iiidem2;

defined('MOODLE_INTERNAL') || die();

/**
 * Quiz assessment summary for the instructor dashboard.
 *
 * @package   theme_iiidem2
 * @copyright 2026 IIIDEM
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class teacher_assessments {

    /** @var int Max student × quiz rows on the dashboard. */
    private const SUBMISSION_LIMIT = 100;

    /**
     * Dashboard context: summary stats + per-quiz average scores.
     *
     * @param array $courses Teaching courses for the teacher.
     * @param int $userid Teacher user id.
     * @return array
     */
    public static function get_dashboard_context(array $courses, int $userid): array {
        global $DB;

        $empty = [
            'hasassessmentsummary' => false,
            'assessmentquizattempts' => '0',
            'assessmentaveragescore' => '—',
            'assessmenthighestscore' => '—',
            'assessmentpendingquizzes' => '0',
            'assessmentquizrows' => [],
            'hasassessmentquizrows' => false,
            'assessmentsubmissionrows' => [],
            'hasassessmentsubmissions' => false,
            'assessmentcoursename' => '',
            'hasassessmentcoursename' => false,
        ];

        if (empty($courses)) {
            return $empty;
        }

        $quizrows = [];
        $totalattempts = 0;
        $quizaverages = [];
        $highest = null;
        $pendingquizzes = 0;

        foreach ($courses as $course) {
            $coursecontext = \context_course::instance($course->id);
            $enrolled = count_enrolled_users($coursecontext, 'mod/quiz:attempt');
            if ($enrolled === 0) {
                $enrolled = count_enrolled_users($coursecontext);
            }

            try {
                $modinfo = get_fast_modinfo($course, $userid);
            } catch (\Exception $e) {
                continue;
            }

            $quizzes = $modinfo->get_instances_of('quiz');
            if (empty($quizzes)) {
                continue;
            }

            uasort($quizzes, static function($a, $b): int {
                return ($a->sectionnum <=> $b->sectionnum) ?: ($a->id <=> $b->id);
            });

            $index = 0;
            foreach ($quizzes as $cm) {
                if (!$cm->uservisible) {
                    continue;
                }

                $quiz = $DB->get_record('quiz', ['id' => $cm->instance], '*', IGNORE_MISSING);
                if (!$quiz) {
                    continue;
                }

                $index++;
                $modcontext = \context_module::instance($cm->id);
                $finishedattempts = (int) $DB->count_records('quiz_attempts', [
                    'quiz' => $quiz->id,
                    'state' => 'finished',
                ]);
                $studentsattempted = (int) $DB->count_records_sql(
                    'SELECT COUNT(DISTINCT userid)
                       FROM {quiz_attempts}
                      WHERE quiz = ? AND state = ?',
                    [$quiz->id, 'finished']
                );

                if ($enrolled > 0 && $studentsattempted < $enrolled) {
                    $pendingquizzes++;
                }

                $totalattempts += $finishedattempts;
                $avgpercent = self::get_quiz_average_percent($quiz);
                $bestpercent = self::get_quiz_highest_percent($quiz);

                if ($avgpercent !== null) {
                    $quizaverages[] = $avgpercent;
                }
                if ($bestpercent !== null && ($highest === null || $bestpercent > $highest)) {
                    $highest = $bestpercent;
                }

                $quizrows[] = [
                    'name' => format_string($quiz->name, true, ['context' => $modcontext]),
                    'label' => get_string('dashboardteacherassessmentquizlabel', 'theme_iiidem2', $index),
                    'scorelabel' => $avgpercent !== null ? (int) round($avgpercent) . '%' : '—',
                    'hasscore' => $avgpercent !== null,
                    'attempts' => $finishedattempts,
                    'attemptslabel' => get_string('dashboardteacherassessmentattemptscount', 'theme_iiidem2', $finishedattempts),
                    'reporturl' => (new \moodle_url('/mod/quiz/report.php', [
                        'id' => $cm->id,
                        'mode' => 'overview',
                    ]))->out(false),
                    'viewurl' => (new \moodle_url('/mod/quiz/view.php', ['id' => $cm->id]))->out(false),
                    'coursename' => format_string($course->fullname, true, ['context' => $coursecontext]),
                ];
            }
        }

        if (empty($quizrows)) {
            return $empty;
        }

        $overallaverage = !empty($quizaverages)
            ? (int) round(array_sum($quizaverages) / count($quizaverages)) . '%'
            : '—';

        $primarycourse = $courses[0];
        $coursename = count($courses) === 1
            ? format_string($primarycourse->fullname, true, ['context' => \context_course::instance($primarycourse->id)])
            : '';
        $multipcourse = count($courses) > 1;

        $submissionrows = self::get_student_submission_rows($courses, $userid, $multipcourse);

        return [
            'hasassessmentsummary' => true,
            'assessmentquizattempts' => (string) $totalattempts,
            'assessmentaveragescore' => $overallaverage,
            'assessmenthighestscore' => $highest !== null ? (int) round($highest) . '%' : '—',
            'assessmentpendingquizzes' => (string) $pendingquizzes,
            'assessmentquizrows' => $quizrows,
            'hasassessmentquizrows' => true,
            'assessmentsubmissionrows' => $submissionrows,
            'hasassessmentsubmissions' => !empty($submissionrows),
            'assessmentcoursename' => $coursename,
            'hasassessmentcoursename' => $coursename !== '',
        ];
    }

    /**
     * Per-student quiz submission rows (who submitted, score, date).
     *
     * @param array $courses
     * @param int $teacherid
     * @param bool $showcoursename
     * @return array
     */
    protected static function get_student_submission_rows(array $courses, int $teacherid, bool $showcoursename): array {
        global $DB;

        require_once($GLOBALS['CFG']->libdir . '/enrollib.php');

        $rows = [];

        foreach ($courses as $course) {
            $students = teacher_students::get_course_students($course, $teacherid);
            if (empty($students)) {
                continue;
            }

            try {
                $modinfo = get_fast_modinfo($course, $teacherid);
            } catch (\Exception $e) {
                continue;
            }

            $quizzes = $modinfo->get_instances_of('quiz');
            if (empty($quizzes)) {
                continue;
            }

            uasort($quizzes, static function($a, $b): int {
                return ($a->sectionnum <=> $b->sectionnum) ?: ($a->id <=> $b->id);
            });

            $coursecontext = \context_course::instance($course->id);
            $coursename = format_string($course->fullname, true, ['context' => $coursecontext]);

            foreach ($quizzes as $cm) {
                if (!$cm->uservisible) {
                    continue;
                }

                $quiz = $DB->get_record('quiz', ['id' => $cm->instance], '*', IGNORE_MISSING);
                if (!$quiz || (float) $quiz->sumgrades <= 0) {
                    continue;
                }

                $modcontext = \context_module::instance($cm->id);
                $quizname = format_string($quiz->name, true, ['context' => $modcontext]);

                foreach ($students as $student) {
                    if (count($rows) >= self::SUBMISSION_LIMIT) {
                        break 3;
                    }

                    $best = $DB->get_record_sql(
                        'SELECT qa.id, qa.sumgrades, qa.timefinish
                           FROM {quiz_attempts} qa
                          WHERE qa.quiz = ? AND qa.userid = ? AND qa.state = ?
                       ORDER BY qa.sumgrades DESC, qa.timefinish DESC',
                        [$quiz->id, $student->id, 'finished'],
                        IGNORE_MISSING
                    );

                    $hassubmitted = !empty($best);
                    $percent = $hassubmitted
                        ? (int) round(((float) $best->sumgrades / (float) $quiz->sumgrades) * 100)
                        : null;

                    $rows[] = [
                        'studentname' => fullname($student),
                        'studentemail' => !empty($student->email) ? $student->email : '',
                        'hasstudentemail' => !empty($student->email),
                        'quizname' => $quizname,
                        'coursename' => $coursename,
                        'showcoursename' => $showcoursename,
                        'scorelabel' => $percent !== null ? $percent . '%' : '—',
                        'hasscore' => $percent !== null,
                        'hassubmitted' => $hassubmitted,
                        'statuslabel' => $hassubmitted
                            ? get_string('dashboardteacherassessmentstatussubmitted', 'theme_iiidem2')
                            : get_string('dashboardteacherassessmentstatuspending', 'theme_iiidem2'),
                        'statusclass' => $hassubmitted ? 'submitted' : 'pending',
                        'datelabel' => $hassubmitted
                            ? userdate($best->timefinish, get_string('strftimedatefullshort', 'core_langconfig'))
                            : '—',
                        'detailurl' => teacher_students::get_student_detail_url((int) $course->id, (int) $student->id)->out(false),
                        'reviewurl' => $hassubmitted
                            ? (new \moodle_url('/mod/quiz/review.php', ['attempt' => $best->id]))->out(false)
                            : '',
                        'hasreviewurl' => $hassubmitted,
                    ];
                }
            }
        }

        usort($rows, static function(array $a, array $b): int {
            $name = strcmp($a['studentname'], $b['studentname']);
            if ($name !== 0) {
                return $name;
            }
            return strcmp($a['quizname'], $b['quizname']);
        });

        return $rows;
    }

    /**
     * Class average from each student's best finished attempt.
     *
     * @param \stdClass $quiz
     * @return float|null Percentage 0–100.
     */
    protected static function get_quiz_average_percent(\stdClass $quiz): ?float {
        global $DB;

        if ((float) $quiz->sumgrades <= 0) {
            return null;
        }

        $records = $DB->get_records_sql(
            'SELECT userid, MAX(sumgrades) AS bestgrade
               FROM {quiz_attempts}
              WHERE quiz = ? AND state = ?
           GROUP BY userid',
            [$quiz->id, 'finished']
        );

        if (empty($records)) {
            return null;
        }

        $sum = 0.0;
        foreach ($records as $record) {
            $sum += ((float) $record->bestgrade / (float) $quiz->sumgrades) * 100;
        }

        return $sum / count($records);
    }

    /**
     * Highest percentage from any single finished attempt.
     *
     * @param \stdClass $quiz
     * @return float|null
     */
    protected static function get_quiz_highest_percent(\stdClass $quiz): ?float {
        global $DB;

        if ((float) $quiz->sumgrades <= 0) {
            return null;
        }

        $max = $DB->get_field_sql(
            'SELECT MAX(sumgrades)
               FROM {quiz_attempts}
              WHERE quiz = ? AND state = ?',
            [$quiz->id, 'finished'],
            IGNORE_MISSING
        );

        if ($max === false || $max === null) {
            return null;
        }

        return ((float) $max / (float) $quiz->sumgrades) * 100;
    }
}
