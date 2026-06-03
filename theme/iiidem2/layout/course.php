<?php
/**
 * A drawer based layout for the boost theme.
 *
 * @package   theme_boost
 * @copyright 2021 Bas Brands
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/behat/lib.php');
require_once($CFG->dirroot . '/course/lib.php');

// Add block button in editing mode.
$addblockbutton = $OUTPUT->addblockbutton();

if (isloggedin()) {
    $courseindexopen = (get_user_preferences('drawer-open-index', true) == true);
    $blockdraweropen = (get_user_preferences('drawer-open-block') == true);
} else {
    $courseindexopen = false;
    $blockdraweropen = false;
}

if (defined('BEHAT_SITE_RUNNING') && get_user_preferences('behat_keep_drawer_closed') != 1) {
    $blockdraweropen = true;
}

$extraclasses = ['uses-drawers'];
if ($courseindexopen) {
    $extraclasses[] = 'drawer-open-index';
}

$blockshtml = $OUTPUT->blocks('side-pre');
$hasblocks = (strpos($blockshtml, 'data-block=') !== false || !empty($addblockbutton));
if (!$hasblocks) {
    $blockdraweropen = false;
}
$courseindex = core_course_drawer();
if (!$courseindex) {
    $courseindexopen = false;
}

$bodyattributes = $OUTPUT->body_attributes($extraclasses);
//$forceblockdraweropen = $OUTPUT->firstview_fakeblocks();
$forceblockdraweropen = false;

$secondarynavigation = false;
$overflow = '';
if ($PAGE->has_secondary_navigation()) {
    $tablistnav = $PAGE->has_tablist_secondary_navigation();
    $moremenu = new \core\navigation\output\more_menu($PAGE->secondarynav, 'nav-tabs', true, $tablistnav);
    $secondarynavigation = $moremenu->export_for_template($OUTPUT);
    $overflowdata = $PAGE->secondarynav->get_overflow_menu_data();
    if (!is_null($overflowdata)) {
        $overflow = $overflowdata->export_for_template($OUTPUT);
    }
}

$primary = new core\navigation\output\primary($PAGE);
$renderer = $PAGE->get_renderer('core');
$primarymenu = $primary->export_for_template($renderer);
$buildregionmainsettings = !$PAGE->include_region_main_settings_in_header_actions() && !$PAGE->has_secondary_navigation();
// If the settings menu will be included in the header then don't add it here.
$regionmainsettingsmenu = $buildregionmainsettings ? $OUTPUT->region_main_settings_menu() : false;

$header = $PAGE->activityheader;
$headercontent = $header->export_for_template($renderer);

// fetch course image
$courseimage = '';
$context = context_course::instance($COURSE->id);
$fs = get_file_storage();
$files = $fs->get_area_files(
    $context->id,
    'course',
    'overviewfiles',
    0,
    'itemid, filepath, filename',
    false
);
foreach ($files as $file) {
    if ($file->is_valid_image()) {
        $courseimage = \moodle_url::make_pluginfile_url(
            $file->get_contextid(),
            $file->get_component(),
            $file->get_filearea(),
            null,
            $file->get_filepath(),
            $file->get_filename()
        );
        break;
    }
}
$templatecontext = [
    'coursename' => format_string($COURSE->fullname),
    'coursesummary' => format_text(
        $COURSE->summary,
        $COURSE->summaryformat
    ),
    //'courseimage' => $courseimage->out(false),
    'courseimage' => is_object($courseimage) && method_exists($courseimage, 'out')
    ? $courseimage->out(false)
    : (string)$courseimage,

];

// fetch instructor list

global $DB, $COURSE, $PAGE, $OUTPUT;

use core_user\fields;

//$templatecontext = [];

// Course context.
$context = context_course::instance($COURSE->id);

// Required fields for user picture.
$userfields = fields::for_userpic()
    ->get_sql('u', false, '', '', false)->selects;

// Get teacher roles dynamically.
$roles = $DB->get_records_list(
    'role',
    'shortname',
    ['editingteacher', 'teacher']
);

// Build instructor cards for Mustache (one entry per user with teacher / editingteacher role).
$instructordata = [];

foreach ($roles as $role) {
    $users = get_role_users(
        $role->id,
        $context,
        false,
        $userfields . ', u.description'
    );

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

$instructordata = array_values($instructordata);
//print_r($instructordata);
//die();


/*$handler = core_customfield\handler::get_handler(
    'core_course',
    'course'
);
$customfields = $handler->get_instance_data($COURSE->id);
$instructors = [];
foreach ($customfields as $data) {
    $shortname = $data->get_field()->get('shortname');
    if ($shortname == 'instructors') {
        $rows = explode("\n", $data->get_value());
        foreach ($rows as $row) {
            $parts = explode('|', trim($row));
            if (count($parts) >= 3) {
                $instructors[] = [
                    'name' => trim($parts[0]),
                    'role' => trim($parts[1]),
                    'image' => trim($parts[2]),
                ];
            }
        }
    }
}*/


global $DB, $COURSE;
$faqsraw = $DB->get_records('local_coursefaq', ['courseid' => $COURSE->id]);
$faqs = [];
foreach ($faqsraw as $faq) {
    $faqs[] = [
        'id' => $faq->id,
        'question' => $faq->question,
        'answer' => $faq->answer
    ];
}
$templatecontext['faqs'] = $faqs;

/*echo '<pre>';
print_r($faqs);
echo '</pre>';
die();*/
//-------------------------------------------------------------------

global $DB, $COURSE;

$modinfo = get_fast_modinfo($COURSE);

$sectionsdata = [];
$totalactivities = 0;

foreach ($modinfo->get_section_info_all() as $section) {

    // Skip general section
    if ($section->section == 0) {
        continue;
    }

    $sectionname = get_section_name($COURSE, $section);

    $activities = [];

    if (!empty($modinfo->sections[$section->section])) {

        foreach ($modinfo->sections[$section->section] as $cmid) {

            $cm = $modinfo->cms[$cmid];

            // Skip hidden
            if (!$cm->uservisible) {
                continue;
            }

            $previewcontent = '';
            $activitytype = $cm->modname;
            $duration = '5min';

            /*
             * PAGE MODULE
             */
            if ($cm->modname === 'page') {

                $page = $DB->get_record('page', [
                    'id' => $cm->instance
                ]);

                if ($page && !empty($page->content)) {

                    $previewcontent = format_text(
                        $page->content,
                        $page->contentformat,
                        [
                            'overflowdiv' => true,
                            'noclean' => true
                        ]
                    );
                }
            }

            /*
             * URL MODULE (YouTube support)
             */
            if ($cm->modname === 'url') {

                $url = $DB->get_record('url', [
                    'id' => $cm->instance
                ]);

                if ($url && !empty($url->externalurl)) {

                    $videoUrl = $url->externalurl;

                    // Convert YouTube URL
                    if (strpos($videoUrl, 'youtube.com/watch?v=') !== false) {

                        parse_str(
                            parse_url($videoUrl, PHP_URL_QUERY),
                            $params
                        );

                        if (!empty($params['v'])) {

                            $embedurl = 'https://www.youtube.com/embed/' . $params['v'];

                            $previewcontent = '
                                <iframe
                                    width="100%"
                                    height="400"
                                    src="' . $embedurl . '"
                                    frameborder="0"
                                    allowfullscreen>
                                </iframe>
                            ';
                        }

                    } else {

                        // Normal external link
                        $previewcontent = '
                            <a href="' . $videoUrl . '" target="_blank">
                                Open External Link
                            </a>
                        ';
                    }
                }
            }

         
            /*
             * QUIZ MODULE
             */

            if ($cm->modname === 'quiz') {

                $quiz = $DB->get_record('quiz', [
                    'id' => $cm->instance
                ]);

                if ($quiz) {

                    $quizurl = new moodle_url('/mod/quiz/view.php', [
                        'id' => $cm->id
                    ]);

                    // Count questions
                    $questioncount = $DB->count_records('quiz_slots', [
                        'quizid' => $quiz->id
                    ]);

                    // Time limit
                    $timelimit = !empty($quiz->timelimit)
                        ? gmdate("i:s", $quiz->timelimit)
                        : 'No Limit';

                    // Start HTML
                    $previewcontent = '
                        <div class="quiz-preview">

                            <h4>' . format_string($quiz->name) . '</h4>

                            <p>
                                Total Questions: ' . $questioncount . '
                            </p>

                            <p>
                                Time Limit: ' . $timelimit . '
                            </p>
                    ';

                    // Fetch quiz questions
                    $slots = $DB->get_records('quiz_slots', [
                        'quizid' => $quiz->id
                    ]);

                    if ($slots) {

                        $previewcontent .= '
                            <div class="quiz-questions">
                                <strong>Quiz Questions:</strong>
                        ';

                        foreach ($slots as $slot) {

                            // Question reference
                            $reference = $DB->get_record('question_references', [
                                'itemid' => $slot->id
                            ]);

                            if (!$reference) {
                                continue;
                            }

                            // Latest question version
                            $versions = $DB->get_records_sql("
                                SELECT qv.questionid
                                FROM {question_versions} qv
                                WHERE qv.questionbankentryid = ?
                                ORDER BY qv.version DESC
                            ", [
                                $reference->questionbankentryid
                            ], 0, 1);

                            $version = reset($versions);

                            if (!$version) {
                                continue;
                            }

                            // Question
                            $question = $DB->get_record('question', [
                                'id' => $version->questionid
                            ]);

                            if (!$question) {
                                continue;
                            }

                            $previewcontent .= '
                                <div class="quiz-question" style="margin-bottom:20px;">

                                    <h5>
                                        ' . strip_tags($question->questiontext) . '
                                    </h5>
                            ';

                            // Answers
                            $answers = $DB->get_records('question_answers', [
                                'question' => $question->id
                            ]);

                            if ($answers) {

                                $previewcontent .= '<ul>';

                                foreach ($answers as $answer) {

                                    $previewcontent .= '
                                        <li>
                                            ' . strip_tags($answer->answer) . '
                                        </li>
                                    ';
                                }

                                $previewcontent .= '</ul>';
                            }

                            $previewcontent .= '</div>';
                        }

                        $previewcontent .= '</div>';
                    }

                    // Attempt button
                    $previewcontent .= '
                            <a href="' . $quizurl . '" class="btn btn-primary">
                                Attempt Quiz
                            </a>

                        </div>
                    ';
                }
            }

          

            /*
             * ASSIGNMENT MODULE
             */
            if ($cm->modname === 'assign') {

                $previewcontent = '
                    <div class="assignment-preview">
                        Assignment Activity
                    </div>
                ';
            }

            /*
             * FILE RESOURCE (video, audio, PDF, image, or download)
             */
            if ($cm->modname === 'resource') {
                $previewcontent = theme_iiidem2_get_resource_preview_html($cm);
            }

            $activities[] = [
                'id' => $cm->id,
                'title' => $cm->name,
                'type' => $activitytype,
                'preview' => !empty($previewcontent),
                'duration' => $duration,
                'previewcontent' => $previewcontent,
            ];

            $totalactivities++;
        }
    }

    $sectionsdata[] = [
        'id' => $section->id,
        'name' => $sectionname,
        'activitycount' => count($activities),
        'activities' => $activities
    ];
}


//-------------------------------------------------------------------------------

$templatecontext = [
    'sitename' => format_string($SITE->shortname, true, ['context' => context_course::instance(SITEID), "escape" => false]),
    'output' => $OUTPUT,
    'sidepreblocks' => $blockshtml,
    'hasblocks' => $hasblocks,
    'bodyattributes' => $bodyattributes,
    'courseindexopen' => $courseindexopen,
    'blockdraweropen' => $blockdraweropen,
    'courseindex' => $courseindex,
    'primarymoremenu' => $primarymenu['moremenu'],
    'secondarymoremenu' => $secondarynavigation ?: false,
    'mobileprimarynav' => $primarymenu['mobileprimarynav'],
    'usermenu' => $primarymenu['user'],
    'langmenu' => $primarymenu['lang'],
    'forceblockdraweropen' => $forceblockdraweropen,
    'regionmainsettingsmenu' => $regionmainsettingsmenu,
    'hasregionmainsettingsmenu' => !empty($regionmainsettingsmenu),
    'overflow' => $overflow,
    'headercontent' => $headercontent,
    'addblockbutton' => $addblockbutton,
       // Course data
    'coursename' => format_string($COURSE->fullname),
    'courseshortname' => format_string($COURSE->shortname),
    'coursesummary' => format_text($COURSE->summary, $COURSE->summaryformat),
    'courseimage' => $courseimage,
    'instructordata' => $instructordata,
    'faqs' => $faqs,
    'sections' => $sectionsdata,
    'totalsections' => count($sectionsdata),
    'totalactivities' => $totalactivities,
    'hasenrollmodal' => true,
    // Moodle header() requires the main_content token in layout output (hidden; curriculum uses theme partial).
    'maincontentplaceholder' => $OUTPUT->main_content(),

];

$templatecontext = theme_iiidem2_merge_footer_context($templatecontext);

$PAGE->requires->js_call_amd('theme_iiidem2/enroll', 'init');

/*$templatecontext = [
    'output' => $OUTPUT,
    'bodyattributes' => $bodyattributes,
];
$templatecontext['iscoursepage'] = true;*/

echo $OUTPUT->render_from_template(
    'theme_iiidem2/course_drawers',
    $templatecontext
);
