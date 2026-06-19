<?php
namespace local_iiidem_support;

defined('MOODLE_INTERNAL') || die();

/**
 * Support ticket and FAQ helpers.
 */
class manager {

    public const STATUS_OPEN = 'open';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_RESOLVED = 'resolved';
    public const STATUS_CLOSED = 'closed';

    /**
     * @return bool
     */
    public static function is_installed(): bool {
        global $DB;
        $dbman = $DB->get_manager();
        return $dbman->table_exists('local_iiidem_support_ticket');
    }

    /**
     * @return bool
     */
    public static function user_can_manage(): bool {
        return has_capability('local/iiidem_support:manage', \context_system::instance());
    }

    /**
     * @return array
     */
    public static function get_categories(): array {
        return [
            'general' => get_string('category_general', 'local_iiidem_support'),
            'technical' => get_string('category_technical', 'local_iiidem_support'),
            'course' => get_string('category_course', 'local_iiidem_support'),
            'payment' => get_string('category_payment', 'local_iiidem_support'),
            'certificate' => get_string('category_certificate', 'local_iiidem_support'),
        ];
    }

    /**
     * @param string $status
     * @return string
     */
    public static function status_label(string $status): string {
        $map = [
            self::STATUS_OPEN => 'status_open',
            self::STATUS_IN_PROGRESS => 'status_in_progress',
            self::STATUS_RESOLVED => 'status_resolved',
            self::STATUS_CLOSED => 'status_closed',
        ];
        $key = $map[$status] ?? 'status_open';
        return get_string($key, 'local_iiidem_support');
    }

    /**
     * @param int $userid
     * @return array
     */
    public static function get_user_faqs(int $userid): array {
        global $DB, $CFG;

        if (!$DB->get_manager()->table_exists('local_coursefaq')) {
            return [];
        }

        require_once($CFG->libdir . '/enrollib.php');

        $courses = enrol_get_users_courses($userid, true, 'id,fullname,shortname', 'visible DESC, fullname ASC');
        $faqs = [];

        foreach ($courses as $course) {
            $records = $DB->get_records('local_coursefaq', ['courseid' => $course->id], 'id ASC');
            foreach ($records as $faq) {
                $context = \context_course::instance($course->id);
                $faqs[] = (object) [
                    'id' => (int) $faq->id,
                    'courseid' => (int) $course->id,
                    'coursename' => format_string($course->fullname, true, ['context' => $context]),
                    'question' => $faq->question,
                    'answer' => format_text($faq->answer, FORMAT_HTML, ['context' => $context]),
                    'answerplain' => strip_tags($faq->answer),
                ];
            }
        }

        return $faqs;
    }

    /**
     * @param int $userid
     * @param string $query
     * @param int $limit
     * @return array
     */
    public static function search_faqs(int $userid, string $query, int $limit = 5): array {
        $query = self::normalize_search_text($query);
        if ($query === '') {
            return [];
        }

        $scored = [];
        foreach (self::get_user_faqs($userid) as $faq) {
            $haystack = self::normalize_search_text($faq->question . ' ' . $faq->answerplain);
            $score = self::score_faq_match($query, $haystack, $faq->question);
            if ($score > 0) {
                $scored[] = ['score' => $score, 'faq' => $faq];
            }
        }

        usort($scored, static function(array $a, array $b): int {
            return $b['score'] <=> $a['score'];
        });

        $matches = [];
        foreach ($scored as $item) {
            $faq = $item['faq'];
            $matches[] = [
                'id' => $faq->id,
                'question' => $faq->question,
                'answer' => $faq->answer,
                'coursename' => $faq->coursename,
            ];
            if (count($matches) >= $limit) {
                break;
            }
        }

        return $matches;
    }

    /**
     * @param string $text
     * @return string
     */
    protected static function normalize_search_text(string $text): string {
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5);
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        return \core_text::strtolower(trim($text));
    }

    /**
     * @param string $query
     * @param string $haystack
     * @param string $question
     * @return int
     */
    protected static function score_faq_match(string $query, string $haystack, string $question): int {
        if (strpos($haystack, $query) !== false) {
            $questionlower = self::normalize_search_text($question);
            return 100 + (strpos($questionlower, $query) !== false ? 20 : 0);
        }

        $words = preg_split('/\s+/u', $query, -1, PREG_SPLIT_NO_EMPTY);
        if (!$words) {
            return 0;
        }

        $significant = array_values(array_filter($words, static function(string $word): bool {
            return \core_text::strlen($word) >= 3;
        }));

        if (empty($significant)) {
            return 0;
        }

        $matched = 0;
        foreach ($significant as $word) {
            if (strpos($haystack, $word) !== false) {
                $matched++;
            }
        }

        if ($matched === 0) {
            return 0;
        }

        if ($matched === count($significant)) {
            return 60 + $matched;
        }

        return 30 + $matched;
    }

    /**
     * @param int $userid
     * @param stdClass $data
     * @return int
     */
    public static function create_ticket(int $userid, \stdClass $data): int {
        global $DB;

        $now = time();
        $record = (object) [
            'userid' => $userid,
            'courseid' => (int) ($data->courseid ?? 0),
            'category' => $data->category ?? 'general',
            'subject' => $data->subject,
            'message' => $data->message,
            'status' => self::STATUS_OPEN,
            'timecreated' => $now,
            'timemodified' => $now,
        ];

        $ticketid = $DB->insert_record('local_iiidem_support_ticket', $record);
        self::notify_support_team($ticketid);

        return $ticketid;
    }

    /**
     * @param int $ticketid
     * @return void
     */
    protected static function notify_support_team(int $ticketid): void {
        global $DB;

        $ticket = $DB->get_record('local_iiidem_support_ticket', ['id' => $ticketid], '*', MUST_EXIST);
        $user = $DB->get_record('user', ['id' => $ticket->userid], '*', MUST_EXIST);
        $support = \core_user::get_support_user();

        $subject = format_string($ticket->subject);
        $body = "New support ticket from {$user->firstname} {$user->lastname} ({$user->email})\n\n";
        $body .= "Subject: {$subject}\n";
        $body .= "Category: {$ticket->category}\n\n";
        $body .= $ticket->message . "\n\n";
        $body .= "View: " . (new \moodle_url('/local/iiidem_support/manage.php', ['ticketid' => $ticketid]))->out(false);

        email_to_user($support, $support, 'Support ticket: ' . $subject, $body);
    }

    /**
     * @param int $userid
     * @return array
     */
    public static function get_user_tickets(int $userid): array {
        global $DB;

        $records = $DB->get_records('local_iiidem_support_ticket', ['userid' => $userid], 'timecreated DESC');
        return self::format_tickets_for_list($records);
    }

    /**
     * @param int $ticketid
     * @param int $userid
     * @return stdClass|null
     */
    public static function get_user_ticket(int $ticketid, int $userid): ?\stdClass {
        global $DB;

        $ticket = $DB->get_record('local_iiidem_support_ticket', ['id' => $ticketid, 'userid' => $userid]);
        return $ticket ? self::format_ticket_detail($ticket) : null;
    }

    /**
     * @param string $status
     * @return int
     */
    public static function count_user_open_tickets(int $userid, string $status = ''): int {
        global $DB;

        $params = ['userid' => $userid];
        if ($status !== '') {
            $params['status'] = $status;
            return $DB->count_records('local_iiidem_support_ticket', $params);
        }

        return $DB->count_records_select(
            'local_iiidem_support_ticket',
            'userid = :userid AND status IN (:open, :progress)',
            [
                'userid' => $userid,
                'open' => self::STATUS_OPEN,
                'progress' => self::STATUS_IN_PROGRESS,
            ]
        );
    }

    /**
     * @return array
     */
    public static function get_all_tickets(): array {
        global $DB;

        $records = $DB->get_records('local_iiidem_support_ticket', null, 'timecreated DESC');
        return self::format_tickets_for_list($records, true);
    }

    /**
     * @param int $ticketid
     * @return stdClass|null
     */
    public static function get_ticket_for_admin(int $ticketid): ?\stdClass {
        global $DB;

        $ticket = $DB->get_record('local_iiidem_support_ticket', ['id' => $ticketid]);
        return $ticket ? self::format_ticket_detail($ticket, true) : null;
    }

    /**
     * @param int $ticketid
     * @param string $reply
     * @param string $status
     * @return void
     */
    public static function admin_reply(int $ticketid, string $reply, string $status): void {
        global $DB, $USER;

        $ticket = $DB->get_record('local_iiidem_support_ticket', ['id' => $ticketid], '*', MUST_EXIST);
        $now = time();

        $ticket->adminreply = $reply;
        $ticket->repliedby = $USER->id;
        $ticket->timereplied = $now;
        $ticket->timemodified = $now;
        $ticket->status = $status;
        $DB->update_record('local_iiidem_support_ticket', $ticket);

        $student = $DB->get_record('user', ['id' => $ticket->userid], '*', MUST_EXIST);
        self::notify_student_ticket_reply($student, $ticket, $reply, $status);
    }

    /**
     * Email + Moodle message (speech bubble) when admin replies.
     *
     * @param stdClass $student
     * @param stdClass $ticket
     * @param string $reply
     * @param string $status
     * @return void
     */
    protected static function notify_student_ticket_reply(\stdClass $student, \stdClass $ticket, string $reply, string $status): void {
        global $CFG, $DB, $USER;

        $ticketurl = (new \moodle_url('/local/iiidem_support/ticket.php', ['id' => $ticket->id]))->out(false);
        $subjectline = format_string($ticket->subject);
        $statustext = self::status_label($status);

        $from = \core_user::get_support_user();
        if (\core_user::is_real_user($USER->id)) {
            $from = $DB->get_record('user', ['id' => $USER->id], '*', MUST_EXIST);
        }

        $emailsubject = get_string('ticketreplyemailsubject', 'local_iiidem_support', $subjectline);
        $emailbody = get_string('ticketreplyemailbody', 'local_iiidem_support', (object) [
            'status' => $statustext,
            'reply' => $reply,
            'url' => $ticketurl,
        ]);
        email_to_user($student, \core_user::get_support_user(), $emailsubject, $emailbody);

        if (empty($CFG->messaging)) {
            return;
        }

        require_once($CFG->dirroot . '/message/lib.php');

        $messageparams = (object) [
            'subject' => $subjectline,
            'status' => $statustext,
            'reply' => s($reply),
            'url' => $ticketurl,
        ];
        $html = get_string('ticketreplyinstant', 'local_iiidem_support', $messageparams);

        message_post_message($from, $student, $html, FORMAT_HTML);
    }

    /**
     * @param int $ticketid
     * @param string $status
     * @return void
     */
    public static function set_status(int $ticketid, string $status): void {
        global $DB;

        $ticket = $DB->get_record('local_iiidem_support_ticket', ['id' => $ticketid], '*', MUST_EXIST);
        $ticket->status = $status;
        $ticket->timemodified = time();
        $DB->update_record('local_iiidem_support_ticket', $ticket);
    }

    /**
     * @param array $records
     * @param bool $includeuser
     * @return array
     */
    protected static function format_tickets_for_list(array $records, bool $includeuser = false): array {
        global $DB;

        $items = [];
        foreach ($records as $ticket) {
            $item = [
                'id' => (int) $ticket->id,
                'subject' => $ticket->subject,
                'category' => $ticket->category,
                'categorylabel' => self::get_categories()[$ticket->category] ?? $ticket->category,
                'status' => $ticket->status,
                'statuslabel' => self::status_label($ticket->status),
                'statusclass' => str_replace('_', '-', $ticket->status),
                'date' => userdate($ticket->timecreated, get_string('strftimedatetimeshort', 'langconfig')),
                'url' => (new \moodle_url('/local/iiidem_support/ticket.php', ['id' => $ticket->id]))->out(false),
                'hasreply' => !empty($ticket->adminreply),
            ];

            if ($includeuser) {
                $user = $DB->get_record('user', ['id' => $ticket->userid], 'id,firstname,lastname,email', MUST_EXIST);
                $item['username'] = fullname($user);
                $item['useremail'] = $user->email;
            }

            $items[] = $item;
        }

        return $items;
    }

    /**
     * @param stdClass $ticket
     * @param bool $admin
     * @return stdClass
     */
    protected static function format_ticket_detail(\stdClass $ticket, bool $admin = false): \stdClass {
        global $DB;

        $course = null;
        if (!empty($ticket->courseid)) {
            $course = $DB->get_record('course', ['id' => $ticket->courseid], 'id,fullname', IGNORE_MISSING);
        }

        $ticket->categorylabel = self::get_categories()[$ticket->category] ?? $ticket->category;
        $ticket->statuslabel = self::status_label($ticket->status);
        $ticket->statusclass = str_replace('_', '-', $ticket->status);
        $ticket->datecreated = userdate($ticket->timecreated, get_string('strftimedatetimeshort', 'langconfig'));
        $ticket->hasreply = !empty($ticket->adminreply);
        $ticket->coursename = $course ? format_string($course->fullname) : '';
        $ticket->messageformatted = format_text($ticket->message, FORMAT_PLAIN);
        $ticket->adminreplyformatted = $ticket->adminreply
            ? format_text($ticket->adminreply, FORMAT_PLAIN)
            : '';
        $ticket->datereplied = $ticket->timereplied
            ? userdate($ticket->timereplied, get_string('strftimedatetimeshort', 'langconfig'))
            : '';

        if ($admin) {
            $user = $DB->get_record('user', ['id' => $ticket->userid], 'id,firstname,lastname,email', MUST_EXIST);
            $ticket->username = fullname($user);
            $ticket->useremail = $user->email;
        }

        return $ticket;
    }

    /**
     * @param int $userid
     * @return array
     */
    public static function get_dashboard_context(int $userid): array {
        global $CFG;

        if (!self::is_installed()) {
            return ['hassupport' => false];
        }

        $faqsurl = (new \moodle_url('/local/iiidem_support/faqs.php'))->out(false);
        $newurl = (new \moodle_url('/local/iiidem_support/ticket_new.php'))->out(false);
        $ticketsurl = (new \moodle_url('/local/iiidem_support/tickets.php'))->out(false);
        $contacturl = $CFG->wwwroot . '/contact-us/';
        $opentickets = self::count_user_open_tickets($userid);

        return [
            'hassupport' => true,
            'supportlinks' => [
                [
                    'key' => 'faqs',
                    'icon' => 'fa-circle-question',
                    'label' => get_string('viewfaqs', 'local_iiidem_support'),
                    'url' => $faqsurl,
                ],
                [
                    'key' => 'raise',
                    'icon' => 'fa-ticket',
                    'label' => get_string('raiseticket', 'local_iiidem_support'),
                    'url' => $newurl,
                ],
                [
                    'key' => 'tickets',
                    'icon' => 'fa-list',
                    'label' => get_string('mytickets', 'local_iiidem_support'),
                    'url' => $ticketsurl,
                    'badge' => $opentickets > 0 ? (string) $opentickets : '',
                    'hasbadge' => $opentickets > 0,
                ],
                [
                    'key' => 'contact',
                    'icon' => 'fa-headset',
                    'label' => get_string('contactsupport', 'local_iiidem_support'),
                    'url' => $contacturl,
                ],
            ],
            'supportfaqsurl' => $faqsurl,
            'supportticketurl' => $newurl,
            'supportchatapiurl' => (new \moodle_url('/local/iiidem_support/api.php'))->out(false),
        ];
    }
}
