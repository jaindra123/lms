<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Theme functions.
 *
 * @package   theme_iiidem2
 * @copyright 2016 Frédéric Massart
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Inject additional SCSS.
 *
 * @param theme_config $theme The theme config object.
 * @return string
 */
function theme_iiidem2_get_extra_scss($theme) {
    $content = '';
    $imageurl = $theme->setting_file_url('backgroundimage', 'backgroundimage');

    if (!empty($imageurl)) {
        $content .= '@media (min-width: 768px) {';
        $content .= 'body { ';
        $content .= "background-image: url('$imageurl'); background-size: cover;";
        $content .= ' } }';
    }

    $loginbackgroundimageurl = $theme->setting_file_url('loginbackgroundimage', 'loginbackgroundimage');
    if (!empty($loginbackgroundimageurl)) {
        $content .= 'body.pagelayout-login #page { ';
        $content .= "background-image: url('$loginbackgroundimageurl'); background-size: cover;";
        $content .= ' }';
    }

    return !empty($theme->settings->scss) ? "{$theme->settings->scss}  \n  {$content}" : $content;
}

/**
 * Serves theme setting files (logos, slider images, presets, etc.).
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
function theme_iiidem2_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    if ($context->contextlevel != CONTEXT_SYSTEM) {
        send_file_not_found();
    }

    $theme = theme_config::load('iiidem2');
    if (!array_key_exists('cacheability', $options)) {
        $options['cacheability'] = 'public';
    }

    return $theme->setting_file_serve($filearea, $args, $forcedownload, $options);
}

/**
 * User drawer preferences (Boost-compatible).
 *
 * @return array[]
 */
function theme_iiidem2_user_preferences(): array {
    return [
        'drawer-open-block' => [
            'type' => PARAM_BOOL,
            'null' => NULL_NOT_ALLOWED,
            'default' => false,
            'permissioncallback' => [core_user::class, 'is_current_user'],
        ],
        'drawer-open-index' => [
            'type' => PARAM_BOOL,
            'null' => NULL_NOT_ALLOWED,
            'default' => true,
            'permissioncallback' => [core_user::class, 'is_current_user'],
        ],
    ];
}

/**
 * Returns the main SCSS content.
 *
 * @param theme_config $theme The theme config object.
 * @return string
 */
function theme_iiidem2_get_main_scss_content($theme) {
    global $CFG;

    $scss = '';
    $filename = !empty($theme->settings->preset) ? $theme->settings->preset : null;
    $fs = get_file_storage();
    $context = context_system::instance();

    if ($filename == 'default.scss') {
        $scss .= file_get_contents($CFG->dirroot . '/theme/iiidem2/scss/preset/default.scss');
    } else if ($filename == 'plain.scss') {
        $scss .= file_get_contents($CFG->dirroot . '/theme/iiidem2/scss/preset/plain.scss');
    } else if ($filename && ($presetfile = $fs->get_file($context->id, 'theme_iiidem2', 'preset', 0, '/', $filename))) {
        $scss .= $presetfile->get_content();
    } else {
        $scss .= file_get_contents($CFG->dirroot . '/theme/iiidem2/scss/preset/default.scss');
    }

    return $scss;
}

/**
 * Get compiled css.
 *
 * @return string
 */
function theme_iiidem2_get_precompiled_css() {
    global $CFG;
    return file_get_contents($CFG->dirroot . '/theme/iiidem2/style/moodle.css');
}

/**
 * Get SCSS to prepend.
 *
 * @param theme_config $theme The theme config object.
 * @return string
 */
function theme_iiidem2_get_pre_scss($theme) {
    $scss = '';
    $configurable = [
        'brandcolor' => ['primary'],
    ];

    foreach ($configurable as $configkey => $targets) {
        $value = isset($theme->settings->{$configkey}) ? $theme->settings->{$configkey} : null;
        if (empty($value)) {
            continue;
        }
        foreach ((array) $targets as $target) {
            $scss .= '$' . $target . ': ' . $value . ";\n";
        }
    }

    if (!empty($theme->settings->scsspre)) {
        $scss .= $theme->settings->scsspre;
    }

    return $scss;
}

/**
 * Raw footer settings from theme config (no URLs).
 *
 * @return array
 */
function theme_iiidem2_get_footer_settings(): array {
    return [
        'copyrighttext' => get_config('theme_iiidem2', 'copyrighttext'),
        'facebookurl' => get_config('theme_iiidem2', 'facebookurl'),
        'twitterurl' => get_config('theme_iiidem2', 'twitterurl'),
        'youtubeurl' => get_config('theme_iiidem2', 'youtubeurl'),
        'instagramurl' => get_config('theme_iiidem2', 'instagramurl'),
        'footerdescription' => get_config('theme_iiidem2', 'description'),
        'address' => get_config('theme_iiidem2', 'address'),
        'email' => get_config('theme_iiidem2', 'email'),
        'phone' => get_config('theme_iiidem2', 'phone'),
    ];
}

/**
 * Footer + global template context for layouts.
 *
 * @return array
 */
function theme_iiidem2_get_footer_context(): array {
    global $SITE, $CFG;

    $theme = theme_config::load('iiidem2');
    $systemcontext = context_system::instance();

    return array_merge(theme_iiidem2_get_footer_settings(), [
        'sitename' => format_string($SITE->shortname, true, [
            'context' => $systemcontext,
            'escape' => false,
        ]),
        'config' => [
            'wwwroot' => $CFG->wwwroot,
            'homeurl' => new moodle_url('/'),
        ],
        'headerlogo' => $theme->setting_file_url('headerlogo', 'headerlogo'),
        'footerlogo' => $theme->setting_file_url('footerlogo', 'footerlogo'),
    ]);
}

/**
 * Merge footer context into a layout template context array.
 *
 * @param array $templatecontext
 * @return array
 */
function theme_iiidem2_merge_footer_context(array $templatecontext): array {
    return array_merge($templatecontext, theme_iiidem2_get_footer_context(), [
        'hasenrollmodal' => !empty($templatecontext['hasenrollmodal']),
        'hasloginmodal' => !empty($templatecontext['hasloginmodal']),
    ]);
}

/**
 * Preview HTML for a File resource activity (video, audio, PDF, image, or download link).
 *
 * @param cm_info $cm Course module (modname must be resource).
 * @return string Safe HTML for curriculum preview panel.
 */
function theme_iiidem2_get_resource_preview_html(cm_info $cm): string {
    global $DB, $CFG, $PAGE;

    if ($cm->modname !== 'resource') {
        return '';
    }

    require_once($CFG->dirroot . '/mod/resource/locallib.php');
    require_once($CFG->libdir . '/resourcelib.php');

    $resource = $DB->get_record('resource', ['id' => $cm->instance], '*', IGNORE_MISSING);
    if (!$resource) {
        return '';
    }

    $context = context_module::instance($cm->id);
    $fs = get_file_storage();
    $files = $fs->get_area_files(
        $context->id,
        'mod_resource',
        'content',
        0,
        'sortorder DESC, id ASC',
        false
    );
    if (empty($files)) {
        return '';
    }

    $file = reset($files);
    $mimetype = $file->get_mimetype();
    $title = format_string($cm->name, true, ['context' => $context]);
    $fileurl = moodle_url::make_pluginfile_url(
        $context->id,
        'mod_resource',
        'content',
        $resource->revision,
        $file->get_filepath(),
        $file->get_filename()
    );

    $mediamanager = core_media_manager::instance($PAGE);
    $embedoptions = [
        core_media_manager::OPTION_TRUSTED => true,
        core_media_manager::OPTION_BLOCK => true,
    ];

    if (file_mimetype_in_typegroup($mimetype, 'web_image')) {
        $code = resourcelib_embed_image($fileurl->out(false), $title);
    } else if ($mimetype === 'application/pdf') {
        $clicktoopen = resource_get_clicktoopen($file, $resource->revision);
        $code = resourcelib_embed_pdf($fileurl->out(false), $title, $clicktoopen);
    } else if ($mediamanager->can_embed_url($fileurl, $embedoptions)) {
        $code = $mediamanager->embed_url($fileurl, $title, 0, 400, $embedoptions);
    } else if (file_mimetype_in_typegroup($mimetype, 'web_video') || file_mimetype_in_typegroup($mimetype, 'web_audio')) {
        $source = html_writer::empty_tag('source', ['src' => $fileurl->out(false), 'type' => $mimetype]);
        $tag = file_mimetype_in_typegroup($mimetype, 'web_audio') ? 'audio' : 'video';
        $code = html_writer::tag($tag, $source, [
            'controls' => true,
            'class' => 'iiidem-curriculum-media w-100',
            'preload' => 'metadata',
        ]);
    } else {
        $code = html_writer::div(
            html_writer::link(
                $fileurl,
                $file->get_filename(),
                ['class' => 'btn btn-outline-primary']
            ),
            'file-preview'
        );
    }

    return format_text($code, FORMAT_HTML, ['noclean' => true, 'context' => $context]);
}

/**
 * Dashboard role key: admin, teacher, or student.
 *
 * @param int|null $userid
 * @return string
 */
function theme_iiidem2_get_user_dashboard_role(?int $userid = null): string {
    global $USER, $CFG, $DB;

    require_once($CFG->libdir . '/accesslib.php');

    if ($userid === null) {
        $userid = $USER->id;
    }

    if (is_siteadmin($userid)) {
        return 'admin';
    }

    $systemcontext = context_system::instance();

    if (has_capability('moodle/site:config', $systemcontext, $userid)) {
        return 'admin';
    }

    $roles = get_user_roles($systemcontext, $userid, false);
    foreach ($roles as $role) {
        if (in_array($role->shortname, ['manager', 'coursecreator'], true)) {
            return 'admin';
        }
        if (in_array($role->shortname, ['editingteacher', 'teacher'], true)) {
            return 'teacher';
        }
    }

    if (has_capability('moodle/course:create', $systemcontext, $userid)) {
        return 'teacher';
    }

    if (theme_iiidem2_user_has_teacher_role($userid)) {
        return 'teacher';
    }

    return 'student';
}

/**
 * Whether the user is a teacher/instructor in any course (not only at site level).
 *
 * @param int $userid
 * @return bool
 */
function theme_iiidem2_user_has_teacher_role(int $userid): bool {
    global $DB, $CFG;

    require_once($CFG->libdir . '/enrollib.php');

    $sql = "SELECT 1
              FROM {role_assignments} ra
              JOIN {role} r ON r.id = ra.roleid
             WHERE ra.userid = :userid
               AND r.shortname IN ('editingteacher', 'teacher')";

    if ($DB->record_exists_sql($sql, ['userid' => $userid])) {
        return true;
    }

    $courses = enrol_get_users_courses($userid, true);
    foreach ($courses as $course) {
        if ((int) $course->id === SITEID) {
            continue;
        }
        $context = context_course::instance($course->id);
        if (has_capability('moodle/course:manageactivities', $context, $userid) ||
                has_capability('moodle/grade:edit', $context, $userid)) {
            return true;
        }
    }

    return false;
}

/**
 * URL of the role-based theme dashboard.
 *
 * @return moodle_url
 */
function theme_iiidem2_get_dashboard_url(): moodle_url {
    return new moodle_url('/theme/iiidem2/dashboard/index.php');
}

/**
 * Whether a post-login URL is a generic landing page we should override.
 *
 * @param string $url
 * @return bool
 */
function theme_iiidem2_is_generic_login_landing(string $url): bool {
    global $CFG;

    if ($url === '' || $url === $CFG->wwwroot || $url === $CFG->wwwroot . '/') {
        return true;
    }

    $path = parse_url($url, PHP_URL_PATH);
    if ($path === false || $path === null) {
        return true;
    }

    $path = rtrim($path, '/');
    $generic = [
        '',
        '/',
        '/my',
        '/my/index.php',
        '/login/index.php',
    ];

    return in_array($path, $generic, true);
}

/**
 * Recent course / site announcements for the student dashboard.
 *
 * @param int|null $userid
 * @param int $limit Maximum announcements to return.
 * @return array{hasannouncements: bool, announcements: array}
 */
function theme_iiidem2_get_student_announcements(?int $userid = null, int $limit = 10): array {
    global $CFG, $DB;

    if ($userid === null) {
        global $USER;
        $userid = $USER->id;
    }

    $empty = [
        'hasannouncements' => false,
        'announcements' => [],
    ];

    $forumplugin = core_plugin_manager::instance()->get_plugin_info('mod_forum');
    if (!$forumplugin || !$forumplugin->is_enabled()) {
        return $empty;
    }

    require_once($CFG->dirroot . '/mod/forum/lib.php');
    require_once($CFG->libdir . '/enrollib.php');

    $courses = enrol_get_users_courses($userid, true, 'id, fullname, shortname, visible');
    if (!isset($courses[SITEID])) {
        $sitecourse = get_course(SITEID, false);
        if ($sitecourse && $sitecourse->visible) {
            $courses[SITEID] = $sitecourse;
        }
    }

    $candidates = [];
    $percourse = 3;

    foreach ($courses as $course) {
        if (!$course->visible) {
            continue;
        }

        $forum = $DB->get_record('forum', ['course' => $course->id, 'type' => 'news'], '*', IGNORE_MULTIPLE);
        if (!$forum) {
            continue;
        }

        try {
            $modinfo = get_fast_modinfo($course, $userid);
        } catch (Exception $e) {
            continue;
        }

        if (empty($modinfo->instances['forum'][$forum->id])) {
            continue;
        }

        $cm = $modinfo->instances['forum'][$forum->id];
        if (!$cm->uservisible) {
            continue;
        }

        $context = context_module::instance($cm->id);
        if (!has_capability('mod/forum:viewdiscussion', $context, $userid)) {
            continue;
        }

        $sort = forum_get_default_sort_order(true, 'p.modified', 'd', false);
        $discussions = forum_get_discussions(
            $cm,
            $sort,
            true,
            -1,
            $percourse,
            false,
            -1,
            0,
            FORUM_POSTS_ALL_USER_GROUPS
        );

        if (!$discussions) {
            continue;
        }

        $coursename = format_string($course->fullname, true, ['context' => context_course::instance($course->id)]);

        foreach ($discussions as $discussion) {
            $posttime = $discussion->modified;
            if (!empty($CFG->forum_enabletimedposts) && !empty($discussion->timestart) && $discussion->timestart > $posttime) {
                $posttime = $discussion->timestart;
            }

            $excerpt = '';
            if (!empty($discussion->message)) {
                $excerpt = shorten_text(html_to_text($discussion->message, 0, false), 160);
            }

            $candidates[] = [
                'coursefullname' => $coursename,
                'subject' => format_string($discussion->name, true, ['context' => $context]),
                'date' => userdate($posttime, get_string('strftimedatefullshort', 'core_langconfig')),
                'timemodified' => $posttime,
                'url' => (new moodle_url('/mod/forum/discuss.php', ['d' => $discussion->discussion]))->out(false),
                'excerpt' => $excerpt,
            ];
        }
    }

    if (!$candidates) {
        return $empty;
    }

    usort($candidates, static function(array $a, array $b): int {
        return $b['timemodified'] <=> $a['timemodified'];
    });

    $announcements = array_slice($candidates, 0, $limit);
    foreach ($announcements as &$item) {
        $item['hasexcerpt'] = !empty($item['excerpt']);
    }
    unset($item);

    return [
        'hasannouncements' => true,
        'announcements' => $announcements,
    ];
}

/**
 * Latest announcements for the site front page (news forums on visible courses).
 *
 * @param int $limit Maximum items to show.
 * @return array{hasannouncements: bool, announcements: array, hasolderurl: bool, olderurl: string}
 */
function theme_iiidem2_get_frontpage_announcements(int $limit = 5): array {
    global $CFG, $DB, $SITE;

    $empty = [
        'hasannouncements' => false,
        'announcements' => [],
        'hasolderurl' => false,
        'olderurl' => '',
    ];

    $forumplugin = core_plugin_manager::instance()->get_plugin_info('mod_forum');
    if (!$forumplugin || !$forumplugin->is_enabled()) {
        return $empty;
    }

    require_once($CFG->dirroot . '/mod/forum/lib.php');

    $courses = [get_course(SITEID)];
    foreach (get_courses(['sortorder' => 'ASC']) as $course) {
        if ((int) $course->id === SITEID || empty($course->visible)) {
            continue;
        }
        $courses[] = $course;
    }

    $candidates = [];
    $percourse = 5;
    $primaryforumurl = '';

    foreach ($courses as $course) {
        $forum = $DB->get_record('forum', ['course' => $course->id, 'type' => 'news'], '*', IGNORE_MULTIPLE);
        if (!$forum) {
            continue;
        }

        try {
            $modinfo = get_fast_modinfo($course);
        } catch (Exception $e) {
            continue;
        }

        if (empty($modinfo->instances['forum'][$forum->id])) {
            continue;
        }

        $cm = $modinfo->instances['forum'][$forum->id];
        if (!$cm->uservisible) {
            continue;
        }

        $context = context_module::instance($cm->id);
        if (!has_capability('mod/forum:viewdiscussion', $context)) {
            continue;
        }

        $sort = forum_get_default_sort_order(true, 'p.modified', 'd', false);
        $discussions = forum_get_discussions(
            $cm,
            $sort,
            true,
            -1,
            $percourse,
            false,
            -1,
            0,
            FORUM_POSTS_ALL_USER_GROUPS
        );

        if (!$discussions) {
            continue;
        }

        $coursecontext = context_course::instance($course->id);
        $metaname = format_string($course->shortname, true, ['context' => $coursecontext]);
        if ($metaname === '') {
            $metaname = format_string($course->fullname, true, ['context' => $coursecontext]);
        }

        $forumlisturl = (new moodle_url('/mod/forum/view.php', ['id' => $cm->id]))->out(false);
        if ((int) $course->id === SITEID && $primaryforumurl === '') {
            $primaryforumurl = $forumlisturl;
        }

        foreach ($discussions as $discussion) {
            $posttime = $discussion->modified;
            if (!empty($CFG->forum_enabletimedposts) && !empty($discussion->timestart) && $discussion->timestart > $posttime) {
                $posttime = $discussion->timestart;
            }

            $candidates[] = [
                'datetime' => userdate($posttime, get_string('strftimedatetimeshort', 'langconfig')),
                'meta' => $metaname,
                'title' => format_string($discussion->name, true, ['context' => $context]),
                'url' => (new moodle_url('/mod/forum/discuss.php', ['d' => $discussion->discussion]))->out(false),
                'forumlisturl' => $forumlisturl,
                'timemodified' => $posttime,
            ];
        }
    }

    if (!$candidates) {
        return $empty;
    }

    usort($candidates, static function(array $a, array $b): int {
        return $b['timemodified'] <=> $a['timemodified'];
    });

    $announcements = array_slice($candidates, 0, $limit);
    $olderurl = $primaryforumurl;
    if ($olderurl === '' && !empty($announcements[0]['forumlisturl'])) {
        $olderurl = $announcements[0]['forumlisturl'];
    }
    foreach ($announcements as &$item) {
        unset($item['timemodified'], $item['forumlisturl']);
    }
    unset($item);

    return [
        'hasannouncements' => true,
        'announcements' => $announcements,
        'hasolderurl' => $olderurl !== '',
        'olderurl' => $olderurl,
    ];
}

/**
 * Course id for embedded dashboard calendar for the current user.
 *
 * @param int $userid
 * @return int
 */
function theme_iiidem2_get_dashboard_calendar_course_id_for_user(int $userid): int {
    global $CFG;

    require_once($CFG->libdir . '/enrollib.php');

    $courses = enrol_get_users_courses($userid, true);
    unset($courses[SITEID]);

    if (!empty($courses)) {
        return theme_iiidem2_get_dashboard_calendar_course_id(array_values($courses));
    }

    $visible = theme_iiidem2_get_visible_course_ids_for_calendar();
    if (count($visible) === 1) {
        return $visible[0];
    }

    return SITEID;
}

/**
 * Course id used for embedded dashboard calendar (single course vs site-wide).
 *
 * @param array $courses Enrolled or visible courses.
 * @return int
 */
function theme_iiidem2_get_dashboard_calendar_course_id(array $courses): int {
    if (count($courses) === 1) {
        $course = reset($courses);
        return (int) $course->id;
    }
    return SITEID;
}

/**
 * Render Moodle's native month calendar view HTML for dashboard embedding.
 *
 * @param int $courseid Course calendar scope (SITEID = all accessible courses).
 * @return string
 */
function theme_iiidem2_render_dashboard_calendar_embed(int $courseid = SITEID): string {
    global $PAGE, $CFG, $USER;

    require_once($CFG->dirroot . '/calendar/lib.php');

    \core_calendar\local\event\container::set_requesting_user((int) $USER->id);

    $calendar = calendar_information::create(time(), $courseid);
    $renderer = $PAGE->get_renderer('core_calendar');
    [$data, $template] = calendar_get_view($calendar, 'month', true, false);

    $data->showviewselector = false;
    unset($data->filter_selector, $data->defaulteventcontext);

    $html = $renderer->render_from_template($template, $data);

    return $renderer->start_layout()
        . html_writer::start_tag('div', ['class' => 'heightcontainer', 'data-calendar-type' => 'dashboard-embed'])
        . $html
        . html_writer::end_tag('div')
        . $renderer->complete_layout();
}

/**
 * @deprecated Use theme_iiidem2_render_dashboard_calendar_embed().
 * @param int $courseid
 * @return string
 */
function theme_iiidem2_render_dashboard_calendar_upcoming(int $courseid = SITEID): string {
    return theme_iiidem2_render_dashboard_calendar_embed($courseid);
}

/**
 * Calendar panel context: embedded month view + link to full calendar page.
 *
 * @param int $courseid
 * @return array{calendarhtml: string, hascalendarhtml: bool, calendarurl: string, calendarcourseid: int}
 */
function theme_iiidem2_get_dashboard_calendar_context(int $courseid = SITEID): array {
    $url = new moodle_url('/calendar/view.php', ['view' => 'month']);
    if ($courseid != SITEID) {
        $url->param('course', $courseid);
    }

    return [
        'calendarhtml' => theme_iiidem2_render_dashboard_calendar_embed($courseid),
        'hascalendarhtml' => true,
        'calendarurl' => $url->out(false),
        'calendarcourseid' => $courseid,
    ];
}

/**
 * URL for a Moodle calendar event (activity or day view).
 *
 * @param calendar_event|stdClass $event
 * @return string
 */
function theme_iiidem2_get_calendar_event_url($event): string {
    if (!empty($event->modulename) && !empty($event->instance)) {
        try {
            $cm = get_coursemodule_from_instance($event->modulename, $event->instance, $event->courseid, IGNORE_MISSING);
            if ($cm) {
                return (new moodle_url('/mod/' . $event->modulename . '/view.php', ['id' => $cm->id]))->out(false);
            }
        } catch (Exception $e) {
            // Fall through to calendar day view.
        }
    }

    $params = ['view' => 'day', 'time' => $event->timestart];
    if (!empty($event->courseid)) {
        $params['course'] = $event->courseid;
    }

    return (new moodle_url('/calendar/view.php', $params))->out(false);
}

/**
 * Upcoming (and recent) Moodle calendar events for dashboard panels.
 *
 * @param array $courseids Course ids to include.
 * @param int|null $userid User for capability checks.
 * @param int $limit Max events to return.
 * @return array List of {title, meta, url, ispast} items.
 */
function theme_iiidem2_get_dashboard_calendar_events(array $courseids, ?int $userid = null, int $limit = 8): array {
    global $CFG;

    $courseids = array_values(array_unique(array_filter(array_map('intval', $courseids))));
    if (empty($courseids)) {
        return [];
    }

    require_once($CFG->dirroot . '/calendar/lib.php');

    if ($userid !== null) {
        \core_calendar\local\event\container::set_requesting_user($userid);
    }

    $now = time();
    $upcoming = calendar_get_events($now, $now + (90 * DAYSECS), false, false, $courseids, true, true);
    $events = $upcoming ?: [];

    if (count($events) < $limit) {
        $recent = calendar_get_events($now - (60 * DAYSECS), $now, false, false, $courseids, true, true);
        if ($recent) {
            usort($recent, static function($a, $b): int {
                return $b->timestart <=> $a->timestart;
            });
            $existingids = [];
            foreach ($events as $event) {
                $existingids[$event->id] = true;
            }
            foreach ($recent as $event) {
                if (empty($existingids[$event->id])) {
                    $events[] = $event;
                }
            }
        }
    }

    if (!$events) {
        return [];
    }

    usort($events, static function($a, $b): int {
        return $a->timestart <=> $b->timestart;
    });

    $items = [];
    foreach (array_slice($events, 0, $limit) as $event) {
        $coursename = '';
        if (!empty($event->courseid)) {
            $course = get_course($event->courseid, false);
            if ($course) {
                $coursecontext = context_course::instance($course->id);
                $coursename = format_string($course->shortname, true, ['context' => $coursecontext]);
                if ($coursename === '') {
                    $coursename = format_string($course->fullname, true, ['context' => $coursecontext]);
                }
            }
        }

        $datestr = userdate($event->timestart, get_string('strftimedatetimeshort', 'langconfig'));
        $items[] = [
            'title' => format_string($event->name, true),
            'meta' => $coursename !== '' ? $coursename . ' · ' . $datestr : $datestr,
            'url' => theme_iiidem2_get_calendar_event_url($event),
            'ispast' => $event->timestart < $now,
        ];
    }

    return $items;
}

/**
 * Visible course ids for site-wide dashboard calendar (admins).
 *
 * @return int[]
 */
function theme_iiidem2_get_visible_course_ids_for_calendar(): array {
    $ids = [];
    foreach (get_courses(['sortorder' => 'ASC']) as $course) {
        if ((int) $course->id === SITEID || empty($course->visible)) {
            continue;
        }
        $ids[] = (int) $course->id;
    }
    return $ids;
}

/**
 * Template context for role dashboards.
 *
 * @param int|null $userid
 * @return array
 */
function theme_iiidem2_get_dashboard_context(?int $userid = null): array {
    global $USER, $CFG;

    if ($userid === null) {
        $userid = $USER->id;
    }

    $user = core_user::get_user($userid, '*', MUST_EXIST);
    $role = theme_iiidem2_get_user_dashboard_role($userid);

    $context = [
        'fullname' => fullname($user),
        'config' => [
            'wwwroot' => $CFG->wwwroot,
        ],
        'isstudent' => false,
        'isteacher' => false,
        'isadmin' => false,
        'mycoursesurl' => (new moodle_url('/my/courses.php'))->out(false),
        'profileurl' => (new moodle_url('/user/profile.php', ['id' => $userid]))->out(false),
        'coursesurl' => (new moodle_url('/course/management.php'))->out(false),
        'reportsurl' => (new moodle_url('/report/log/index.php'))->out(false),
        'usersurl' => (new moodle_url('/admin/user.php'))->out(false),
        'siteadminurl' => (new moodle_url('/admin/search.php'))->out(false),
    ];

    switch ($role) {
        case 'admin':
            $context['isadmin'] = true;
            $calendarcourseid = theme_iiidem2_get_dashboard_calendar_course_id_for_user($userid);
            $context = array_merge($context, theme_iiidem2_get_dashboard_calendar_context($calendarcourseid));
            break;
        case 'teacher':
            $context['isteacher'] = true;
            $context = array_merge($context, \theme_iiidem2\teacher_dashboard::get_context($userid));
            break;
        default:
            $context['isstudent'] = true;
            $context = array_merge($context, \theme_iiidem2\student_dashboard::get_context($userid));
    }

    // Admins/teachers may also be enrolled as students — always expose participant courses.
    if (empty($context['isstudent'])) {
        $context = array_merge($context, \theme_iiidem2\student_dashboard::get_enrolled_courses_context($userid));
    }

    return $context;
}

/**
 * Homepage slider slides from theme settings.
 *
 * @return array
 */
function theme_iiidem2_get_frontpage_slides(): array {
    global $CFG;

    $slides = [];
    $fs = get_file_storage();
    $context = context_system::instance();

    for ($i = 1; $i <= 2; $i++) {
        $imageurl = '';
        $files = $fs->get_area_files(
            $context->id,
            'theme_iiidem2',
            'slideimage' . $i,
            0,
            'itemid, filepath, filename',
            false
        );

        if ($files) {
            $file = reset($files);
            $imageurl = moodle_url::make_pluginfile_url(
                $file->get_contextid(),
                $file->get_component(),
                $file->get_filearea(),
                $file->get_itemid(),
                $file->get_filepath(),
                $file->get_filename()
            )->out(false);
        }

        if (empty($imageurl)) {
            $imageurl = $CFG->wwwroot . '/theme/iiidem2/pix/hero.jpg';
        }

        $title = get_config('theme_iiidem2', 'slidetitle' . $i);
        $description = format_text(
            get_config('theme_iiidem2', 'slidedesc' . $i),
            FORMAT_HTML,
            ['noclean' => true]
        );
        $buttontext = get_config('theme_iiidem2', 'slidebtntext' . $i);
        $buttonurl = get_config('theme_iiidem2', 'slidebtnurl' . $i);

        if (!empty($buttonurl) && $buttonurl[0] === '/') {
            $buttonurl = $CFG->wwwroot . $buttonurl;
        }

        $slides[] = [
            'index' => $i - 1,
            'title' => $title ?: 'Welcome to IIIDEM',
            'description' => $description,
            'buttontext' => $buttontext ?: get_string('view'),
            'buttonurl' => $buttonurl ?: ($CFG->wwwroot . '/course/index.php'),
            'image' => $imageurl,
            'active' => ($i === 1),
        ];
    }

    return $slides;
}

/**
 * About IDEA + Program Governance context for the front page template.
 *
 * @return array
 */
function theme_iiidem2_get_frontpage_context(): array {
    return array_merge(
        theme_iiidem2_get_about_idea_context(),
        theme_iiidem2_get_program_governance_context()
    );
}

/**
 * Program Governance block for the front page (theme settings + uploaded photos).
 *
 * @return array Mustache context: governancetitle, advisors[], hasadvisors.
 */
function theme_iiidem2_get_program_governance_context(): array {
    global $CFG;

    $theme = theme_config::load('iiidem2');
    $title = trim((string) get_config('theme_iiidem2', 'governancetitle'));
    if ($title === '' && !empty($theme->settings->governancetitle)) {
        $title = trim((string) $theme->settings->governancetitle);
    }
    if ($title === '') {
        $title = 'Program Governance';
    }

    $advisors = [];
    $fs = get_file_storage();
    $context = context_system::instance();
    $defaultimage = $CFG->wwwroot . '/theme/iiidem2/pix/rakesh-verma.png';

    for ($i = 1; $i <= 8; $i++) {
        $name = trim((string) get_config('theme_iiidem2', 'advisorname' . $i));
        if ($name === '') {
            continue;
        }

        $roles = [];
        foreach (['advisorrole1', 'advisorrole2'] as $rolekey) {
            $line = trim((string) get_config('theme_iiidem2', $rolekey . $i));
            if ($line !== '') {
                $roles[] = ['text' => $line];
            }
        }
        // Legacy: old textarea / comma-separated advisorroles setting.
        $rolesraw = trim((string) get_config('theme_iiidem2', 'advisorroles' . $i));
        if ($rolesraw !== '' && empty($roles)) {
            foreach (preg_split('/\r\n|\r|\n|,/', $rolesraw) as $line) {
                $line = trim($line);
                if ($line !== '') {
                    $roles[] = ['text' => $line];
                }
            }
        }

        $imageurl = $defaultimage;
        $files = $fs->get_area_files(
            $context->id,
            'theme_iiidem2',
            'advisorimage' . $i,
            0,
            'itemid, filepath, filename',
            false
        );
        if ($files) {
            $file = reset($files);
            $imageurl = moodle_url::make_pluginfile_url(
                $file->get_contextid(),
                $file->get_component(),
                $file->get_filearea(),
                $file->get_itemid(),
                $file->get_filepath(),
                $file->get_filename()
            )->out(false);
        }

        $advisors[] = [
            'name' => $name,
            'roles' => $roles,
            'hasroles' => !empty($roles),
            'imageurl' => $imageurl,
            'imagealt' => $name,
        ];
    }

    return [
        'governancetitle' => $title,
        'advisors' => $advisors,
        'hasadvisors' => !empty($advisors),
    ];
}

/**
 * About International IDEA block for the front page.
 *
 * @return array Mustache context: aboutideatitle, aboutideabody, hasaboutideabody.
 */
function theme_iiidem2_get_about_idea_context(): array {
    static $cached;

    if ($cached !== null) {
        return $cached;
    }

    $theme = theme_config::load('iiidem2');

    $title = trim((string) get_config('theme_iiidem2', 'aboutideatitle'));
    if ($title === '' && !empty($theme->settings->aboutideatitle)) {
        $title = trim((string) $theme->settings->aboutideatitle);
    }
    if ($title === '') {
        $title = 'About International IDEA';
    }

    $bodyraw = (string) get_config('theme_iiidem2', 'aboutideabody');
    if ($bodyraw === '' && !empty($theme->settings->aboutideabody)) {
        $bodyraw = (string) $theme->settings->aboutideabody;
    }

    $hasbody = trim(strip_tags($bodyraw)) !== '';
    $body = $bodyraw;
    if ($hasbody) {
        $context = context_system::instance();
        $body = format_text($bodyraw, FORMAT_HTML, ['noclean' => true, 'context' => $context]);
    }

    $cached = [
        'iiidem_idea_title' => $title,
        'iiidem_idea_body' => $body,
        'iiidem_idea_hasbody' => $hasbody,
    ];

    return $cached;
}

/**
 * Course overview image URL for templates.
 *
 * @param stdClass $course
 * @return string
 */
function theme_iiidem2_get_course_image_url(stdClass $course): string {
    global $CFG;

    $courseobj = new core_course_list_element($course);
    foreach ($courseobj->get_course_overviewfiles() as $file) {
        if ($file->is_valid_image()) {
            return moodle_url::make_pluginfile_url(
                $file->get_contextid(),
                $file->get_component(),
                $file->get_filearea(),
                null,
                $file->get_filepath(),
                $file->get_filename()
            )->out(false);
        }
    }

    return $CFG->wwwroot . '/theme/iiidem2/pix/ai.jpg';
}

/**
 * Visible courses for the front page listing.
 *
 * @return array
 */
function theme_iiidem2_get_frontpage_courses(): array {
    global $CFG;

    $coursedata = [];
    $courses = get_courses();

    foreach ($courses as $course) {
        if ((int) $course->id === (int) SITEID || empty($course->visible)) {
            continue;
        }

        $coursedata[] = [
            'id' => $course->id,
            'fullname' => format_string($course->fullname),
            'summary' => shorten_text(strip_tags($course->summary), 120),
            'viewurl' => (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
            'courseimage' => theme_iiidem2_get_course_image_url($course),
        ];
    }

    return $coursedata;
}

/**
 * Set Mustache template for the current marketing layout page.
 *
 * @param string $templatename e.g. theme_iiidem2/pages/about-us
 */
function theme_iiidem2_set_marketing_template(string $templatename): void {
    global $CFG;
    $CFG->theme_iiidem2_marketing_template = $templatename;
}

/**
 * Optional extra Mustache context for the current marketing page.
 *
 * @param array $context
 */
function theme_iiidem2_set_marketing_context(array $context): void {
    global $CFG;
    $CFG->theme_iiidem2_marketing_context = $context;
}

/**
 * Get Mustache template for the marketing layout.
 *
 * @return string
 */
function theme_iiidem2_get_marketing_template(): string {
    global $CFG;
    return $CFG->theme_iiidem2_marketing_template ?? '';
}

/**
 * Extra context set by the current marketing page script.
 *
 * @return array
 */
function theme_iiidem2_get_marketing_extra_context(): array {
    global $CFG;
    return $CFG->theme_iiidem2_marketing_context ?? [];
}

/**
 * Render a public page without $OUTPUT->header() (avoids forced login on course URLs).
 *
 * Uses the same approach as the site front page layout.
 *
 * @param string $template Full Mustache template name
 * @param array $extracontext Template variables merged with marketing shell context
 * @param string $bodyclass Body class for styling
 */
function theme_iiidem2_render_public_page(
    string $template,
    array $extracontext = [],
    string $bodyclass = 'pagelayout-marketing',
    bool $requirelogin = true
): void {
    global $OUTPUT, $PAGE, $SITE;

    if ($requirelogin) {
        require_course_login($SITE);
    }

    $pagesubtitle = $extracontext['pagesubtitle'] ?? get_string('aboutus_lead', 'theme_iiidem2');
    $templatecontext = theme_iiidem2_merge_footer_context(array_merge(
        theme_iiidem2_get_marketing_page_context($pagesubtitle),
        $extracontext
    ));

    echo $OUTPUT->doctype();
    ?>
<html <?php echo $OUTPUT->htmlattributes(); ?>>
<head>
    <?php echo $OUTPUT->standard_head_html(); ?>
</head>
<body <?php echo $OUTPUT->body_attributes([$bodyclass]); ?>>
<?php echo $OUTPUT->standard_top_of_body_html(); ?>
<?php
    echo $OUTPUT->render_from_template($template, $templatecontext);
?>
</body>
</html>
    <?php
}

/**
 * Shared context for public marketing pages (navbar, footer, etc.).
 *
 * @param string|null $pagesubtitle
 * @return array
 */
function theme_iiidem2_get_marketing_page_context(?string $pagesubtitle = null): array {
    global $USER, $PAGE, $OUTPUT, $SITE;

    $primary = new core\navigation\output\primary($PAGE);
    $renderer = $PAGE->get_renderer('core');
    $primarymenu = $primary->export_for_template($renderer);

    if ($pagesubtitle === null) {
        $pagesubtitle = get_string('aboutus_lead', 'theme_iiidem2');
    }

    return [
        'sitename' => format_string($SITE->fullname),
        'output' => $OUTPUT,
        'primarymoremenu' => $primarymenu['moremenu'],
        'mobileprimarynav' => $primarymenu['mobileprimarynav'],
        'usermenu' => $primarymenu['user'],
        'langmenu' => $primarymenu['lang'],
        'isrealuser' => ($USER->id > 1),
        'pagetitle' => $PAGE->title,
        'pagesubtitle' => $pagesubtitle,
        'homeurl' => (new moodle_url('/'))->out(false),
    ];
}

/**
 * Contact page: details from theme settings.
 *
 * @return array
 */
function theme_iiidem2_get_contact_page_context(): array {
    $footer = theme_iiidem2_get_footer_settings();

    $hasaddress = !empty($footer['address']);
    $hasemail = !empty($footer['email']);
    $hasphone = !empty($footer['phone']);
    $hascontactinfo = $hasaddress || $hasemail || $hasphone;

    $mailto = '';
    if ($hasemail) {
        $mailto = 'mailto:' . rawurlencode($footer['email']);
    }

    return [
        'hascontactinfo' => $hascontactinfo,
        'hasaddress' => $hasaddress,
        'hasemail' => $hasemail,
        'hasphone' => $hasphone,
        'contactaddress' => $hasaddress ? format_string($footer['address']) : '',
        'contactemail' => $hasemail ? $footer['email'] : '',
        'contactphone' => $hasphone ? $footer['phone'] : '',
        'contactemailurl' => $mailto,
        'contactphoneurl' => $hasphone ? 'tel:' . preg_replace('/\s+/', '', $footer['phone']) : '',
    ];
}

/**
 * Send contact form submission to the institute email / site support.
 *
 * @param stdClass $data Form data (name, email, subject, message).
 * @return bool
 */
function theme_iiidem2_send_contact_message(\stdClass $data): bool {
    global $CFG, $SITE, $USER;

    $recipient = core_user::get_support_user();
    $themeemail = get_config('theme_iiidem2', 'email');
    if (!empty($themeemail) && validate_email($themeemail)) {
        $recipient = clone $recipient;
        $recipient->email = $themeemail;
        $recipient->maildisplay = true;
    }

    if (isloggedin() && !isguestuser()) {
        $from = $USER;
    } else {
        $from = core_user::get_noreply_user();
    }

    $subject = get_string('contactusemailsubject', 'theme_iiidem2', [
        'site' => format_string($SITE->fullname),
        'subject' => $data->subject,
    ]);

    $body = get_string('contactusemailbody', 'theme_iiidem2', (object) [
        'name' => $data->name,
        'email' => $data->email,
        'subject' => $data->subject,
        'message' => $data->message,
    ]);

    return email_to_user(
        $recipient,
        $from,
        $subject,
        $body,
        '',
        '',
        true,
        $data->email,
        $data->name
    );
}

/**
 * Optional extra cmids for custom quiz wrapper (empty = all quizzes in the current course).
 *
 * @return int[]
 */
function theme_iiidem2_get_custom_quiz_cm_ids(): array {
    $raw = get_config('theme_iiidem2', 'customquizcmids');
    if ($raw === false || $raw === null || trim((string) $raw) === '') {
        return [];
    }
    $ids = [];
    foreach (preg_split('/\s*,\s*/', trim((string) $raw)) as $part) {
        if ($part !== '' && is_numeric($part)) {
            $ids[] = (int) $part;
        }
    }
    return $ids;
}

/**
 * Resolve quiz cm from page (cm on $PAGE or cmid/attempt in URL).
 *
 * @param moodle_page $page
 * @return cm_info|null
 */
function theme_iiidem2_resolve_quiz_cm_from_page(moodle_page $page): ?cm_info {
    global $DB;

    if (!empty($page->cm) && $page->cm->modname === 'quiz') {
        return $page->cm;
    }

    $path = $page->url->get_path(false);
    if (!preg_match('#/mod/quiz/(attempt|summary|review|view)\.php$#', $path)) {
        return null;
    }

    $cmid = (int) $page->url->get_param('cmid');
    if (!$cmid && $path === '/mod/quiz/attempt.php') {
        $attemptid = (int) $page->url->get_param('attempt');
        if ($attemptid) {
            $attempt = $DB->get_record('quiz_attempts', ['id' => $attemptid], 'quiz', IGNORE_MISSING);
            if ($attempt) {
                $cmrecord = get_coursemodule_from_instance('quiz', (int) $attempt->quiz, 0, false, IGNORE_MISSING);
                if ($cmrecord) {
                    $course = get_course($cmrecord->course);
                    $modinfo = get_fast_modinfo($course);
                    return $modinfo->get_cm($cmrecord->id);
                }
            }
        }
    }

    if ($cmid) {
        if (!empty($page->course->id) && (int) $page->course->id !== SITEID) {
            $modinfo = get_fast_modinfo($page->course);
            if (isset($modinfo->cms[$cmid]) && $modinfo->cms[$cmid]->modname === 'quiz') {
                return $modinfo->cms[$cmid];
            }
        }
        $cmrecord = get_coursemodule_from_id('quiz', $cmid, 0, false, IGNORE_MISSING);
        if ($cmrecord) {
            $course = get_course($cmrecord->course);
            $modinfo = get_fast_modinfo($course);
            return $modinfo->get_cm($cmrecord->id);
        }
    }

    $cmid = theme_iiidem2_get_cmid_from_page($page);
    if ($cmid) {
        $cmrecord = get_coursemodule_from_id('quiz', $cmid, 0, false, IGNORE_MISSING);
        if ($cmrecord) {
            $course = get_course($cmrecord->course);
            $modinfo = get_fast_modinfo($course);
            return $modinfo->get_cm($cmrecord->id);
        }
    }

    return null;
}

/**
 * Best-effort cmid from $PAGE->cm, URL, or body classes (cmid-7).
 *
 * @param moodle_page $page
 * @return int
 */
function theme_iiidem2_get_cmid_from_page(moodle_page $page): int {
    if (!empty($page->cm) && $page->cm->modname === 'quiz') {
        return (int) $page->cm->id;
    }

    $cmid = (int) $page->url->get_param('cmid');
    if ($cmid) {
        return $cmid;
    }

    if (preg_match('/\bcmid-(\d+)\b/', (string) $page->bodyclasses, $matches)) {
        return (int) $matches[1];
    }

    return 0;
}

/**
 * True on mod/quiz/attempt.php (and summary/review).
 *
 * @param moodle_page|null $page
 * @return bool
 */
function theme_iiidem2_is_quiz_attempt_url(?moodle_page $page = null): bool {
    global $PAGE;
    $page = $page ?? $PAGE;
    $path = $page->url->get_path(false);
    return (bool) preg_match('#/mod/quiz/(attempt|summary|review)\.php$#', $path);
}

/**
 * True on the live quiz attempt screen (pagetype or URL).
 *
 * @param moodle_page|null $page
 * @return bool
 */
function theme_iiidem2_is_quiz_attempt_page(?moodle_page $page = null): bool {
    global $PAGE;
    $page = $page ?? $PAGE;
    if ($page->pagetype === 'mod-quiz-attempt') {
        return true;
    }
    $path = $page->url->get_path(false);
    return strpos($path, '/mod/quiz/attempt.php') !== false;
}

/**
 * Whether the current page should use theme_iiidem2/quiz/activity_wrapper.
 * By default: any quiz activity in the current course (not a fixed cmid).
 *
 * @param moodle_page|null $page
 * @return bool
 */
function theme_iiidem2_is_custom_quiz_page(?moodle_page $page = null): bool {
    global $PAGE;
    $page = $page ?? $PAGE;

    if (theme_iiidem2_is_quiz_attempt_page($page)) {
        return theme_iiidem2_use_custom_quiz_ui($page);
    }

    $cm = theme_iiidem2_resolve_quiz_cm_from_page($page);
    if (!$cm || $cm->modname !== 'quiz') {
        return false;
    }

    $explicit = theme_iiidem2_get_custom_quiz_cm_ids();
    if (!empty($explicit)) {
        return in_array((int) $cm->id, $explicit, true);
    }

    if ((int) $cm->course === SITEID) {
        return false;
    }

    return true;
}

/**
 * Whether this quiz page should use the IIIDEM custom MCQ UI.
 *
 * @param moodle_page|null $page
 * @return bool
 */
function theme_iiidem2_use_custom_quiz_ui(?moodle_page $page = null): bool {
    global $PAGE;
    $page = $page ?? $PAGE;

    if (!theme_iiidem2_is_quiz_attempt_page($page)) {
        return false;
    }

    $explicit = theme_iiidem2_get_custom_quiz_cm_ids();
    $cmid = theme_iiidem2_get_cmid_from_page($page);
    if (!$cmid) {
        $cm = theme_iiidem2_resolve_quiz_cm_from_page($page);
        $cmid = $cm ? (int) $cm->id : 0;
    }

    if (!empty($explicit)) {
        return $cmid > 0 && in_array($cmid, $explicit, true);
    }

    $cm = theme_iiidem2_resolve_quiz_cm_from_page($page);
    if ($cm && (int) $cm->course === SITEID) {
        return false;
    }

    return true;
}

/**
 * Apply body class + assets for custom quiz pages (safe to call multiple times).
 *
 * @param moodle_page|null $page
 * @return void
 */
function theme_iiidem2_apply_custom_quiz_page_assets(?moodle_page $page = null): void {
    global $PAGE;
    $page = $page ?? $PAGE;

    if (theme_iiidem2_is_quiz_attempt_page($page)) {
        if (!theme_iiidem2_use_custom_quiz_ui($page)) {
            return;
        }
    } else if (!theme_iiidem2_is_custom_quiz_page($page)) {
        return;
    }

    $cm = theme_iiidem2_resolve_quiz_cm_from_page($page);
    $cmid = $cm ? (int) $cm->id : theme_iiidem2_get_cmid_from_page($page);
    if ($cmid) {
        $page->add_body_class('iiidem-custom-quiz');
        $page->add_body_class('iiidem-custom-quiz-cmid-' . $cmid);
    }

    $page->requires->css(new moodle_url('/theme/iiidem2/style/quiz-mcq.css'));
    $page->requires->js_call_amd('theme_iiidem2/quiz_mcq', 'init');
}

/**
 * Template context for the custom quiz wrapper (drawers / incourse layout).
 *
 * @return array
 */
function theme_iiidem2_get_custom_quiz_template_context(): array {
    global $PAGE, $DB;

    $cm = theme_iiidem2_resolve_quiz_cm_from_page($PAGE);
    if (!$cm || $cm->modname !== 'quiz') {
        return [
            'iiidemcustomquiz' => false,
            'iiidemquizattempt' => theme_iiidem2_is_quiz_attempt_url($PAGE),
            'quizviewurl' => $PAGE->url->out(false),
            'quizcmid' => 0,
            'hasquizwatermark' => false,
        ];
    }

    $explicit = theme_iiidem2_get_custom_quiz_cm_ids();
    if (!empty($explicit) && !in_array((int) $cm->id, $explicit, true)) {
        return ['iiidemcustomquiz' => false, 'iiidemquizattempt' => theme_iiidem2_is_quiz_attempt_url($PAGE)];
    }
    $quiz = $DB->get_record('quiz', ['id' => $cm->instance], '*', MUST_EXIST);
    $context = context_module::instance($cm->id);

    $timelimit = '';
    if (!empty($quiz->timelimit)) {
        $timelimit = format_time($quiz->timelimit);
    }

    $theme = theme_config::load('iiidem2');
    $watermark = $theme->setting_file_url('footerlogo', 'footerlogo');
    if (!$watermark) {
        $watermark = (new moodle_url('/theme/iiidem2/pix/iiidem-white-logo-footer.png'))->out(false);
    }

    return [
        'iiidemcustomquiz' => true,
        'iiidemquizattempt' => theme_iiidem2_is_quiz_attempt_url($PAGE),
        'quizcmid' => $cm->id,
        'quizname' => format_string($quiz->name, true, ['context' => $context]),
        'quizintro' => format_text($quiz->intro, $quiz->introformat, ['context' => $context]),
        'hasquizintro' => trim(strip_tags($quiz->intro)) !== '',
        'quizquestioncount' => $DB->count_records('quiz_slots', ['quizid' => $quiz->id]),
        'quiztimelimit' => $timelimit,
        'hasquiztimelimit' => !empty($quiz->timelimit),
        'quizviewurl' => (new moodle_url('/mod/quiz/view.php', ['id' => $cm->id]))->out(false),
        'quizwatermarkurl' => $watermark ? $watermark : '',
        'hasquizwatermark' => !empty($watermark),
    ];
}

/**
 * Optional cmids for the live virtual classroom page layout (empty = auto-detect by name).
 *
 * @return int[]
 */
function theme_iiidem2_get_live_class_cm_ids(): array {
    $raw = get_config('theme_iiidem2', 'liveclasscmids');
    if ($raw === false || $raw === null || trim((string) $raw) === '') {
        return [];
    }
    $ids = [];
    foreach (preg_split('/\s*,\s*/', trim((string) $raw)) as $part) {
        if ($part !== '' && is_numeric($part)) {
            $ids[] = (int) $part;
        }
    }
    return $ids;
}

/**
 * Resolve page cm from $PAGE or mod/page/view.php?id= URL.
 *
 * @param moodle_page $page
 * @return cm_info|null
 */
function theme_iiidem2_resolve_page_cm_from_page(moodle_page $page): ?cm_info {
    if (!empty($page->cm) && $page->cm->modname === 'page') {
        return $page->cm;
    }

    if ($page->url->get_path(false) !== '/mod/page/view.php') {
        return null;
    }

    $id = (int) $page->url->get_param('id');
    if (!$id) {
        return null;
    }

    $cmrecord = get_coursemodule_from_id('page', $id, 0, false, IGNORE_MISSING);
    if (!$cmrecord) {
        return null;
    }

    $course = get_course($cmrecord->course);
    $modinfo = get_fast_modinfo($course);
    return $modinfo->get_cm($cmrecord->id);
}

/**
 * Whether a page activity name should use the live class layout (when no explicit cmid list).
 *
 * @param string $name
 * @return bool
 */
function theme_iiidem2_page_name_matches_live_class(string $name): bool {
    $normalized = core_text::strtolower(trim($name));
    if ($normalized === '') {
        return false;
    }
    return (bool) preg_match('/\b(webex|live\s*class|online\s*class|virtual\s*class|bbb)\b/i', $name);
}

/**
 * Extract the first link href from formatted page HTML (e.g. Join Live Class button).
 *
 * @param string $html
 * @return string
 */
function theme_iiidem2_extract_join_url_from_page_html(string $html): string {
    if (preg_match('/<a[^>]+href=["\']([^"\']+)["\']/i', $html, $matches)) {
        return html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8');
    }
    return '';
}

/**
 * URL for the live virtual classroom page (mod/page), e.g. student dashboard "Live Sessions".
 *
 * Uses theme liveclasscmids when set; otherwise the first visible matching page in enrolled courses.
 *
 * @param int|null $userid Defaults to current user.
 * @return moodle_url
 */
function theme_iiidem2_get_live_class_page_url(?int $userid = null): moodle_url {
    global $USER;

    $userid = $userid ?? (int) $USER->id;
    return \theme_iiidem2\student_dashboard::get_live_class_page_url($userid);
}

/**
 * Whether the current mod/page view should use theme_iiidem2/liveclass/activity_wrapper.
 *
 * @param moodle_page|null $page
 * @return bool
 */
function theme_iiidem2_is_live_class_page(?moodle_page $page = null): bool {
    global $PAGE, $DB;
    $page = $page ?? $PAGE;

    $cm = theme_iiidem2_resolve_page_cm_from_page($page);
    if (!$cm || $cm->modname !== 'page') {
        return false;
    }

    if ((int) $cm->course === SITEID) {
        return false;
    }

    $explicit = theme_iiidem2_get_live_class_cm_ids();
    if (!empty($explicit)) {
        return in_array((int) $cm->id, $explicit, true);
    }

    $pagerecord = $DB->get_record('page', ['id' => $cm->instance], 'name', IGNORE_MISSING);
    if (!$pagerecord) {
        return false;
    }

    return theme_iiidem2_page_name_matches_live_class($pagerecord->name);
}

/**
 * Apply body class + assets for live class pages.
 *
 * @param moodle_page|null $page
 * @return void
 */
function theme_iiidem2_apply_live_class_page_assets(?moodle_page $page = null): void {
    global $PAGE;
    $page = $page ?? $PAGE;

    if (!theme_iiidem2_is_live_class_page($page)) {
        return;
    }

    $cm = theme_iiidem2_resolve_page_cm_from_page($page);
    if ($cm) {
        $page->add_body_class('iiidem-live-class');
        $page->add_body_class('iiidem-live-class-cmid-' . (int) $cm->id);
    }

    $page->requires->css(new moodle_url('/theme/iiidem2/style/live-class.css'));
}

/**
 * Template context for the live virtual classroom wrapper.
 *
 * @return array
 */
function theme_iiidem2_get_live_class_template_context(): array {
    global $PAGE, $DB;

    $defaults = [
        'iiidemcustomliveclass' => false,
        'liveclasscmid' => 0,
        'liveclassjoinurl' => '',
        'hasliveclassjoinurl' => false,
        'liveclassbackurl' => '',
        'liveclassmodified' => '',
        'hasliveclassmodified' => false,
        'liveclassfeatures' => [],
    ];

    if (!theme_iiidem2_is_live_class_page($PAGE)) {
        return $defaults;
    }

    $cm = theme_iiidem2_resolve_page_cm_from_page($PAGE);
    if (!$cm) {
        return $defaults;
    }

    $pagerecord = $DB->get_record('page', ['id' => $cm->instance], '*', MUST_EXIST);
    $context = context_module::instance($cm->id);

    $content = file_rewrite_pluginfile_urls(
        $pagerecord->content,
        'pluginfile.php',
        $context->id,
        'mod_page',
        'content',
        $pagerecord->revision
    );
    $formatoptions = new stdClass();
    $formatoptions->noclean = true;
    $formatoptions->overflowdiv = true;
    $formatoptions->context = $context;
    $formatted = format_text($content, $pagerecord->contentformat, $formatoptions);
    $joinurl = theme_iiidem2_extract_join_url_from_page_html($formatted);

    $courseurl = new moodle_url('/course/view.php', ['id' => $cm->course]);
    $modified = '';
    if (!empty($pagerecord->timemodified)) {
        $modified = userdate($pagerecord->timemodified);
    }

    $features = [
        [
            'iconclass' => 'fa-video',
            'title' => get_string('liveclassfeat_hd', 'theme_iiidem2'),
            'description' => get_string('liveclassfeat_hd_desc', 'theme_iiidem2'),
        ],
        [
            'iconclass' => 'fa-users',
            'title' => get_string('liveclassfeat_breakout', 'theme_iiidem2'),
            'description' => get_string('liveclassfeat_breakout_desc', 'theme_iiidem2'),
        ],
        [
            'iconclass' => 'fa-bolt',
            'title' => get_string('liveclassfeat_polls', 'theme_iiidem2'),
            'description' => get_string('liveclassfeat_polls_desc', 'theme_iiidem2'),
        ],
        [
            'iconclass' => 'fa-comments',
            'title' => get_string('liveclassfeat_chat', 'theme_iiidem2'),
            'description' => get_string('liveclassfeat_chat_desc', 'theme_iiidem2'),
        ],
        [
            'iconclass' => 'fa-play-circle',
            'title' => get_string('liveclassfeat_recording', 'theme_iiidem2'),
            'description' => get_string('liveclassfeat_recording_desc', 'theme_iiidem2'),
        ],
    ];

    return [
        'iiidemcustomliveclass' => true,
        'liveclasscmid' => $cm->id,
        'liveclassname' => format_string($pagerecord->name, true, ['context' => $context]),
        'liveclassjoinurl' => $joinurl,
        'hasliveclassjoinurl' => $joinurl !== '',
        'liveclassbackurl' => $courseurl->out(false),
        'liveclassmodified' => $modified,
        'hasliveclassmodified' => $modified !== '',
        'liveclassfeatures' => $features,
    ];
}

/**
 * HTML list of quiz questions for curriculum preview panels.
 *
 * @param int $quizid Quiz instance id.
 * @return string Safe HTML.
 */
function theme_iiidem2_get_quiz_questions_preview_html(int $quizid): string {
    global $DB;

    $slots = $DB->get_records('quiz_slots', ['quizid' => $quizid], 'slot');
    if (!$slots) {
        return '';
    }

    $html = '<div class="quiz-questions"><strong>Quiz Questions:</strong>';
    foreach ($slots as $slot) {
        $reference = $DB->get_record('question_references', ['itemid' => $slot->id]);
        if (!$reference) {
            continue;
        }
        $versions = $DB->get_records_sql(
            'SELECT qv.questionid
               FROM {question_versions} qv
              WHERE qv.questionbankentryid = ?
           ORDER BY qv.version DESC',
            [$reference->questionbankentryid],
            0,
            1
        );
        $version = reset($versions);
        if (!$version) {
            continue;
        }
        $question = $DB->get_record('question', ['id' => $version->questionid]);
        if (!$question) {
            continue;
        }
        $html .= '<div class="quiz-question mb-3"><h5 class="h6">' .
            format_text($question->questiontext, $question->questiontextformat, ['para' => false]) .
            '</h5>';
        $answers = $DB->get_records('question_answers', ['question' => $question->id], 'id');
        if ($answers) {
            $html .= '<ul class="mb-0">';
            foreach ($answers as $answer) {
                $html .= '<li>' . format_text($answer->answer, $answer->answerformat, ['para' => false]) . '</li>';
            }
            $html .= '</ul>';
        }
        $html .= '</div>';
    }
    $html .= '</div>';
    return $html;
}

/**
 * URL for the marketing-style course detail page.
 *
 * @param stdClass $course
 * @return string
 */
function theme_iiidem2_get_course_detail_url(stdClass $course): string {
    return (new moodle_url('/course-detail/', ['id' => $course->id]))->out(false);
}

/**
 * Public registration page URL.
 *
 * @return string
 */
function theme_iiidem2_get_register_url(): string {
    return (new moodle_url('/register/'))->out(false);
}

/**
 * Strip register nodes from exported primary navigation arrays.
 *
 * @param array $items
 * @return array
 */
function theme_iiidem2_filter_register_from_nav_items(array $items): array {
    $filtered = [];
    foreach ($items as $item) {
        if (!empty($item['key']) && $item['key'] === 'register') {
            continue;
        }
        if (!empty($item['children']) && is_array($item['children'])) {
            $item['children'] = theme_iiidem2_filter_register_from_nav_items($item['children']);
        }
        $filtered[] = $item;
    }
    return $filtered;
}

/**
 * Export primary navigation for templates without the registration link.
 *
 * @param moodle_page $page
 * @return array
 */
function theme_iiidem2_export_primary_menu(moodle_page $page): array {
    $primary = new core\navigation\output\primary($page);
    $renderer = $page->get_renderer('core');
    $menu = $primary->export_for_template($renderer);

    if (!empty($menu['mobileprimarynav']) && is_array($menu['mobileprimarynav'])) {
        $menu['mobileprimarynav'] = theme_iiidem2_filter_register_from_nav_items($menu['mobileprimarynav']);
    }
    if (!empty($menu['moremenu']['nodearray']) && is_array($menu['moremenu']['nodearray'])) {
        $menu['moremenu']['nodearray'] = theme_iiidem2_filter_register_from_nav_items($menu['moremenu']['nodearray']);
    }

    return $menu;
}

/**
 * Generate a unique Moodle username from an email address.
 *
 * @param string $email
 * @return string
 */
function theme_iiidem2_generate_username_from_email(string $email): string {
    global $DB, $CFG;

    $local = strtolower((string) strstr($email, '@', true));
    $base = core_user::clean_field($local !== '' ? $local : 'user', 'username');
    if ($base === '') {
        $base = 'user';
    }

    $candidate = $base;
    $suffix = 0;
    while ($DB->record_exists('user', ['username' => $candidate, 'mnethostid' => $CFG->mnet_localhost_id])) {
        $suffix++;
        $candidate = $base . $suffix;
    }

    return $candidate;
}

/**
 * Render the registration page (form is output after head setup).
 *
 * @param \theme_iiidem2\form\register_form|null $form Existing form instance (POST/validation), or null on first visit.
 * @param array $extracontext
 * @return void
 */
function theme_iiidem2_render_register_page(?\theme_iiidem2\form\register_form $form, array $extracontext = []): void {
    global $OUTPUT, $PAGE, $SITE, $CFG;

    require_course_login($SITE);

    if ($form === null) {
        $form = new \theme_iiidem2\form\register_form();
    }

    $pagesubtitle = $extracontext['pagesubtitle'] ?? get_string('registerpagesubtitle', 'theme_iiidem2');
    $templatecontext = theme_iiidem2_merge_footer_context(array_merge(
        theme_iiidem2_get_marketing_page_context($pagesubtitle),
        $extracontext,
        ['config' => ['wwwroot' => $CFG->wwwroot]]
    ));

    echo $OUTPUT->doctype();
    ?>
<html <?php echo $OUTPUT->htmlattributes(); ?>>
<head>
    <?php echo $OUTPUT->standard_head_html(); ?>
</head>
<body <?php echo $OUTPUT->body_attributes(['pagelayout-marketing', 'iiidem-register-page']); ?>>
<?php echo $OUTPUT->standard_top_of_body_html(); ?>
<?php
    echo $OUTPUT->render_from_template('theme_iiidem2/pages/register_shell', $templatecontext);
    echo '<section class="iiidem-register-section py-5"><div class="container"><div class="row justify-content-center">';
    echo '<div class="col-lg-8"><div class="iiidem-register-card card border-0 shadow-sm">';
    echo '<div class="card-body p-4 p-md-5"><div class="iiidem-register-form">';
    $form->display();
    echo '</div></div></div></div></div></section>';
    echo $OUTPUT->render_from_template('theme_iiidem2/page_end', $templatecontext);
?>
</body>
</html>
    <?php
}

/**
 * Create a Moodle user account from the custom registration form.
 *
 * Saves to core tables: mdl_user (firstname, middlename, lastname, email, phone1, country, city, etc.).
 *
 * @param stdClass $data Form data from theme_iiidem2\form\register_form
 * @return int New user id
 */
function theme_iiidem2_create_registered_user(stdClass $data): int {
    global $CFG;

    require_once($CFG->dirroot . '/user/lib.php');
    require_once($CFG->libdir . '/accesslib.php');

    $auth = 'email';
    if (!empty($CFG->auth)) {
        $enabled = array_filter(explode(',', $CFG->auth));
        if (!in_array('email', $enabled, true) && in_array('manual', $enabled, true)) {
            $auth = 'manual';
        }
    }

    $user = new stdClass();
    $user->username = theme_iiidem2_generate_username_from_email($data->email);
    $user->password = $data->password;
    $user->firstname = $data->firstname;
    $user->middlename = $data->middlename ?? '';
    $user->lastname = $data->lastname;
    $user->email = $data->email;
    $user->phone1 = $data->phone1;
    $user->country = $data->country;
    $user->city = $data->city;
    $user->auth = $auth;
    $user->confirmed = 1;
    $user->mnethostid = $CFG->mnet_localhost_id;
    $user->lang = current_language();

    $occupation = \theme_iiidem2\registration_profile::get_occupation_type($data);
    if ($occupation === 'working') {
        $user->institution = \theme_iiidem2\registration_profile::get_submitted_value($data, 'organization');
        $user->department = \theme_iiidem2\registration_profile::get_submitted_value($data, 'jobprofile');
        $user->address = \theme_iiidem2\registration_profile::get_submitted_value($data, 'jobpostingcountry');
    } else if ($occupation === 'student') {
        $user->institution = \theme_iiidem2\registration_profile::get_submitted_value($data, 'university');
        $user->department = \theme_iiidem2\registration_profile::get_submitted_value($data, 'position');
        $user->address = \theme_iiidem2\registration_profile::get_submitted_value($data, 'specialization');
    } else if ($occupation === 'instructor') {
        $user->institution = \theme_iiidem2\registration_profile::get_submitted_value($data, 'instructor_university');
        $user->department = \theme_iiidem2\registration_profile::get_submitted_value($data, 'instructor_course');
        $user->address = \theme_iiidem2\registration_profile::get_submitted_value($data, 'presentcountry');
    }

    $userid = user_create_user($user, true, true);

    if ($occupation !== '') {
        $profileupdate = (object) ['id' => $userid];
        if ($occupation === 'working') {
            $profileupdate->institution = \theme_iiidem2\registration_profile::get_submitted_value($data, 'organization');
            $profileupdate->department = \theme_iiidem2\registration_profile::get_submitted_value($data, 'jobprofile');
            $profileupdate->address = \theme_iiidem2\registration_profile::get_submitted_value($data, 'jobpostingcountry');
        } else if ($occupation === 'student') {
            $profileupdate->institution = \theme_iiidem2\registration_profile::get_submitted_value($data, 'university');
            $profileupdate->department = \theme_iiidem2\registration_profile::get_submitted_value($data, 'position');
            $profileupdate->address = \theme_iiidem2\registration_profile::get_submitted_value($data, 'specialization');
        } else if ($occupation === 'instructor') {
            $profileupdate->institution = \theme_iiidem2\registration_profile::get_submitted_value($data, 'instructor_university');
            $profileupdate->department = \theme_iiidem2\registration_profile::get_submitted_value($data, 'instructor_course');
            $profileupdate->address = \theme_iiidem2\registration_profile::get_submitted_value($data, 'presentcountry');
        }
        user_update_user($profileupdate, false, false);
    }

    if (!empty($CFG->defaultuserroleid)) {
        role_assign((int) $CFG->defaultuserroleid, $userid, context_system::instance()->id);
    }

    \theme_iiidem2\registration_profile::save_user_data($userid, $data);

    return $userid;
}

/**
 * Whether the current page is the course enrolment options screen.
 *
 * @param moodle_page $page
 * @return bool
 */
function theme_iiidem2_is_enrol_index_page(moodle_page $page): bool {
    return $page->url->compare(new moodle_url('/enrol/index.php'), URL_MATCH_BASE);
}

/**
 * True on the custom IIIDEM student dashboard (theme/iiidem2/dashboard/index.php).
 *
 * @param moodle_page|null $page
 * @return bool
 */
function theme_iiidem2_is_student_dashboard_page(?moodle_page $page = null): bool {
    global $PAGE;
    $page = $page ?? $PAGE;

    if (strpos((string) $page->bodyclasses, 'iiidem-student-dashboard-page') !== false) {
        return true;
    }

    return $page->url->compare(new moodle_url('/theme/iiidem2/dashboard/index.php'), URL_MATCH_BASE);
}

/**
 * Replace Moodle footer placeholders (%%PERFORMANCEINFO%%, %%ENDHTML%%) with real output.
 *
 * Full-page Mustache templates that bypass $OUTPUT->footer() leave these tokens visible.
 *
 * @param string $html
 * @return string
 */
function theme_iiidem2_finalize_page_html(string $html): string {
    global $PAGE;

    $renderer = $PAGE->get_renderer('core');
    $reflection = new ReflectionClass($renderer);

    $perfprop = $reflection->getProperty('unique_performance_info_token');
    $perfprop->setAccessible(true);
    $html = str_replace((string) $perfprop->getValue($renderer), '', $html);

    $endprop = $reflection->getProperty('unique_end_html_token');
    $endprop->setAccessible(true);
    $html = str_replace((string) $endprop->getValue($renderer), $PAGE->requires->get_end_code(), $html);

    return $html;
}

/**
 * CSS/JS for /course/view.php marketing layout (before &lt;head&gt; is built).
 *
 * @param moodle_page $page
 * @return void
 */
function theme_iiidem2_apply_course_view_page_assets(moodle_page $page): void {
    global $CFG;

    static $done = false;
    if ($done) {
        return;
    }
    if (!$page->url->compare(new moodle_url('/course/view.php'), URL_MATCH_BASE)) {
        return;
    }
    if (empty($page->course->id) || (int) $page->course->id === SITEID) {
        return;
    }

    $done = true;
    $page->add_body_class('iiidem-course-hero-layout');
    $page->requires->css(new moodle_url('/theme/iiidem2/style/course-quiz-mcq.css'));

    $bs5css = $CFG->dirroot . '/theme/iiidem2/style/bootstrap5.min.css';
    $bs5js = $CFG->dirroot . '/theme/iiidem2/style/bootstrap5.bundle.min.js';
    if (is_readable($bs5css)) {
        $page->requires->css(new moodle_url('/theme/iiidem2/style/bootstrap5.min.css'));
    }
    if (is_readable($bs5js)) {
        $page->requires->js(new moodle_url('/theme/iiidem2/style/bootstrap5.bundle.min.js'), true);
    }
}

/**
 * Render a full-page Mustache template and resolve Moodle footer placeholders.
 *
 * @param string $templatename
 * @param array|stdClass $context
 * @return void
 */
function theme_iiidem2_echo_page_template(string $templatename, $context): void {
    global $OUTPUT;
    echo theme_iiidem2_finalize_page_html($OUTPUT->render_from_template($templatename, $context));
}

/**
 * Render /course/view.php for visitors without enrolment (hero, curriculum, instructors).
 *
 * @param stdClass $course
 * @return void
 */
function theme_iiidem2_render_public_course_view(stdClass $course): void {
    global $OUTPUT, $PAGE, $SITE, $CFG;

    $coursecontext = context_course::instance($course->id);
    $PAGE->set_context($coursecontext);
    $PAGE->set_course($course);
    $PAGE->set_url(new moodle_url('/course/view.php', ['id' => $course->id]));
    $PAGE->set_pagelayout('course');
    $PAGE->set_pagetype('course-view-' . $course->format);
    $PAGE->set_cacheable(true);
    $PAGE->set_title(format_string($course->fullname));
    $PAGE->set_heading(format_string($course->fullname));

    // Register CSS/JS before the template renders &lt;head&gt; (public path skips header()).
    $PAGE->theme->init_page($PAGE);
    theme_iiidem2_apply_course_view_page_assets($PAGE);
    theme_iiidem2_preload_course_layout_context($course);

    $primarymenu = theme_iiidem2_export_primary_menu($PAGE);

    $coursedisplay = theme_iiidem2_get_course_display_context($course);
    $curriculum = theme_iiidem2_get_course_curriculum_context($course);
    $quizzes = theme_iiidem2_get_course_quizzes_context($course);

    $loginurl = new moodle_url('/login/index.php');
    $loginurl->param('wantsurl', (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false));

    $bodyattributes = $OUTPUT->body_attributes(['uses-drawers', 'iiidem-public-course-view', 'iiidem-course-hero-layout']);

    $wantsurl = (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false);

    $templatecontext = theme_iiidem2_merge_footer_context(array_merge(
        $coursedisplay,
        $curriculum,
        $quizzes,
        theme_iiidem2_get_course_fee_payment_context($course),
        theme_iiidem2_get_program_governance_context(),
        theme_iiidem2_get_login_modal_context($wantsurl),
        [
            'sitename' => format_string($SITE->shortname, true, [
                'context' => context_course::instance(SITEID),
                'escape' => false,
            ]),
            'output' => $OUTPUT,
            'bodyattributes' => $bodyattributes,
            'sidepreblocks' => '',
            'hasblocks' => false,
            'courseindexopen' => false,
            'blockdraweropen' => false,
            'courseindex' => false,
            'primarymoremenu' => $primarymenu['moremenu'],
            'secondarymoremenu' => false,
            'mobileprimarynav' => $primarymenu['mobileprimarynav'],
            'usermenu' => $primarymenu['user'],
            'langmenu' => $primarymenu['lang'],
            'forceblockdraweropen' => false,
            'regionmainsettingsmenu' => false,
            'hasregionmainsettingsmenu' => false,
            'overflow' => false,
            'headercontent' => false,
            'addblockbutton' => '',
            'hasenrollmodal' => false,
            'maincontentplaceholder' => '',
            'ispubliccourseview' => true,
            'isloggedin' => isloggedin() && !isguestuser(),
            'loginurl' => $loginurl->out(false),
            'config' => ['wwwroot' => $CFG->wwwroot],
        ]
    ));

    // course_drawers is a full-page template (head, body, page_end) — finalize Moodle footer tokens.
    theme_iiidem2_echo_page_template('theme_iiidem2/course_drawers', $templatecontext);
}

/**
 * Preview HTML for a single course-module (page, url, quiz, resource, etc.).
 *
 * @param cm_info $cm
 * @return string
 */
function theme_iiidem2_get_activity_preview_content(cm_info $cm): string {
    global $DB, $OUTPUT;

    if ($cm->modname === 'page') {
        $page = $DB->get_record('page', ['id' => $cm->instance], '*', IGNORE_MISSING);
        if ($page && !empty($page->content)) {
            return format_text($page->content, $page->contentformat, [
                'overflowdiv' => true,
                'noclean' => true,
            ]);
        }
        return '';
    }

    if ($cm->modname === 'url') {
        $url = $DB->get_record('url', ['id' => $cm->instance], '*', IGNORE_MISSING);
        if (!$url || empty($url->externalurl)) {
            return '';
        }
        $videourl = $url->externalurl;
        if (strpos($videourl, 'youtube.com/watch?v=') !== false) {
            parse_str(parse_url($videourl, PHP_URL_QUERY), $params);
            if (!empty($params['v'])) {
                $embedurl = 'https://www.youtube.com/embed/' . $params['v'];
                return '<iframe width="100%" height="400" src="' . s($embedurl) . '" frameborder="0" allowfullscreen></iframe>';
            }
        }
        return '<a href="' . s($videourl) . '" target="_blank" rel="noopener">Open external link</a>';
    }

    if ($cm->modname === 'quiz') {
        $quizpreview = theme_iiidem2_get_quiz_curriculum_preview_context($cm);
        if (!$quizpreview) {
            return '';
        }
        $link = html_writer::link(
            $quizpreview['attempturl'],
            get_string('attemptquiznow', 'quiz'),
            ['class' => 'btn btn-primary btn-sm']
        );
        return html_writer::div($link, 'iiidem-quiz-preview-link');
    }

    if ($cm->modname === 'resource') {
        return theme_iiidem2_get_resource_preview_html($cm);
    }

    if ($cm->modname === 'assign') {
        return '<div class="assignment-preview">Assignment activity</div>';
    }

    return '';
}

/**
 * Enabled fee enrolment instance for a course, if any.
 *
 * @param int $courseid
 * @return stdClass|null
 */
function theme_iiidem2_get_course_fee_enrol_instance(int $courseid): ?stdClass {
    global $CFG;
    require_once($CFG->libdir . '/enrollib.php');

    foreach (enrol_get_instances($courseid, true) as $instance) {
        if ($instance->enrol === 'fee' && (int) $instance->status === ENROL_INSTANCE_ENABLED && $instance->cost > 0) {
            return $instance;
        }
    }
    return null;
}

/**
 * Logged-in university student who must pay the course fee before accessing curriculum previews.
 *
 * @param stdClass $course
 * @param int|null $userid
 * @return bool
 */
function theme_iiidem2_user_needs_course_fee_for_preview(stdClass $course, ?int $userid = null): bool {
    global $USER, $CFG;

    require_once($CFG->libdir . '/enrollib.php');

    if (!isloggedin() || isguestuser()) {
        return false;
    }

    if ($userid === null) {
        $userid = (int) $USER->id;
    }

    $feeinstance = theme_iiidem2_get_course_fee_enrol_instance((int) $course->id);
    if (!$feeinstance) {
        return false;
    }

    if (!\theme_iiidem2\registration_profile::user_requires_course_fee_payment($userid)) {
        return false;
    }

    return !theme_iiidem2_user_has_active_fee_enrolment($userid, (int) $feeinstance->id);
}

/**
 * Whether the current user may expand curriculum activity previews.
 *
 * University students on a paid course must complete fee enrolment. Staff who manage the course
 * and other actively enrolled users may preview without the fee check.
 *
 * @param stdClass $course
 * @param int|null $userid
 * @return bool
 */
function theme_iiidem2_user_can_preview_curriculum(stdClass $course, ?int $userid = null): bool {
    global $USER, $CFG;

    require_once($CFG->libdir . '/enrollib.php');

    if ($userid === null) {
        if (!isloggedin() || isguestuser()) {
            return false;
        }
        $userid = (int) $USER->id;
    }

    $context = context_course::instance($course->id);

    if (has_capability('moodle/course:manageactivities', $context, $userid)
            || has_capability('moodle/course:update', $context, $userid)) {
        return true;
    }

    $feeinstance = theme_iiidem2_get_course_fee_enrol_instance((int) $course->id);
    if ($feeinstance && \theme_iiidem2\registration_profile::user_requires_course_fee_payment($userid)) {
        return theme_iiidem2_user_has_active_fee_enrolment($userid, (int) $feeinstance->id);
    }

    return is_enrolled($context, $userid, '', true);
}

/**
 * Total weekend slots used for progress % (100 ÷ 7 ≈ 14% per slot).
 *
 * @return int
 */
function theme_iiidem2_weekend_progress_slots(): int {
    return 7;
}

/**
 * Progress % from completed weekend units (integer math: units×100÷7).
 *
 * @param int $completedunits
 * @return int 0–100
 */
function theme_iiidem2_weekend_progress_percent(int $completedunits): int {
    $slots = theme_iiidem2_weekend_progress_slots();
    if ($completedunits <= 0) {
        return 0;
    }
    return min(100, intdiv($completedunits * 100, $slots));
}

/**
 * Course list progress % (My courses, mobile, etc.) using weekend slots when applicable.
 *
 * @param stdClass $course
 * @param int $userid 0 = current user
 * @return float|null Weekend-based percent, or null to fall back to core activity completion
 */
function theme_iiidem2_get_course_list_progress_percentage(stdClass $course, int $userid = 0): ?float {
    global $USER;

    if (empty($userid)) {
        $userid = (int) $USER->id;
    }

    $weekend = theme_iiidem2_get_course_weekend_progress($course, $userid);
    if (!empty($weekend['hasprogress']) && !empty($weekend['weekends'])) {
        return (float) $weekend['progress'];
    }

    return null;
}

/**
 * Completed weekend units from section rows (last section = Weekend 6+7 counts as 2).
 *
 * @param array $weekends
 * @return int
 */
function theme_iiidem2_weekend_progress_completed_units(array $weekends): int {
    $units = 0;
    $lastindex = count($weekends) - 1;
    foreach ($weekends as $index => $weekend) {
        if (empty($weekend['complete'])) {
            continue;
        }
        $units += ($index === $lastindex) ? 2 : 1;
    }
    return $units;
}

/**
 * Weekend (course section) progress for a student.
 *
 * A weekend counts as complete when every completion-tracked activity in that section
 * is marked complete for the user (viewed lecture, finished quiz, attended live session, etc.).
 * Progress % uses 7 slots (100÷7); the final section (Weekend 6-7) counts as 2 slots.
 *
 * @param stdClass $course
 * @param int $userid
 * @return array hasprogress, progress, completedweekends, totalweekends, weekends[], progresslabel
 */
function theme_iiidem2_get_course_weekend_progress(stdClass $course, int $userid): array {
    global $CFG;

    require_once($CFG->libdir . '/completionlib.php');

    $defaults = [
        'hasprogress' => false,
        'progress' => 0,
        'completedweekends' => 0,
        'totalweekends' => 0,
        'weekends' => [],
        'progresslabel' => get_string('dashboardnoprogress', 'theme_iiidem2'),
        'coursename' => format_string($course->fullname, true, ['context' => context_course::instance($course->id)]),
        'courseurl' => (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
    ];

    $completion = new completion_info($course);
    if (!$completion->is_enabled() || !$completion->is_tracked_user($userid)) {
        return $defaults;
    }

    try {
        $modinfo = get_fast_modinfo($course, $userid);
    } catch (Exception $e) {
        return $defaults;
    }

    $weekends = [];

    foreach ($modinfo->get_section_info_all() as $section) {
        if ((int) $section->section === 0) {
            continue;
        }
        if (empty($modinfo->sections[$section->section])) {
            continue;
        }

        $tracked = 0;
        $completed = 0;

        foreach ($modinfo->sections[$section->section] as $cmid) {
            $cm = $modinfo->cms[$cmid];
            if (!$cm->uservisible || $cm->deletioninprogress) {
                continue;
            }
            if (!$completion->is_enabled($cm)) {
                continue;
            }

            $tracked++;
            $data = $completion->get_data($cm, false, $userid);
            if (in_array((int) $data->completionstate, [COMPLETION_COMPLETE, COMPLETION_COMPLETE_PASS], true)) {
                $completed++;
            }
        }

        if ($tracked === 0) {
            continue;
        }

        $iscomplete = ($completed >= $tracked);
        $weekends[] = [
            'name' => get_section_name($course, $section),
            'complete' => $iscomplete,
            'completedactivities' => $completed,
            'totalactivities' => $tracked,
            'statuslabel' => $iscomplete
                ? get_string('dashboardweekendcomplete', 'theme_iiidem2')
                : get_string('dashboardweekendpending', 'theme_iiidem2'),
            'statusclass' => $iscomplete ? 'success' : 'muted',
        ];
    }

    $totalslots = theme_iiidem2_weekend_progress_slots();
    if (count($weekends) === 0) {
        return $defaults;
    }

    $doneunits = theme_iiidem2_weekend_progress_completed_units($weekends);
    $percent = theme_iiidem2_weekend_progress_percent($doneunits);

    return [
        'hasprogress' => true,
        'progress' => $percent,
        'completedweekends' => $doneunits,
        'totalweekends' => $totalslots,
        'weekends' => $weekends,
        'hasweekends' => !empty($weekends),
        'progresslabel' => get_string('dashboardweekendprogresslabel', 'theme_iiidem2', (object) [
            'completed' => $doneunits,
            'total' => $totalslots,
            'percent' => $percent,
        ]),
        'coursename' => $defaults['coursename'],
        'courseurl' => $defaults['courseurl'],
    ];
}

/**
 * Curriculum sections/activities for a course (used on course layout and course detail page).
 *
 * @param stdClass $course
 * @return array keys: sections, totalsections, totalactivities
 */
function theme_iiidem2_get_course_curriculum_context(stdClass $course): array {
    global $CFG;

    require_once($CFG->libdir . '/completionlib.php');

    $modinfo = get_fast_modinfo($course);
    $sectionsdata = [];
    $totalactivities = 0;
    $canpreview = theme_iiidem2_user_can_preview_curriculum($course);
    $needspaymentforpreview = theme_iiidem2_user_needs_course_fee_for_preview($course);
    $completion = $canpreview ? new completion_info($course) : null;

    $loginurl = new moodle_url('/login/index.php');
    $loginurl->param('wantsurl', (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false));
    $loginmodal = theme_iiidem2_get_login_modal_context($loginurl->out(false));
    $hasloginmodal = !empty($loginmodal['hasloginmodal']);

    foreach ($modinfo->get_section_info_all() as $section) {
        if ($section->section == 0) {
            continue;
        }

        $activities = [];
        if (!empty($modinfo->sections[$section->section])) {
            foreach ($modinfo->sections[$section->section] as $cmid) {
                $cm = $modinfo->cms[$cmid];
                if (!$cm->uservisible) {
                    continue;
                }

                $previewcontentraw = theme_iiidem2_get_activity_preview_content($cm);
                $haspreviewcontent = $previewcontentraw !== '';

                $trackcompletion = $canpreview && $completion && $completion->is_enabled($cm)
                    && in_array($cm->modname, ['page', 'url', 'resource'], true);

                $activities[] = [
                    'id' => $cm->id,
                    'title' => $cm->name,
                    'type' => $cm->modname,
                    'preview' => $haspreviewcontent,
                    'duration' => '5min',
                    'previewcontent' => $canpreview ? $previewcontentraw : '',
                    'haspreviewcontent' => $haspreviewcontent,
                    'canpreviewcurriculum' => $canpreview,
                    'curriculumpreviewneedspayment' => $needspaymentforpreview,
                    'hasloginmodal' => $hasloginmodal,
                    'trackcompletion' => $trackcompletion,
                    'previewlabel' => 'Preview',
                    'loginurl' => $loginurl->out(false),
                    'isquiz' => ($cm->modname === 'quiz'),
                    'cmid' => $cm->id,
                ];
                $totalactivities++;
            }
        }

        $sectionsdata[] = [
            'id' => $section->id,
            'name' => get_section_name($course, $section),
            'activitycount' => count($activities),
            'activities' => $activities,
        ];
    }

    $paymentmodal = ['hascurriculumpaymentmodal' => false];
    if ($needspaymentforpreview) {
        $feeinstance = theme_iiidem2_get_course_fee_enrol_instance((int) $course->id);
        if ($feeinstance) {
            $currency = $feeinstance->currency ?: 'INR';
            $paymentmodal = [
                'hascurriculumpaymentmodal' => true,
                'coursefeecost' => \core_payment\helper::get_cost_as_string((float) $feeinstance->cost, $currency),
                'coursefeeinstanceid' => (int) $feeinstance->id,
                'showcoursepayment' => !empty(\core_payment\helper::get_available_gateways(
                    'enrol_fee',
                    'fee',
                    (int) $feeinstance->id
                )),
            ];
        } else {
            $paymentmodal = [
                'hascurriculumpaymentmodal' => true,
                'coursefeecost' => '',
                'showcoursepayment' => false,
            ];
        }
    }

    return array_merge($loginmodal, $paymentmodal, [
        'sections' => $sectionsdata,
        'totalsections' => count($sectionsdata),
        'totalactivities' => $totalactivities,
        'canpreviewcurriculum' => $canpreview,
        'curriculumpreviewneedspayment' => $needspaymentforpreview,
        'curriculumtrackcompletion' => $canpreview,
        'curriculumcompletionajaxurl' => (new moodle_url('/theme/iiidem2/ajax/mark_activity_viewed.php'))->out(false),
        'previewredirectlogin' => !$canpreview && !$needspaymentforpreview,
        'loginurl' => $loginurl->out(false),
        'previewlabel' => 'Preview',
    ]);
}

/**
 * Mark a curriculum preview as viewed for activity completion (page, url, resource).
 *
 * @param int $cmid Course-module id.
 * @param int|null $userid Defaults to current user.
 * @return array{success: bool, already?: bool, error?: string}
 */
function theme_iiidem2_mark_curriculum_activity_viewed(int $cmid, ?int $userid = null): array {
    global $USER, $DB, $CFG;

    require_once($CFG->libdir . '/completionlib.php');

    if ($userid === null) {
        if (!isloggedin() || isguestuser()) {
            return ['success' => false, 'error' => 'notloggedin'];
        }
        $userid = (int) $USER->id;
    }

    try {
        $cmrecord = get_coursemodule_from_id(null, $cmid, 0, false, MUST_EXIST);
    } catch (Exception $e) {
        return ['success' => false, 'error' => 'invalidcm'];
    }

    $course = get_course($cmrecord->course);
    require_course_login($course, false, $cmrecord);

    if (!theme_iiidem2_user_can_preview_curriculum($course, $userid)) {
        return ['success' => false, 'error' => 'nopermission'];
    }

    $modinfo = get_fast_modinfo($course, $userid);
    $cm = $modinfo->get_cm($cmid);

    if (!$cm->uservisible || $cm->deletioninprogress) {
        return ['success' => false, 'error' => 'notvisible'];
    }

    if (!in_array($cm->modname, ['page', 'url', 'resource'], true)) {
        return ['success' => false, 'error' => 'unsupported'];
    }

    $completion = new completion_info($course);
    if (!$completion->is_enabled() || !$completion->is_enabled($cm)) {
        return ['success' => false, 'error' => 'completionnotenabled'];
    }

    $data = $completion->get_data($cm, false, $userid);
    if (in_array((int) $data->completionstate, [COMPLETION_COMPLETE, COMPLETION_COMPLETE_PASS], true)) {
        return ['success' => true, 'already' => true];
    }

    $context = context_module::instance($cmid);
    $needsviewevent = empty($data->viewed);

    if ($needsviewevent) {
        switch ($cm->modname) {
            case 'page':
                require_once($CFG->dirroot . '/mod/page/lib.php');
                $instance = $DB->get_record('page', ['id' => $cm->instance], '*', MUST_EXIST);
                page_view($instance, $course, $cmrecord, $context);
                break;
            case 'url':
                require_once($CFG->dirroot . '/mod/url/lib.php');
                $instance = $DB->get_record('url', ['id' => $cm->instance], '*', MUST_EXIST);
                url_view($instance, $course, $cmrecord, $context);
                break;
            case 'resource':
                require_once($CFG->dirroot . '/mod/resource/lib.php');
                $instance = $DB->get_record('resource', ['id' => $cm->instance], '*', MUST_EXIST);
                resource_view($instance, $course, $cmrecord, $context);
                break;
        }
    }

    // page_view/url_view may record "viewed" without marking complete — always finish completion.
    $data = $completion->get_data($cm, false, $userid);
    if (empty($data->viewed)) {
        $data->viewed = COMPLETION_VIEWED;
        $completion->internal_set_data($cm, $data);
    }

    $completion->update_state($cm, COMPLETION_COMPLETE, $userid);

    $data = $completion->get_data($cm, false, $userid);
    if (!in_array((int) $data->completionstate, [COMPLETION_COMPLETE, COMPLETION_COMPLETE_PASS], true)) {
        return ['success' => false, 'error' => 'notcomplete'];
    }

    return ['success' => true];
}

/**
 * Login modal context for guests (Bootstrap modal with core login form).
 *
 * Sets SESSION->wantsurl so a successful login returns to the course page.
 *
 * @param string|null $wantsurl Return URL after login (relative or absolute local URL).
 * @return array
 */
function theme_iiidem2_get_login_modal_context(?string $wantsurl = null): array {
    global $SESSION, $CFG;

    if (isloggedin() && !isguestuser()) {
        return [
            'hasloginmodal' => false,
        ];
    }

    if (!empty($wantsurl)) {
        $SESSION->wantsurl = (new moodle_url($wantsurl))->out(false);
    }

    return [
        'hasloginmodal' => true,
        'loginurl' => (new moodle_url('/login/index.php'))->out(false),
        'logintoken' => \core\session\manager::get_login_token(),
        'forgotpasswordurl' => (new moodle_url('/login/forgot_password.php'))->out(false),
        'registerurl' => theme_iiidem2_get_register_url(),
        'loginsignuplabel' => get_string('loginsignup', 'theme_iiidem2'),
        'canloginbyemail' => !empty($CFG->authloginviaemail),
    ];
}

/**
 * Structured question rows for quiz templates (from course quiz instance id).
 *
 * @param int $quizid Quiz instance id from {quiz}.id
 * @return array
 */
function theme_iiidem2_get_quiz_questions_template_data(int $quizid): array {
    global $DB;

    $rows = [];
    $slots = $DB->get_records('quiz_slots', ['quizid' => $quizid], 'slot');
    foreach ($slots as $slot) {
        $reference = $DB->get_record('question_references', ['itemid' => $slot->id]);
        if (!$reference) {
            continue;
        }
        $versions = $DB->get_records_sql(
            'SELECT qv.questionid
               FROM {question_versions} qv
              WHERE qv.questionbankentryid = ?
           ORDER BY qv.version DESC',
            [$reference->questionbankentryid],
            0,
            1
        );
        $version = reset($versions);
        if (!$version) {
            continue;
        }
        $question = $DB->get_record('question', ['id' => $version->questionid], '*', IGNORE_MISSING);
        if (!$question) {
            continue;
        }

        $answers = [];
        $answerrecords = $DB->get_records('question_answers', ['question' => $question->id], 'id');
        $letterindex = 0;
        foreach ($answerrecords as $answer) {
            $answers[] = [
                'text' => format_text($answer->answer, $answer->answerformat, ['para' => false]),
                'letter' => chr(97 + $letterindex),
            ];
            $letterindex++;
        }

        $rows[] = [
            'number' => count($rows) + 1,
            'text' => format_text($question->questiontext, $question->questiontextformat, ['para' => false]),
            'answers' => $answers,
            'hasanswers' => !empty($answers),
        ];
    }

    return $rows;
}

/**
 * Live Moodle attempt form HTML (only call before header / in preload).
 *
 * @param cm_info $cm
 * @return string
 */
function theme_iiidem2_get_quiz_attempt_form_html(cm_info $cm): string {
    global $USER, $CFG, $PAGE, $DB;

    if (!isloggedin() || isguestuser()) {
        return '';
    }

    require_once($CFG->dirroot . '/mod/quiz/locallib.php');

    $quiz = $DB->get_record('quiz', ['id' => $cm->instance], '*', IGNORE_MISSING);
    if (!$quiz) {
        return '';
    }

    $unfinished = quiz_get_user_attempts((int) $quiz->id, $USER->id, 'unfinished', true);
    if (empty($unfinished)) {
        return '';
    }

    $attemptrec = reset($unfinished);

    try {
        $attemptobj = quiz_create_attempt_handling_errors($attemptrec->id, $cm->id);
    } catch (Throwable $e) {
        return '';
    }

    if ($attemptobj->get_userid() != $USER->id || $attemptobj->is_finished()) {
        return '';
    }

    $accessmanager = $attemptobj->get_access_manager(time());
    if ($accessmanager->prevent_access() || $accessmanager->is_preflight_check_required($attemptobj->get_attemptid())) {
        return '';
    }

    $page = $attemptobj->get_currentpage();
    $slots = $attemptobj->get_slots($page);
    if (empty($slots) || !$attemptobj->set_currentpage($page)) {
        return '';
    }

    static $assetsregistered = false;
    if (!$assetsregistered) {
        $assetsregistered = true;
        $autosaveperiod = get_config('quiz', 'autosaveperiod');
        if ($autosaveperiod) {
            $PAGE->requires->string_for_js('strftimedatetimeshortaccurate', 'langconfig');
            $PAGE->requires->string_for_js('lastautosave', 'quiz');
            $PAGE->requires->yui_module('moodle-mod_quiz-autosave', 'M.mod_quiz.autosave.init', [$autosaveperiod]);
        }
        $PAGE->requires->js_init_call('M.mod_quiz.init_attempt_form', null, false, quiz_get_js_module());
        \core\session\manager::keepalive();
    }

    /** @var \mod_quiz\output\renderer $quizoutput */
    $quizoutput = $PAGE->get_renderer('mod_quiz');
    $nextpage = $attemptobj->is_last_page($page) ? -1 : $page + 1;

    return $quizoutput->attempt_form($attemptobj, $page, $slots, $cm->id, $nextpage);
}

/**
 * Whether the current user has an unfinished attempt for a quiz.
 *
 * @param int $quizid Quiz instance id.
 * @return bool
 */
function theme_iiidem2_user_has_unfinished_quiz_attempt(int $quizid): bool {
    global $USER, $CFG;

    if (!isloggedin() || isguestuser()) {
        return false;
    }

    require_once($CFG->dirroot . '/mod/quiz/locallib.php');

    return !empty(quiz_get_user_attempts($quizid, $USER->id, 'unfinished', true));
}

/**
 * URL for the full quiz attempt page (in-progress attempt or quiz view).
 *
 * @param stdClass $quiz Quiz instance from {quiz}.
 * @param cm_info $cm Course module.
 * @return string
 */
function theme_iiidem2_get_quiz_attempt_page_url(stdClass $quiz, cm_info $cm): string {
    global $USER, $CFG;

    require_once($CFG->dirroot . '/mod/quiz/locallib.php');

    if (isloggedin() && !isguestuser()) {
        $unfinished = quiz_get_user_attempts((int) $quiz->id, $USER->id, 'unfinished', true);
        if (!empty($unfinished)) {
            $attempt = reset($unfinished);
            return (new moodle_url('/mod/quiz/attempt.php', [
                'attempt' => $attempt->id,
                'cmid' => $cm->id,
            ]))->out(false);
        }
    }

    return (new moodle_url('/mod/quiz/view.php', ['id' => $cm->id]))->out(false);
}

/**
 * Build quiz section context (cached per request).
 *
 * @param stdClass $course
 * @return array keys: quizzes, hasquizzes, quizcount
 */
function theme_iiidem2_build_course_quizzes_context(stdClass $course): array {
    global $DB;

    $modinfo = get_fast_modinfo($course);
    $quizzes = [];

    foreach ($modinfo->get_instances_of('quiz') as $cm) {
        if (!$cm->uservisible) {
            continue;
        }

        $quiz = $DB->get_record('quiz', ['id' => $cm->instance], '*', IGNORE_MISSING);
        if (!$quiz) {
            continue;
        }

        $modcontext = context_module::instance($cm->id);
        $section = $modinfo->get_section_info($cm->sectionnum, MUST_EXIST);
        $attemptpageurl = theme_iiidem2_get_quiz_attempt_page_url($quiz, $cm);
        $hasunfinished = theme_iiidem2_user_has_unfinished_quiz_attempt((int) $quiz->id);
        $questions = theme_iiidem2_get_quiz_questions_template_data((int) $quiz->id);
        $attempthtml = $hasunfinished ? theme_iiidem2_get_quiz_attempt_form_html($cm) : '';

        $quizzes[] = [
            'cmid' => $cm->id,
            'quizid' => (int) $quiz->id,
            'name' => format_string($cm->name, true, ['context' => $modcontext]),
            'sectionname' => get_section_name($course, $section),
            'questioncount' => count($questions),
            'timelimit' => !empty($quiz->timelimit) ? format_time($quiz->timelimit) : get_string('none', 'moodle'),
            'hasquiztimelimit' => !empty($quiz->timelimit),
            'intro' => format_text($quiz->intro, $quiz->introformat, ['context' => $modcontext]),
            'hasintro' => trim(strip_tags($quiz->intro)) !== '',
            'viewurl' => (new moodle_url('/mod/quiz/view.php', ['id' => $cm->id]))->out(false),
            'attemptpageurl' => $attemptpageurl,
            'hasunfinished' => $hasunfinished,
            'hasattempthtml' => $attempthtml !== '',
            'attempthtml' => $attempthtml,
            'questions' => $questions,
            'hasquestions' => !empty($questions),
            'anchor' => 'quiz-' . $cm->id,
        ];
    }

    $quizcount = count($quizzes);

    return [
        'quizzes' => $quizzes,
        'hasquizzes' => !empty($quizzes),
        'quizcount' => $quizcount,
        'coursequizzeslead' => get_string('coursequizzes_lead', 'theme_iiidem2', $quizcount),
        'haslivequiz' => !empty(array_filter($quizzes, static fn($q) => !empty($q['hasattempthtml']))),
    ];
}

/**
 * Preload course layout Mustache context before header() (safe page state).
 *
 * @param stdClass $course
 * @return void
 */
function theme_iiidem2_preload_course_layout_context(stdClass $course): void {
    static $done = [];
    $courseid = (int) $course->id;
    if (isset($done[$courseid])) {
        return;
    }
    $done[$courseid] = true;

    theme_iiidem2_set_preloaded_course_layout_context($course, [
        'display' => theme_iiidem2_get_course_display_context($course),
        'curriculum' => theme_iiidem2_get_course_curriculum_context($course),
        'quizzes' => theme_iiidem2_get_course_quizzes_context($course),
    ]);
}

/**
 * @param stdClass $course
 * @param array $context
 * @return void
 */
function theme_iiidem2_set_preloaded_course_layout_context(stdClass $course, array $context): void {
    $store = &theme_iiidem2_preloaded_course_layout_store();
    $store[(int) $course->id] = $context;
}

/**
 * @param stdClass $course
 * @return array|null
 */
function theme_iiidem2_get_preloaded_course_layout_context(stdClass $course): ?array {
    $store = theme_iiidem2_preloaded_course_layout_store();
    return $store[(int) $course->id] ?? null;
}

/**
 * @return array
 */
function &theme_iiidem2_preloaded_course_layout_store(): array {
    static $store = [];
    return $store;
}

/**
 * All quiz activities in a course for the course / course-detail pages.
 *
 * @param stdClass $course
 * @return array keys: quizzes, hasquizzes, quizcount
 */
function theme_iiidem2_get_course_quizzes_context(stdClass $course): array {
    static $cache = [];
    $courseid = (int) $course->id;
    if (!isset($cache[$courseid])) {
        $cache[$courseid] = theme_iiidem2_build_course_quizzes_context($course);
    }
    return $cache[$courseid];
}

/**
 * Instructors, FAQs, and hero fields for course detail / course layout.
 *
 * @param stdClass $course
 * @return array
 */
function theme_iiidem2_get_course_display_context(stdClass $course): array {
    global $DB, $PAGE;

    $context = context_course::instance($course->id);
    $courseimage = theme_iiidem2_get_course_image_url($course);

    $userfields = \core_user\fields::for_userpic()->get_sql('u', false, '', '', false)->selects;
    $roles = $DB->get_records_list('role', 'shortname', ['editingteacher', 'teacher']);
    $instructordata = [];

    foreach ($roles as $role) {
        $users = get_role_users($role->id, $context, false, $userfields . ', u.description');
        foreach ($users as $teacher) {
            if (isset($instructordata[$teacher->id])) {
                continue;
            }
            $userpicture = new user_picture($teacher);
            $userpicture->size = 150;
            $instructordata[$teacher->id] = [
                'name' => fullname($teacher),
                'image' => $userpicture->get_url($PAGE)->out(false),
                'role' => role_get_name($role, $context),
                'bio' => !empty($teacher->description)
                    ? strip_tags($teacher->description)
                    : get_string('nobio', 'theme_iiidem2'),
                'profileurl' => (new moodle_url('/user/profile.php', ['id' => $teacher->id]))->out(false),
            ];
        }
    }

    $faqs = [];
    $faqsraw = $DB->get_records('local_coursefaq', ['courseid' => $course->id]);
    foreach ($faqsraw as $faq) {
        $faqs[] = [
            'id' => $faq->id,
            'question' => $faq->question,
            'answer' => $faq->answer,
        ];
    }

    return [
        'coursename' => format_string($course->fullname),
        'courseshortname' => format_string($course->shortname),
        'coursesummary' => format_text($course->summary, $course->summaryformat, ['context' => $context]),
        'hascoursesummary' => trim(strip_tags($course->summary)) !== '',
        'courseimage' => $courseimage,
        'instructordata' => array_values($instructordata),
        'hasinstructors' => !empty($instructordata),
        'faqs' => $faqs,
        'hasfaqs' => !empty($faqs),
        'coursedetailurl' => theme_iiidem2_get_course_detail_url($course),
        'entercourseurl' => (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
    ];
}

/**
 * Whether the user has an active (non-suspended, in-date) fee enrolment instance.
 *
 * @param int $userid
 * @param int $feeinstanceid enrol.id for the fee instance
 * @return bool
 */
function theme_iiidem2_user_has_active_fee_enrolment(int $userid, int $feeinstanceid): bool {
    global $DB;

    $ue = $DB->get_record('user_enrolments', ['userid' => $userid, 'enrolid' => $feeinstanceid]);
    if (!$ue || (int) $ue->status !== ENROL_USER_ACTIVE) {
        return false;
    }

    $now = time();
    if (!empty($ue->timestart) && (int) $ue->timestart > $now) {
        return false;
    }
    if (!empty($ue->timeend) && (int) $ue->timeend < $now) {
        return false;
    }

    return true;
}

/**
 * Course fee / PNB payment context for the course view marketing layout.
 *
 * @param stdClass $course
 * @return array
 */
function theme_iiidem2_get_course_fee_payment_context(stdClass $course): array {
    global $CFG, $USER, $DB;

    require_once($CFG->libdir . '/enrollib.php');

    $defaults = [
        'hascoursefee' => false,
        'showcoursepayment' => false,
        'coursefeeloginrequired' => false,
    ];

    $feeinstance = theme_iiidem2_get_course_fee_enrol_instance((int) $course->id);

    if (!$feeinstance) {
        return $defaults;
    }

    $currency = $feeinstance->currency ?: 'INR';
    $costdisplay = \core_payment\helper::get_cost_as_string((float) $feeinstance->cost, $currency);
    $description = get_string('purchasedescription', 'enrol_fee', format_string($course->fullname));
    $successurl = (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false);
    $loginurl = (new moodle_url('/login/index.php', [
        'wantsurl' => (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
    ]))->out(false);

    if (!isloggedin() || isguestuser()) {
        return [
            'hascoursefee' => true,
            'showcoursepayment' => false,
            'coursefeecost' => $costdisplay,
            'coursefeeloginrequired' => true,
            'coursefeeloginurl' => $loginurl,
        ];
    }

    // Fee payment is for registered university students only (not EMB / working / instructor).
    if (!\theme_iiidem2\registration_profile::user_requires_course_fee_payment((int) $USER->id)) {
        return $defaults;
    }

    // Hide payment only when fee enrolment is currently active (suspended users can pay again).
    if (theme_iiidem2_user_has_active_fee_enrolment((int) $USER->id, (int) $feeinstance->id)) {
        return $defaults;
    }

    $gateways = \core_payment\helper::get_available_gateways('enrol_fee', 'fee', (int) $feeinstance->id);

    return [
        'hascoursefee' => true,
        'showcoursepayment' => !empty($gateways),
        'coursefeecost' => $costdisplay,
        'coursefeeinstanceid' => (int) $feeinstance->id,
        'coursefeedescription' => $description,
        'coursefeesuccessurl' => $successurl,
        'coursefeeloginrequired' => false,
        'haspnbgateway' => in_array('pnb', $gateways, true),
    ];
}

/**
 * Full Mustache context for theme_iiidem2/pages/course-detail.
 *
 * @param stdClass $course
 * @return array
 */
function theme_iiidem2_get_course_detail_context(stdClass $course): array {
    global $USER, $CFG;

    $curriculum = theme_iiidem2_get_course_curriculum_context($course);
    $quizzes = theme_iiidem2_get_course_quizzes_context($course);
    $display = theme_iiidem2_get_course_display_context($course);

    $loginurl = new moodle_url('/login/index.php');
    $loginurl->param('wantsurl', theme_iiidem2_get_course_detail_url($course));

    $wantsurl = theme_iiidem2_get_course_detail_url($course);

    return array_merge(
        $display,
        $curriculum,
        $quizzes,
        theme_iiidem2_get_login_modal_context($wantsurl),
        [
            'courseid' => $course->id,
            'isloggedin' => isloggedin() && !isguestuser(),
            'loginurl' => $loginurl->out(false),
            'config' => ['wwwroot' => $CFG->wwwroot],
        ]
    );
}

/**
 * Render course detail marketing page.
 *
 * @param stdClass $course
 * @return void
 */
/**
 * Public enrol preview (guests): course hero, summary, instructors, login CTA.
 *
 * @param stdClass $course
 * @return void
 */
function theme_iiidem2_render_enrol_preview_page(stdClass $course): void {
    global $OUTPUT, $PAGE, $SITE, $CFG;

    $coursecontext = context_course::instance($course->id);
    $PAGE->set_context($coursecontext);
    $PAGE->set_course($course);
    $PAGE->set_url(new moodle_url('/enrol/index.php', ['id' => $course->id]));
    $PAGE->set_pagelayout('marketing');
    $PAGE->set_cacheable(true);
    $PAGE->set_title(format_string($course->fullname));
    $PAGE->set_heading(format_string($course->fullname));

    $primarymenu = theme_iiidem2_export_primary_menu($PAGE);

    $display = theme_iiidem2_get_course_display_context($course);
    $loginurl = new moodle_url('/login/index.php');
    $loginurl->param('wantsurl', (new moodle_url('/enrol/index.php', ['id' => $course->id]))->out(false));

    $templatecontext = theme_iiidem2_merge_footer_context(array_merge($display, [
        'sitename' => format_string($SITE->fullname),
        'output' => $OUTPUT,
        'primarymoremenu' => $primarymenu['moremenu'],
        'mobileprimarynav' => $primarymenu['mobileprimarynav'],
        'usermenu' => $primarymenu['user'],
        'langmenu' => $primarymenu['lang'],
        'isloggedin' => isloggedin() && !isguestuser(),
        'loginurl' => $loginurl->out(false),
        'config' => ['wwwroot' => $CFG->wwwroot],
    ]));

    echo $OUTPUT->doctype();
    ?>
<html <?php echo $OUTPUT->htmlattributes(); ?>>
<head>
    <?php echo $OUTPUT->standard_head_html(); ?>
</head>
<body <?php echo $OUTPUT->body_attributes(['pagelayout-marketing', 'iiidem-enrol-preview']); ?>>
<?php echo $OUTPUT->standard_top_of_body_html(); ?>
<?php
    echo $OUTPUT->render_from_template('theme_iiidem2/pages/enrol-preview', $templatecontext);
?>
</body>
</html>
    <?php
}

function theme_iiidem2_render_course_detail_page(stdClass $course): void {
    global $OUTPUT, $PAGE, $SITE;

    $primarymenu = theme_iiidem2_export_primary_menu($PAGE);

    $detailcontext = theme_iiidem2_get_course_detail_context($course);

    if (!empty($detailcontext['curriculumtrackcompletion'])) {
        // Loaded via pages/course-detail.mustache {{#js}} when that layout is used.
        $detailcontext['curriculumcompletionajaxurl'] = $detailcontext['curriculumcompletionajaxurl']
            ?? (new moodle_url('/theme/iiidem2/ajax/mark_activity_viewed.php'))->out(false);
    }

    $templatecontext = theme_iiidem2_merge_footer_context(array_merge(
        $detailcontext,
        [
            'sitename' => format_string($SITE->fullname),
            'output' => $OUTPUT,
            'primarymoremenu' => $primarymenu['moremenu'],
            'mobileprimarynav' => $primarymenu['mobileprimarynav'],
            'usermenu' => $primarymenu['user'],
            'langmenu' => $primarymenu['lang'],
        ]
    ));

    echo $OUTPUT->doctype();
    ?>
<html <?php echo $OUTPUT->htmlattributes(); ?>>
<head>
    <?php echo $OUTPUT->standard_head_html(); ?>
</head>
<body <?php echo $OUTPUT->body_attributes(['pagelayout-marketing', 'iiidem-course-detail']); ?>>
<?php echo $OUTPUT->standard_top_of_body_html(); ?>
<?php
    echo $OUTPUT->render_from_template('theme_iiidem2/pages/course-detail', $templatecontext);
?>
</body>
</html>
    <?php
}

/**
 * Curriculum accordion preview context for a quiz activity.
 *
 * @param cm_info $cm Course module.
 * @return array|null Mustache context or null if not a quiz.
 */
function theme_iiidem2_get_quiz_curriculum_preview_context(cm_info $cm): ?array {
    global $DB;

    if ($cm->modname !== 'quiz') {
        return null;
    }

    $quiz = $DB->get_record('quiz', ['id' => $cm->instance], '*', IGNORE_MISSING);
    if (!$quiz) {
        return null;
    }

    $timelimit = !empty($quiz->timelimit) ? format_time($quiz->timelimit) : get_string('none', 'moodle');
    $questions = theme_iiidem2_get_quiz_questions_template_data((int) $quiz->id);

    return [
        'title' => format_string($cm->name),
        'cmid' => $cm->id,
        'quizid' => (int) $quiz->id,
        'questioncount' => count($questions),
        'timelimit' => $timelimit,
        'previewcontent' => '',
        'haspreviewcontent' => false,
        'attempturl' => theme_iiidem2_get_quiz_attempt_page_url($quiz, $cm),
    ];
}

/**
 * Per-page init: Bootstrap 5 on marketing layouts, redirect /my/ to role dashboard.
 *
 * @param moodle_page $page
 */
function theme_iiidem2_page_init($page) {
    global $CFG;

    $page->requires->js_call_amd('theme_iiidem2/footer-popover', 'init');

    if (isloggedin() && !isguestuser() && !CLI_SCRIPT && !AJAX_SCRIPT && !WS_SERVER) {
        $path = $page->url->get_path(false);
        if ($path === '/my' || $path === '/my/index.php') {
            redirect(theme_iiidem2_get_dashboard_url());
        }
    }

    $coursequizcss = new moodle_url('/theme/iiidem2/style/course-quiz-mcq.css');
    if (theme_iiidem2_is_quiz_attempt_page($page) && theme_iiidem2_use_custom_quiz_ui($page)) {
        theme_iiidem2_apply_custom_quiz_page_assets($page);
        $page->add_body_class('iiidem-quiz-attempt-active');
        if ($page->state < moodle_page::STATE_IN_BODY) {
            $page->set_pagelayout('quizattempt');
        }
    } else if (theme_iiidem2_is_custom_quiz_page($page)) {
        theme_iiidem2_apply_custom_quiz_page_assets($page);
    } else if (theme_iiidem2_is_live_class_page($page)) {
        theme_iiidem2_apply_live_class_page_assets($page);
    } else if ($page->url->get_path(false) === '/course-detail'
            || strpos($page->url->get_path(false), '/course-detail/') === 0) {
        $page->requires->css(new moodle_url('/theme/iiidem2/style/course-quiz-mcq.css'));
    }

    // BS5 accordions/tabs only where templates use data-bs-* (not site home — avoids slow CDN on every visit).
    if (in_array($page->pagelayout, ['marketing', 'incourse'], true)
        || strpos($page->bodyclasses, 'iiidem-course-detail') !== false
        || strpos($page->bodyclasses, 'iiidem-enrol-preview') !== false) {
        $bs5css = $CFG->dirroot . '/theme/iiidem2/style/bootstrap5.min.css';
        $bs5js = $CFG->dirroot . '/theme/iiidem2/style/bootstrap5.bundle.min.js';
        if (is_readable($bs5css)) {
            $page->requires->css(new moodle_url('/theme/iiidem2/style/bootstrap5.min.css'));
        }
        if (is_readable($bs5js)) {
            $page->requires->js(new moodle_url('/theme/iiidem2/style/bootstrap5.bundle.min.js'), true);
        }
    }
}
