<?php
define('CLI_SCRIPT', true);
require __DIR__ . '/../config.php';
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/theme/iiidem2/lib.php');

global $PAGE, $OUTPUT, $DB;

$featured = (string) get_config('theme_iiidem2', 'featuredinstructors');
$max = get_config('theme_iiidem2', 'maxinstructors');
cli_writeln('featuredinstructors=' . str_replace("\n", ' | ', $featured));
cli_writeln('maxinstructors=' . (string) $max);

foreach ([4, 7, 8] as $courseid) {
    $course = $DB->get_record('course', ['id' => $courseid]);
    if (!$course) {
        cli_writeln("--- course {$courseid} missing ---");
        continue;
    }
    cli_writeln("--- course {$courseid} [{$course->shortname}] ---");
    cli_writeln('shared_readings=' . (\theme_iiidem2\shared_readings::is_enabled($courseid) ? 'yes' : 'no'));
    $PAGE->set_context(context_course::instance($courseid));
    $display = theme_iiidem2_get_course_display_context($course);
    cli_writeln('shows_professors=' . (theme_iiidem2_course_shows_meet_professors($courseid) ? 'yes' : 'no'));
    cli_writeln('hasinstructors=' . (!empty($display['hasinstructors']) ? '1' : '0'));
    foreach ($display['instructordata'] ?? [] as $row) {
        cli_writeln('  shown id=' . $row['id'] . ' name=' . $row['name']);
    }
    $featuredids = theme_iiidem2_get_featured_instructor_ids($courseid);
    cli_writeln('featured=' . implode(',', $featuredids));
    $context = context_course::instance($courseid);
    $roles = $DB->get_records_list('role', 'shortname', ['editingteacher', 'teacher', 'manager']);
    foreach ($roles as $role) {
        $users = get_role_users($role->id, $context, false, 'u.id, u.firstname, u.lastname');
        foreach ($users as $u) {
            cli_writeln('  role=' . $role->shortname . ' userid=' . $u->id . ' name=' . fullname($u)
                . ' admin=' . (is_siteadmin((int) $u->id) ? 'yes' : 'no'));
        }
    }
}
