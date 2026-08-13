<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Serve class video files (only when viewer is allowed).
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param context $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @param array $options
 * @return bool
 */
function local_iiidem_classvideos_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    global $CFG;

    require_once($CFG->dirroot . '/local/iiidem_classvideos/classes/manager.php');

    if ($context->contextlevel != CONTEXT_COURSE) {
        return false;
    }
    require_login($course, false);

    if ($filearea !== 'video') {
        return false;
    }

    $itemid = (int) array_shift($args);
    $filename = array_pop($args);
    $filepath = $args ? '/' . implode('/', $args) . '/' : '/';

    $video = \local_iiidem_classvideos\manager::get_video($itemid);
    if (!$video || (int) $video->courseid !== (int) $course->id || !(int) $video->visible) {
        return false;
    }

    if (!\local_iiidem_classvideos\manager::user_can_watch($video)) {
        send_file_not_found();
    }

    $fs = get_file_storage();
    $file = $fs->get_file($context->id, 'local_iiidem_classvideos', 'video', $itemid, $filepath, $filename);
    if (!$file || $file->is_directory()) {
        return false;
    }

    send_stored_file($file, 0, 0, $forcedownload, $options);
}
