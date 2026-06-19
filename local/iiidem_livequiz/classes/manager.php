<?php
namespace local_iiidem_livequiz;

defined('MOODLE_INTERNAL') || die();

/**
 * Live session MCQ business logic.
 */
class manager {

    public const STATUS_DRAFT = 0;
    public const STATUS_ACTIVE = 1;
    public const STATUS_CLOSED = 2;

    /**
     * @param int $courseid
     * @param int|null $userid
     * @return bool
     */
    public static function user_can_manage(int $courseid, ?int $userid = null): bool {
        global $USER;

        if ($userid === null) {
            $userid = (int) $USER->id;
        }

        $context = \context_course::instance($courseid);
        return has_capability('local/iiidem_livequiz:manage', $context, $userid)
            || has_capability('moodle/course:manageactivities', $context, $userid);
    }

    /**
     * @param int $courseid
     * @param int|null $userid
     * @return bool
     */
    public static function user_can_participate(int $courseid, ?int $userid = null): bool {
        global $USER, $CFG;

        require_once($CFG->libdir . '/enrollib.php');

        if ($userid === null) {
            $userid = (int) $USER->id;
        }

        if (!isloggedin() || isguestuser($userid)) {
            return false;
        }

        $context = \context_course::instance($courseid);
        if (!is_enrolled($context, $userid, '', true)) {
            return false;
        }

        return !self::user_can_manage($courseid, $userid);
    }

    /**
     * @param int $courseid
     * @param int|null $cmid
     * @return \stdClass|null
     */
    public static function get_active_session(int $courseid, ?int $cmid = null): ?\stdClass {
        global $DB;

        $params = ['courseid' => $courseid, 'status' => self::STATUS_ACTIVE];
        $sql = 'SELECT * FROM {local_iiidem_livequiz_session} WHERE courseid = :courseid AND status = :status';
        if ($cmid !== null) {
            $sql .= ' AND cmid = :cmid';
            $params['cmid'] = $cmid;
        }
        $sql .= ' ORDER BY timestarted DESC';

        return $DB->get_record_sql($sql, $params, IGNORE_MISSING) ?: null;
    }

    /**
     * @param int $sessionid
     * @return \stdClass|null
     */
    public static function get_session(int $sessionid): ?\stdClass {
        global $DB;
        return $DB->get_record('local_iiidem_livequiz_session', ['id' => $sessionid], '*', IGNORE_MISSING) ?: null;
    }

    /**
     * @param int $courseid
     * @return array
     */
    public static function get_course_sessions(int $courseid): array {
        global $DB;
        return $DB->get_records('local_iiidem_livequiz_session', ['courseid' => $courseid], 'timecreated DESC');
    }

    /**
     * @param int $courseid
     * @param int $cmid
     * @param string $name
     * @param int $teacherid
     * @return int
     */
    public static function create_session(int $courseid, int $cmid, string $name, int $teacherid): int {
        global $DB;

        $now = time();
        return (int) $DB->insert_record('local_iiidem_livequiz_session', (object) [
            'courseid' => $courseid,
            'cmid' => $cmid,
            'name' => $name,
            'teacherid' => $teacherid,
            'status' => self::STATUS_DRAFT,
            'timecreated' => $now,
        ]);
    }

    /**
     * @param int $sessionid
     * @param string $text
     * @param array $options
     * @param int $correctindex
     * @return int
     */
    public static function add_question(int $sessionid, string $text, array $options, int $correctindex = -1): int {
        global $DB;

        $options = array_values(array_filter(array_map('trim', $options), static function(string $opt): bool {
            return $opt !== '';
        }));
        if (count($options) < 2) {
            throw new \moodle_exception('nooptions', 'local_iiidem_livequiz');
        }

        $sortorder = (int) $DB->count_records('local_iiidem_livequiz_question', ['sessionid' => $sessionid]);

        return (int) $DB->insert_record('local_iiidem_livequiz_question', (object) [
            'sessionid' => $sessionid,
            'sortorder' => $sortorder,
            'questiontext' => trim($text),
            'opt0' => $options[0] ?? '',
            'opt1' => $options[1] ?? '',
            'opt2' => $options[2] ?? null,
            'opt3' => $options[3] ?? null,
            'correctindex' => $correctindex,
        ]);
    }

    /**
     * @param int $questionid
     * @param int $sessionid
     * @return void
     */
    public static function delete_question(int $questionid, int $sessionid): void {
        global $DB;
        $DB->delete_records('local_iiidem_livequiz_question', ['id' => $questionid, 'sessionid' => $sessionid]);
    }

    /**
     * @param int $sessionid
     * @return void
     */
    public static function activate_session(int $sessionid): void {
        global $DB;

        $session = self::get_session($sessionid);
        if (!$session) {
            throw new \moodle_exception('invalidsession', 'local_iiidem_livequiz');
        }

        if (!$DB->count_records('local_iiidem_livequiz_question', ['sessionid' => $sessionid])) {
            throw new \moodle_exception('noquestions', 'local_iiidem_livequiz');
        }

        $now = time();
        $others = $DB->get_records_select(
            'local_iiidem_livequiz_session',
            'courseid = ? AND cmid = ? AND status = ? AND id <> ?',
            [$session->courseid, $session->cmid, self::STATUS_ACTIVE, $sessionid]
        );
        foreach ($others as $other) {
            $other->status = self::STATUS_CLOSED;
            $other->timeclosed = $now;
            $DB->update_record('local_iiidem_livequiz_session', $other);
        }

        $session->status = self::STATUS_ACTIVE;
        $session->timestarted = $now;
        $session->timeclosed = null;
        $DB->update_record('local_iiidem_livequiz_session', $session);
    }

    /**
     * @param int $sessionid
     * @return void
     */
    public static function close_session(int $sessionid): void {
        global $DB;

        $session = self::get_session($sessionid);
        if (!$session) {
            throw new \moodle_exception('invalidsession', 'local_iiidem_livequiz');
        }

        $session->status = self::STATUS_CLOSED;
        $session->timeclosed = time();
        $DB->update_record('local_iiidem_livequiz_session', $session);
    }

    /**
     * @param int $sessionid
     * @return array
     */
    public static function get_questions(int $sessionid): array {
        global $DB;
        return array_values($DB->get_records('local_iiidem_livequiz_question', ['sessionid' => $sessionid], 'sortorder ASC'));
    }

    /**
     * @param \stdClass $question
     * @return array
     */
    public static function question_options(\stdClass $question): array {
        $options = [];
        foreach (['opt0', 'opt1', 'opt2', 'opt3'] as $index => $field) {
            if (!empty($question->$field)) {
                $options[] = [
                    'index' => $index,
                    'label' => $question->$field,
                ];
            }
        }
        return $options;
    }

    /**
     * @param int $sessionid
     * @param int $userid
     * @return bool
     */
    public static function has_submitted(int $sessionid, int $userid): bool {
        global $DB;

        $questioncount = (int) $DB->count_records('local_iiidem_livequiz_question', ['sessionid' => $sessionid]);
        if ($questioncount === 0) {
            return false;
        }

        $answercount = (int) $DB->count_records('local_iiidem_livequiz_answer', [
            'sessionid' => $sessionid,
            'userid' => $userid,
        ]);

        return $answercount >= $questioncount;
    }

    /**
     * @param int $sessionid
     * @param int $userid
     * @param array $choices questionid => choiceindex
     * @return bool
     */
    public static function submit_answers(int $sessionid, int $userid, array $choices): bool {
        global $DB;

        $session = self::get_session($sessionid);
        if (!$session || (int) $session->status !== self::STATUS_ACTIVE) {
            return false;
        }

        if (self::has_submitted($sessionid, $userid)) {
            return false;
        }

        $questions = self::get_questions($sessionid);
        if (count($questions) !== count($choices)) {
            return false;
        }

        $now = time();
        foreach ($questions as $question) {
            if (!array_key_exists($question->id, $choices)) {
                return false;
            }
            $choiceindex = (int) $choices[$question->id];
            $valid = array_column(self::question_options($question), 'index');
            if (!in_array($choiceindex, $valid, true)) {
                return false;
            }

            $DB->insert_record('local_iiidem_livequiz_answer', (object) [
                'sessionid' => $sessionid,
                'questionid' => $question->id,
                'userid' => $userid,
                'choiceindex' => $choiceindex,
                'timecreated' => $now,
            ]);
        }

        return true;
    }

    /**
     * @param int $sessionid
     * @return array
     */
    public static function get_teacher_results(int $sessionid): array {
        global $DB;

        $session = self::get_session($sessionid);
        if (!$session) {
            return [];
        }

        $questions = self::get_questions($sessionid);
        $gradable = array_filter($questions, static function(\stdClass $q): bool {
            return (int) $q->correctindex >= 0;
        });
        $gradablecount = count($gradable);

        $answers = $DB->get_records('local_iiidem_livequiz_answer', ['sessionid' => $sessionid]);
        $byuser = [];
        foreach ($answers as $answer) {
            $byuser[$answer->userid][$answer->questionid] = (int) $answer->choiceindex;
        }

        $rows = [];
        foreach ($byuser as $userid => $useranswers) {
            $user = $DB->get_record('user', ['id' => $userid], 'id, firstname, lastname, email', IGNORE_MISSING);
            if (!$user) {
                continue;
            }

            $correct = 0;
            foreach ($gradable as $question) {
                if (isset($useranswers[$question->id]) && $useranswers[$question->id] === (int) $question->correctindex) {
                    $correct++;
                }
            }

            $answered = count($useranswers);
            $totalquestions = count($questions);
            $scorelabel = $gradablecount > 0
                ? (int) round(($correct / $gradablecount) * 100) . '%'
                : '—';

            $rows[] = [
                'userid' => (int) $userid,
                'studentname' => fullname($user),
                'email' => $user->email ?? '',
                'answered' => $answered,
                'total' => $totalquestions,
                'complete' => $answered >= $totalquestions,
                'scorelabel' => $scorelabel,
                'submittedlabel' => $answered >= $totalquestions
                    ? get_string('active', 'local_iiidem_livequiz')
                    : get_string('notstarted', 'local_iiidem_livequiz'),
            ];
        }

        usort($rows, static function(array $a, array $b): int {
            return strcmp($a['studentname'], $b['studentname']);
        });

        return [
            'rows' => $rows,
            'submittedcount' => count(array_filter($rows, static function(array $r): bool {
                return $r['complete'];
            })),
        ];
    }

    /**
     * Mustache / JSON payload for the live class page.
     *
     * @param int $cmid
     * @param int $courseid
     * @return array
     */
    public static function get_live_page_shell_context(int $cmid, int $courseid): array {
        global $CFG;

        $defaults = [
            'haslivequiz' => false,
            'livequizcmid' => $cmid,
            'livequizcourseid' => $courseid,
            'livequizapiurl' => '',
            'livequizsesskey' => '',
        ];

        if (!isloggedin() || isguestuser()) {
            return $defaults;
        }

        $defaults['haslivequiz'] = true;
        $defaults['livequizapiurl'] = (new \moodle_url('/local/iiidem_livequiz/api.php'))->out(false);
        $defaults['livequizsesskey'] = sesskey();

        return $defaults;
    }

    /**
     * @param int $courseid
     * @param int|null $userid
     * @return array
     */
    public static function get_live_class_pages(int $courseid, ?int $userid = null): array {
        global $CFG;

        require_once($CFG->dirroot . '/course/lib.php');

        $course = get_course($courseid);
        try {
            $modinfo = get_fast_modinfo($course, $userid);
        } catch (\Exception $e) {
            return [];
        }

        $pages = [];
        foreach ($modinfo->get_instances_of('page') as $cm) {
            if (!$cm->uservisible) {
                continue;
            }
            if (function_exists('theme_iiidem2_page_name_matches_live_class')) {
                if (!\theme_iiidem2_page_name_matches_live_class($cm->name)) {
                    continue;
                }
            } else if (!preg_match('/\b(webex|live\s*class|online\s*class|virtual\s*class)\b/i', $cm->name)) {
                continue;
            }
            $pages[] = [
                'cmid' => (int) $cm->id,
                'name' => format_string($cm->name, true, ['context' => \context_module::instance($cm->id)]),
            ];
        }

        return $pages;
    }

    /**
     * @param int $status
     * @return string
     */
    public static function status_label(int $status): string {
        switch ($status) {
            case self::STATUS_ACTIVE:
                return get_string('active', 'local_iiidem_livequiz');
            case self::STATUS_CLOSED:
                return get_string('closed', 'local_iiidem_livequiz');
            default:
                return get_string('draft', 'local_iiidem_livequiz');
        }
    }
}
