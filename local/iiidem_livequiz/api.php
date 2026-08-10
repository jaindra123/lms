<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

use local_iiidem_livequiz\manager;

global $USER;

require_login();

header('Content-Type: application/json; charset=utf-8');

$action = required_param('action', PARAM_ALPHANUMEXT);

try {
    // Polling endpoints are chatty — enforce a per-user floor.
    if ($action === 'getactive' || $action === 'teacherstats') {
        \theme_iiidem2\rate_limit::require_json('livequiz_poll', 40, 60);
    } else if ($action === 'submit') {
        \theme_iiidem2\rate_limit::require_json('livequiz_submit', 10, 60);
    }

    switch ($action) {
        case 'getactive':
            $courseid = required_param('courseid', PARAM_INT);
            $cmid = required_param('cmid', PARAM_INT);
            require_sesskey();

            $course = get_course($courseid);
            require_course_login($course);

            $session = manager::get_active_session($courseid, $cmid);
            if (!$session) {
                echo json_encode(['status' => 'waiting']);
                exit;
            }

            $canparticipate = manager::user_can_participate($courseid);
            $canteach = manager::user_can_manage($courseid);

            if ($canparticipate && manager::has_submitted((int) $session->id, (int) $USER->id)) {
                echo json_encode([
                    'status' => 'submitted',
                    'sessionname' => $session->name,
                ]);
                exit;
            }

            if (!$canparticipate && !$canteach) {
                throw new moodle_exception('notenrolled', 'local_iiidem_livequiz');
            }

            $questions = [];
            foreach (manager::get_questions((int) $session->id) as $question) {
                $options = [];
                foreach (manager::question_options($question) as $opt) {
                    $options[] = [
                        'index' => $opt['index'],
                        'label' => format_string($opt['label']),
                    ];
                }
                $questions[] = [
                    'id' => (int) $question->id,
                    'text' => format_text($question->questiontext, FORMAT_PLAIN),
                    'options' => $options,
                ];
            }

            $payload = [
                'status' => 'active',
                'sessionid' => (int) $session->id,
                'sessionname' => format_string($session->name),
                'questions' => $questions,
                'canteach' => $canteach,
            ];

            if ($canteach) {
                $results = manager::get_teacher_results((int) $session->id);
                $payload['submittedcount'] = $results['submittedcount'];
                $payload['results'] = $results['rows'];
            }

            echo json_encode($payload);
            break;

        case 'submit':
            require_sesskey();
            $sessionid = required_param('sessionid', PARAM_INT);
            $answersraw = required_param('answers', PARAM_RAW);
            if (!is_string($answersraw) || strlen($answersraw) > 4096) {
                throw new moodle_exception('invalidaction', 'local_iiidem_livequiz');
            }

            $session = manager::get_session($sessionid);
            if (!$session) {
                throw new moodle_exception('invalidsession', 'local_iiidem_livequiz');
            }

            require_course_login(get_course($session->courseid));

            if (!manager::user_can_participate((int) $session->courseid)) {
                throw new moodle_exception('notenrolled', 'local_iiidem_livequiz');
            }

            $decoded = json_decode($answersraw, true, 32);
            if (!is_array($decoded) || count($decoded) > 50) {
                throw new moodle_exception('invalidaction', 'local_iiidem_livequiz');
            }

            $choices = [];
            foreach ($decoded as $questionid => $choiceindex) {
                $qid = (int) $questionid;
                $choice = (int) $choiceindex;
                if ($qid <= 0 || $choice < 0 || $choice > 3) {
                    throw new moodle_exception('invalidaction', 'local_iiidem_livequiz');
                }
                $choices[$qid] = $choice;
            }

            if (!manager::submit_answers($sessionid, (int) $USER->id, $choices)) {
                throw new moodle_exception('alreadysubmitted', 'local_iiidem_livequiz');
            }

            echo json_encode([
                'status' => 'ok',
                'message' => get_string('submitted', 'local_iiidem_livequiz'),
            ]);
            break;

        case 'teacherstats':
            require_sesskey();
            $sessionid = required_param('sessionid', PARAM_INT);
            $session = manager::get_session($sessionid);
            if (!$session) {
                throw new moodle_exception('invalidsession', 'local_iiidem_livequiz');
            }

            require_course_login(get_course($session->courseid));
            if (!manager::user_can_manage((int) $session->courseid)) {
                throw new moodle_exception('nopermission', 'local_iiidem_livequiz');
            }

            $results = manager::get_teacher_results($sessionid);
            echo json_encode([
                'status' => 'ok',
                'submittedcount' => $results['submittedcount'],
                'results' => $results['rows'],
            ]);
            break;

        default:
            throw new moodle_exception('invalidaction', 'local_iiidem_livequiz');
    }
} catch (Exception $e) {
    error_log('local_iiidem_livequiz api: ' . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => get_string('error'),
    ]);
}
