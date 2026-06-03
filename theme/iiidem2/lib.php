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
    global $USER, $CFG;

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

    if (has_capability('moodle/course:create', $systemcontext, $userid)) {
        return 'teacher';
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

    return 'student';
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
            break;
        case 'teacher':
            $context['isteacher'] = true;
            $context = array_merge($context, \theme_iiidem2\teacher_dashboard::get_context($userid));
            break;
        default:
            $context['isstudent'] = true;
            $context = array_merge($context, \theme_iiidem2\student_dashboard::get_context($userid));
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
        if ((int) $course->id === SITEID) {
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
function theme_iiidem2_render_public_page(string $template, array $extracontext = [], string $bodyclass = 'pagelayout-marketing'): void {
    global $OUTPUT, $PAGE, $SITE;

    require_course_login($SITE);

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

    // BS5 accordions/tabs only where templates use data-bs-* (not site home — avoids slow CDN on every visit).
    if (in_array($page->pagelayout, ['marketing', 'course', 'incourse'], true)) {
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
