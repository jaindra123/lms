<?php
defined('MOODLE_INTERNAL') || die();

function local_studentredirect_after_config() {
    global $USER, $PAGE;

    if (!isloggedin() || isguestuser()) {
        return;
    }

    // prevent breaking admin + system pages
    if (CLI_SCRIPT || AJAX_SCRIPT) {
        return;
    }

    // avoid redirect loops
    if ($PAGE->url && strpos($PAGE->url->out(false), 'course/view.php?id=4') !== false) {
        return;
    }

    $roles = get_user_roles(context_system::instance(), $USER->id);

    foreach ($roles as $role) {
        if ($role->shortname === 'student') {
            redirect(new moodle_url('/course/view.php', ['id' => 4]));
        }
    }
}