<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Whether the user may submit a review for this course.
 *
 * @param stdClass $course
 * @param int|null $userid
 * @return bool
 */
function local_coursereviews_user_can_review(stdClass $course, ?int $userid = null): bool {
    global $USER, $CFG;

    require_once($CFG->libdir . '/enrollib.php');

    if (!isloggedin() || isguestuser()) {
        return false;
    }

    if ($userid === null) {
        $userid = (int) $USER->id;
    }

    if ((int) $course->id === SITEID) {
        return false;
    }

    $context = context_course::instance($course->id);
    return is_enrolled($context, $userid, '', true);
}

/**
 * Display name for a reviewer (first name + last initial).
 *
 * @param stdClass $user
 * @return string
 */
function local_coursereviews_format_reviewer_name(stdClass $user): string {
    $firstname = format_string($user->firstname);
    $initial = !empty($user->lastname) ? mb_strtoupper(mb_substr($user->lastname, 0, 1)) . '.' : '';
    return trim($firstname . ($initial ? ' ' . $initial : ''));
}

/**
 * Star rating rows for Mustache templates.
 *
 * @param int $rating
 * @return array
 */
function local_coursereviews_get_star_rating_rows(int $rating): array {
    $rating = max(1, min(5, $rating));
    $rows = [];
    for ($i = 1; $i <= 5; $i++) {
        $rows[] = ['filled' => $i <= $rating];
    }
    return $rows;
}

/**
 * Subtitle such as "Learner since 2024".
 *
 * @param int $userid
 * @param int $courseid
 * @return string
 */
function local_coursereviews_get_reviewer_subtitle(int $userid, int $courseid): string {
    global $DB;

    $since = (int) $DB->get_field_sql(
        "SELECT MIN(ue.timestart)
           FROM {user_enrolments} ue
           JOIN {enrol} e ON e.id = ue.enrolid
          WHERE ue.userid = ? AND e.courseid = ? AND ue.status = ?",
        [$userid, $courseid, ENROL_USER_ACTIVE]
    );

    if ($since <= 0) {
        $since = (int) $DB->get_field('user', 'timecreated', ['id' => $userid]);
    }

    if ($since <= 0) {
        return '';
    }

    return get_string('studentreviewssince', 'theme_iiidem2', userdate($since, '%Y'));
}

/**
 * Save or update a student review.
 *
 * @param int $courseid
 * @param int $userid
 * @param int $rating
 * @param string $reviewtext
 * @return bool
 */
function local_coursereviews_save_review(int $courseid, int $userid, int $rating, string $reviewtext): bool {
    global $DB;

    $rating = max(1, min(5, $rating));
    $reviewtext = trim($reviewtext);
    if ($reviewtext === '') {
        return false;
    }

    $now = time();
    $existing = $DB->get_record('local_coursereviews', ['courseid' => $courseid, 'userid' => $userid]);

    if ($existing) {
        $existing->rating = $rating;
        $existing->reviewtext = $reviewtext;
        $existing->timemodified = $now;
        return (bool) $DB->update_record('local_coursereviews', $existing);
    }

    $record = (object) [
        'courseid' => $courseid,
        'userid' => $userid,
        'rating' => $rating,
        'reviewtext' => $reviewtext,
        'timecreated' => $now,
        'timemodified' => $now,
    ];

    return (bool) $DB->insert_record('local_coursereviews', $record);
}

/**
 * Mustache context for student reviews on the course page.
 *
 * @param stdClass $course
 * @return array
 */
function local_coursereviews_get_course_context(stdClass $course): array {
    global $DB, $USER, $CFG, $PAGE;

    require_once($CFG->libdir . '/filelib.php');

    $courseid = (int) $course->id;
    $defaults = [
        'hasstudentreviews' => false,
        'showstudentreviewsection' => false,
        'studentreviewstitle' => get_string('studentreviewstitle', 'theme_iiidem2'),
        'hasreviewitems' => false,
        'reviewitems' => [],
        'reviewcount' => 0,
        'averagerating' => 0,
        'averageratingdisplay' => '0',
        'hasaveragerating' => false,
        'canreview' => false,
        'hasuserreview' => false,
        'needlogin' => !isloggedin() || isguestuser(),
        'needenrol' => false,
        'reviewformurl' => '',
        'reviewreturnurl' => (new moodle_url('/course/view.php', ['id' => $courseid]))->out(false) . '#student-reviews',
        'sesskey' => sesskey(),
        'loginurl' => (new moodle_url('/login/index.php', [
            'wantsurl' => (new moodle_url('/course/view.php', ['id' => $courseid]))->out(false) . '#student-reviews',
        ]))->out(false),
    ];

    if ($courseid === SITEID) {
        return $defaults;
    }

    $defaults['showstudentreviewsection'] = true;
    $defaults['reviewformurl'] = (new moodle_url('/local/coursereviews/submit.php'))->out(false);
    $defaults['courseid'] = $courseid;

    $canreview = local_coursereviews_user_can_review($course);
    $defaults['canreview'] = $canreview;
    $defaults['needenrol'] = isloggedin() && !isguestuser() && !$canreview;

    $records = $DB->get_records('local_coursereviews', ['courseid' => $courseid], 'timemodified DESC');
    $items = [];
    $totalrating = 0;

    foreach ($records as $record) {
        $user = $DB->get_record('user', ['id' => $record->userid], 'id, firstname, lastname, picture, imagealt', IGNORE_MISSING);
        if (!$user) {
            continue;
        }

        $subtitle = local_coursereviews_get_reviewer_subtitle((int) $user->id, $courseid);
        $stars = (int) $record->rating;
        $totalrating += $stars;

        $userpicture = new user_picture($user);
        $userpicture->size = 50;

        $items[] = [
            'name' => local_coursereviews_format_reviewer_name($user),
            'subtitle' => $subtitle,
            'hassubtitle' => $subtitle !== '',
            'quote' => format_text($record->reviewtext, FORMAT_PLAIN),
            'stars' => $stars,
            'starrating' => local_coursereviews_get_star_rating_rows($stars),
            'hasimage' => true,
            'imageurl' => $userpicture->get_url($PAGE ?? null)->out(false),
            'imagealt' => local_coursereviews_format_reviewer_name($user),
            'isown' => isloggedin() && !isguestuser() && (int) $USER->id === (int) $user->id,
        ];
    }

    $count = count($items);
    $defaults['reviewitems'] = $items;
    $defaults['hasreviewitems'] = $count > 0;
    $defaults['reviewcount'] = $count;
    $defaults['hasstudentreviews'] = $count > 0 || $canreview || $defaults['needlogin'] || $defaults['needenrol'];

    if ($count > 0) {
        $average = round($totalrating / $count, 1);
        $defaults['averagerating'] = $average;
        $defaults['averageratingdisplay'] = number_format($average, 1);
        $defaults['hasaveragerating'] = true;
        $defaults['averagestarrating'] = local_coursereviews_get_star_rating_rows((int) round($average));
    }

    if ($canreview) {
        $own = $DB->get_record('local_coursereviews', ['courseid' => $courseid, 'userid' => (int) $USER->id]);
        if ($own) {
            $defaults['hasuserreview'] = true;
            $defaults['userrating'] = (int) $own->rating;
            $defaults['userreviewtext'] = $own->reviewtext;
            $defaults['userreviewstarrating'] = local_coursereviews_get_star_rating_rows((int) $own->rating);
        } else {
            $defaults['userrating'] = 5;
        }

        $userrating = (int) $defaults['userrating'];
        $starpicker = [];
        for ($i = 1; $i <= 5; $i++) {
            $starpicker[] = [
                'value' => $i,
                'active' => $i <= $userrating,
                'label' => $i,
            ];
        }
        $defaults['starpicker'] = $starpicker;
    }

    return $defaults;
}
