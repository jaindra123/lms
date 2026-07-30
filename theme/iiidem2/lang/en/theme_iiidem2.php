<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Language file.
 *
 * @package   theme_iiidem2
 * @copyright 2016 Frédéric Massart
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'IIIDEM 2';
$string['choosereadme'] = 'Custom Moodle theme based on Boost.';

$string['headerlogo'] = 'Header logo (fallback)';
$string['headerlogo_desc'] = 'Optional fallback only when no logo is set under Site administration → Appearance → Logos. The navbar uses the site Compact logo first, then the main Logo, then this setting.';
$string['footerlogo'] = 'Footer Logo';
$string['footerlogo_desc'] = 'Upload the footer logo (light/white version works best on the dark footer).';
$string['nobio'] = 'No bio available yet.';


$string['advancedsettings'] = 'Advanced settings';
$string['backgroundimage'] = 'Background image';
$string['backgroundimage_desc'] = 'The image to display as a background of the site. The background image you upload here will override the background image in your theme preset files.';
$string['brandcolor'] = 'Brand colour';
$string['brandcolor_desc'] = 'The accent colour.';
$string['bootswatch'] = 'Bootswatch';
$string['bootswatch_desc'] = 'A bootswatch is a set of Bootstrap variables and css to style Bootstrap';
$string['choosereadme'] = 'iiidem2 is a modern highly-customisable theme. This theme is intended to be used directly, or as a parent theme when creating new themes utilising Bootstrap 4.';
$string['configtitle'] = 'iiidem2';
$string['generalsettings'] = 'General settings';
$string['customquizcmids'] = 'Custom quiz layout (optional cmid list)';
$string['customquizcmids_desc'] = 'Leave empty to use the full-screen custom MCQ layout on every quiz attempt page in a course. Or list specific cmids (comma-separated) to limit which quizzes use it.';
$string['liveclasscmids'] = 'Live class page layout (optional cmid list)';
$string['liveclasscmids_desc'] = 'Leave empty to auto-detect Page activities whose name contains Webex, live class, online class, or similar. Or list specific cmids (comma-separated) to limit which pages use the live classroom layout.';
$string['liveclasseyebrow'] = 'The synchronous heart';
$string['liveclasstitle'] = 'The Live Virtual Classroom';
$string['liveclassrec'] = 'REC';
$string['liveclasspresenter'] = 'Faculty · sharing slides';
$string['liveclassjoin'] = 'Join Live Class';
$string['liveclassnojoin'] = 'Add a join link to this page content (e.g. a button linking to your Webex meeting).';
$string['liveclassmodaltitle'] = 'Live class session';
$string['liveclassmodalblocked'] = 'Webex could not be embedded here. Continue in a centered window to join the session.';
$string['liveclassmodalopenexternal'] = 'Open Webex window';
$string['liveclassfeat_hd'] = 'HD video & screen share';
$string['liveclassfeat_hd_desc'] = 'Faculty present slides and case studies in real time.';
$string['liveclassfeat_breakout'] = 'Breakout rooms';
$string['liveclassfeat_breakout_desc'] = 'Small-group facilitated discussion and role-plays.';
$string['liveclassfeat_polls'] = 'Polls & hand-raise';
$string['liveclassfeat_polls_desc'] = 'Live interaction that captures active participation.';
$string['liveclassfeat_chat'] = 'In-session chat & Q&A';
$string['liveclassfeat_chat_desc'] = 'Questions surface without interrupting the talk.';
$string['liveclassfeat_recording'] = 'Automatic recording';
$string['liveclassfeat_recording_desc'] = 'Sessions saved to the library for async catch-up.';
$string['livequiztitle'] = 'Live questions';
$string['livequizdesc'] = 'Your teacher will release MCQs during class. Keep this page open while you join Webex in another tab.';
$string['livequizwaiting'] = 'Waiting for your teacher to release questions…';
$string['livequizthanks'] = 'Thank you — your answers were submitted.';
$string['livequizsubmit'] = 'Submit answers';
$string['livequizteachermeta'] = 'Use Live class questions on your dashboard to manage this session.';
$string['dashboardteacherlivemcq'] = 'Live class MCQ';
$string['dashboardteacherlivemcqmeta'] = 'Create and release questions during class';
$string['dashboardteacherlivemcqbtn'] = 'Manage';
$string['quizmcq_customize_hint'] = 'Quiz attempt UI is controlled by theme layouts and CSS: layout/quizattempt.php, templates/layout/quizattempt.mustache, style/quiz-mcq.css, and classes/output/mod_quiz/renderer.php.';
$string['coursequizzes_eyebrow'] = 'Assessment';
$string['curriculumpreview'] = 'Preview';
$string['curriculumassignsubmit'] = 'Submit assignment';
$string['curriculumassigncontinuesubmit'] = 'Continue submission';
$string['curriculumassignviewsubmission'] = 'View submission';
$string['curriculumassignopen'] = 'Open assignment';
$string['curriculumassignstatus'] = 'Status: {$a}';
$string['curriculumassignfiletypes'] = 'Accepted files: {$a}';
$string['curriculumassignattempts'] = 'Attempts: {$a->used} of {$a->max}';
$string['curriculumassignattemptsunlimited'] = 'Attempts: {$a} used (unlimited allowed)';
$string['curriculumassignawaitingreopen'] = 'Your submission is locked until the teacher grades it or reopens another attempt.';
$string['curriculumassigncanretry'] = 'You can submit again for another attempt.';
$string['assigngradingfiltertip'] = 'Tip: Status is set to “Submitted” by default so you only see students who uploaded work. Choose Status → All to see everyone.';
$string['coursequizzes_title'] = 'Course quizzes';
$string['coursequizzes_lead'] = 'This course includes {$a} quiz(zes). Question lists are loaded from the course automatically.';
$string['coursequizzes_lead_embedded'] = 'This course includes {$a} quiz(zes). Answer questions below (same screen as the quiz attempt page).';
$string['coursequiz_start_in_frame'] = 'Click “Attempt quiz” in the box above to start. After that, questions will appear here on the course page.';
$string['coursequiz_questions'] = 'Sample questions';
$string['coursequiz_instance'] = 'Quiz ID';
$string['coursequiz_no_attempt'] = 'Start or continue the quiz to answer questions here with the full interactive form.';
$string['coursequiz_open_full'] = 'Open quiz in full page';
$string['coursequiz_numquestions'] = 'Questions';
$string['coursequiz_answer_on_attempt'] = 'Select an answer and submit on the quiz page (opens when you click the button above).';
$string['coursequiz_no_questions'] = 'No questions found in this quiz yet.';
$string['loginbackgroundimage'] = 'Login page background image';
$string['loginbackgroundimage_desc'] = 'Optional image for the left brand panel on the login page.';
$string['loginbrandpanel'] = 'About IIIDEM';
$string['logintagline'] = 'Learn. Certify. Lead with confidence.';
$string['loginwelcome'] = 'Welcome back';
$string['loginsubtitle'] = 'Sign in to access your courses and certifications';
$string['frontpagectaguest'] = 'Sign in to access your courses, live classes, and certificates.';
$string['frontpagectaloggedin'] = 'Continue to your courses, live classes, and certificates.';
$string['loginfeature1'] = 'Structured certification programs for election professionals';
$string['loginfeature2'] = 'Live sessions and self-paced learning in one portal';
$string['loginfeature3'] = 'Track progress, assessments, and credentials';
$string['loginsignupprompt'] = 'New to IIIDEM?';
$string['nobootswatch'] = 'None';
$string['pluginname'] = 'iiidem2';
$string['presetfiles'] = 'Additional theme preset files';
$string['presetfiles_desc'] = 'Preset files can be used to dramatically alter the appearance of a theme.';
$string['preset'] = 'Theme preset';
$string['preset_desc'] = 'Pick a preset to broadly change the look of the theme.';
$string['privacy:metadata'] = 'The iiidem2 theme does not store any personal data about any user.';
$string['rawscss'] = 'Raw SCSS';
$string['rawscss_desc'] = 'Use this field to provide SCSS or CSS code which will be injected at the end of the style sheet.';
$string['rawscsspre'] = 'Raw initial SCSS';
$string['rawscsspre_desc'] = 'In this field you can provide initialising SCSS code, it will be injected before everything else. Most of the time you will use this setting to define variables.';
$string['region-side-pre'] = 'Right';
$string['showfooter'] = 'Show footer';
$string['unaddableblocks'] = 'Unneeded blocks';
$string['unaddableblocks_desc'] = 'The blocks specified are not needed when using this theme and will not be listed in the \'Add a block\' menu.';
$string['privacy:metadata:preference:draweropenblock'] = 'The user\'s preference for hiding or showing the drawer with blocks.';
$string['privacy:metadata:preference:draweropenindex'] = 'The user\'s preference for hiding or showing the drawer with course index.';
$string['privacy:metadata:preference:draweropennav'] = 'The user\'s preference for hiding or showing the drawer menu navigation.';
$string['privacy:drawerindexclosed'] = 'The current preference for the index drawer is closed.';
$string['privacy:drawerindexopen'] = 'The current preference for the index drawer is open.';
$string['privacy:drawerblockclosed'] = 'The current preference for the block drawer is closed.';
$string['privacy:drawerblockopen'] = 'The current preference for the block drawer is open.';

$string['dashboard'] = 'Dashboard';
$string['dashboardwelcomeback'] = 'Welcome back, {$a}';
$string['dashboardstudentlabel'] = 'Student';
$string['dashboardsearchplaceholder'] = 'Search...';
$string['dashboardstatcourseprogress'] = 'Course Progress';
$string['dashboardstataveragegrade'] = 'Average Grade';
$string['dashboardstatnextsession'] = 'Next Session';
$string['dashboardupcomingsessions'] = 'Upcoming Sessions';
$string['dashboardassignmentsgrades'] = 'Assignments & Grades';
$string['dashboardjoinnow'] = 'Join Now';
$string['dashboardnavlivesessions'] = 'Live Sessions';
$string['dashboardnavcurriculum'] = 'Curriculum';
$string['dashboardnavassignments'] = 'Assignments';
$string['dashboardnavdiscussions'] = 'Discussions';
$string['dashboardnavrecordings'] = 'Recordings';
$string['dashboardnavgrades'] = 'Grades';
$string['dashboardnavcertificate'] = 'Certificate';
$string['dashboardnavsupport'] = 'Support';
$string['adminnavpayment'] = 'Payment';
$string['dashboardsupporttitle'] = 'Help & Support';
$string['dashboardsupportlead'] = 'Browse FAQs, raise a ticket, or contact the IIIDEM support team.';
$string['dashboardstatuslive'] = 'Live';
$string['dashboardstatustoday'] = 'Today';
$string['dashboardstatusrsvp'] = 'RSVP';
$string['dashboardstatussoon'] = 'Soon';
$string['dashboardstatuspending'] = 'Pending';
$string['dashboardstatusgraded'] = 'Graded';
$string['dashboardstatusnotopen'] = 'Not open';
$string['dashboarddueon'] = 'Due {$a}';
$string['dashboardsessiontoday'] = 'Today';
$string['dashboardsessionsoon'] = 'Soon';
$string['dashboardwelcome'] = 'Welcome, {$a}';
$string['dashboardloaderrortitle'] = 'Dashboard temporarily unavailable';
$string['dashboardloaderrormessage'] = 'We could not load your dashboard right now. Please try again. If the problem continues, contact the site administrator.';
$string['dashboardretry'] = 'Try again';
$string['dashboardroleadmin'] = 'You are viewing the administrator dashboard.';
$string['dashboardroleteacher'] = 'You are viewing the teacher dashboard.';
$string['dashboardrolestudent'] = 'You are viewing the student dashboard.';
$string['dashboardannouncements'] = 'Announcements';
$string['dashboardannouncements_none'] = 'There are no notifications right now.';
$string['dashboardmycourses'] = 'My courses';
$string['dashboardcontinue'] = 'Continue learning';
$string['dashboardnocourses'] = 'You are not enrolled in any courses yet.';
$string['dashboardlearningprogress'] = 'Learning progress';
$string['dashboardviewbadges'] = 'View my badges';
$string['dashboardupcoming'] = 'Upcoming activities';
$string['dashboardnoupcoming'] = 'No assignments or quizzes due in the next 30 days.';
$string['dashboardliveclasses'] = 'Live classes / meetings';
$string['dashboardliveclassestoday'] = 'Today';
$string['dashboardliveclassesupcoming'] = 'Upcoming';
$string['dashboardteacherupcominglive'] = 'Upcoming live classes';
$string['dashboardjoin'] = 'Join';
$string['dashboardnolive'] = 'No upcoming live sessions in the next two weeks.';
$string['dashboardnotifications'] = 'Notifications';
$string['dashboardrecentactivity'] = 'Recent activity';
$string['dashboardnorecent'] = 'No recent course or assignment activity yet.';
$string['dashboardprogresslabel'] = '{$a}% completed';
$string['dashboardnoprogress'] = 'Progress tracking not enabled';
$string['dashboardweekendprogress'] = 'Weekend progress';
$string['dashboardweekendprogresslabel'] = '{$a->completed} of {$a->total} weekends completed ({$a->percent}%)';
$string['dashboardweekendcomplete'] = 'Completed';
$string['dashboardweekendpending'] = 'Not completed';
$string['dashboardweekendprogresshelp'] = 'Each weekend is complete when you view all lectures in that section (use Preview on the course page).';
$string['dashboardstatcourses'] = 'Enrolled courses';
$string['dashboardstatavgprogress'] = 'Average progress';
$string['dashboardstatcompleted'] = 'Courses completed';
$string['dashboardstatinprogress'] = 'In progress';
$string['dashboardstatcertificates'] = 'Certificates earned';
$string['dashboardstatbadges'] = 'Badges earned';
$string['dashboardstatcoursescompleted'] = 'Courses completed';
$string['dashboardstatquizperformance'] = 'Quiz performance';
$string['dashboardstatquizattempts'] = 'Quiz attempts';
$string['dashboardstatmonthlyactivity'] = 'Monthly activity';
$string['dashboardtypelive'] = 'Live session';
$string['dashboardcalendartitle'] = 'Calendar';
$string['dashboardcalendarnone'] = 'No calendar events in your courses yet.';
$string['dashboardquicklinks'] = 'Dashboard sections';
$string['dashboardtaboverview'] = 'Overview';
$string['dashboardtablearning'] = 'My learning';
$string['dashboardtabcalendar'] = 'Calendar';
$string['dashboardtabcommunication'] = 'Messages & news';
$string['dashboardtabachievements'] = 'Achievements';
$string['dashboardlmslinks'] = 'Moodle course tools';
$string['dashboardnonotifications'] = 'No new notifications. Grades and system alerts will appear here.';
$string['dashboardnotificationgeneric'] = 'New notification';
$string['dashboardquickmessages'] = 'Messages';
$string['dashboardquickprofile'] = 'Profile';
$string['dashboardachievements'] = 'Certificates & achievements';
$string['dashboardlearningstats'] = 'Learning statistics';
$string['dashboardviewcertificates'] = 'View certificates';
$string['dashboardtypeassign'] = 'Assignment';
$string['dashboardtypequiz'] = 'Quiz';
$string['dashboardgradeitem'] = 'Grade published: {$a}';
$string['dashboardrecentcourse'] = 'Opened: {$a}';
$string['dashboardrecentopened'] = 'Last visited course';
$string['dashboardrecentassign'] = 'Submitted: {$a}';
$string['viewall'] = 'View all';
$string['dashboardteachercourses'] = 'My teaching courses';
$string['dashboardteachernocourses'] = 'You are not teaching any courses yet.';
$string['dashboardteacherstudents'] = '{$a} students';
$string['dashboardteacherstudentslink'] = 'Students';
$string['dashboardteacherquickactions'] = 'Quick actions';
$string['dashboardteacheractioncreate'] = 'Create course';
$string['dashboardteacheractiongrade'] = 'Grading overview';
$string['dashboardteacheractionmessages'] = 'Messages';
$string['dashboardteacherpending'] = 'Assignment and quiz management';
$string['dashboardteachergradepending'] = 'Needs grading';
$string['dashboardteachergrade'] = 'Grade';
$string['dashboardteachernopending'] = 'No submissions waiting for grading.';
$string['dashboardteacherperformance'] = 'Student management';
$string['dashboardteachercompletion'] = '{$a}% course completion';
$string['dashboardteachergradebook'] = 'Gradebook';
$string['dashboardteacherattendance'] = 'Attendance';
$string['dashboardteacherattendancebydate'] = 'Attendance by date';
$string['dashboardteacherattendancereport'] = 'Full report';
$string['dashboardteacherattendancemanage'] = 'Manage sessions';
$string['dashboardteacherattendancesetup'] = 'Add an Attendance activity to your course (Edit mode → Add activity → Attendance), then create one session per weekend date.';
$string['dashboardteacherattendancenosessions'] = 'No attendance sessions yet. Create a session for each weekend (Weekend-1, Weekend-2, etc.).';
$string['dashboardteacherattendanceaddsessions'] = 'Add weekend sessions';
$string['dashboardteacherattendancedate'] = 'Date';
$string['dashboardteacherattendancesessioncol'] = 'Session / weekend';
$string['dashboardteacherattendancepresent'] = 'Present';
$string['dashboardteacherattendanceabsent'] = 'Absent';
$string['dashboardteacherattendancenotmarked'] = 'Not marked';
$string['dashboardteacherattendancestatus'] = 'Status';
$string['dashboardteacherattendancetake'] = 'Take / edit';
$string['dashboardteacherattendancetaken'] = 'Taken';
$string['dashboardteacherattendancenottaken'] = 'Not taken';
$string['dashboardteacherattendancesession'] = 'Session on {$a}';
$string['dashboardteacherattendancestudents'] = 'Student attendance summary';
$string['dashboardteacherattendancestudent'] = 'Student';
$string['dashboardteacherattendancestudentcol'] = 'Weekends attended';
$string['dashboardteacherattendanceviewstudent'] = 'Date-wise detail';
$string['dashboardteachercertificates'] = 'Certificates issued';
$string['dashboardteachernavcertificates'] = 'Certificates';
$string['dashboardteachercerttotal'] = '{$a} issued';
$string['dashboardteachercertreport'] = 'View all issued';
$string['dashboardteachercertmanage'] = 'Manage certificate';
$string['dashboardteachercertsetup'] = 'Add a Custom certificate activity to your course (Edit mode → Add activity → Custom certificate). Students receive certificates when they meet the activity criteria; you review and download them here.';
$string['dashboardteachercertpluginmissing'] = 'The Custom certificate module is not enabled. Ask your site administrator to install mod_customcert.';
$string['dashboardteachercertissuedcount'] = '{$a} issued';
$string['dashboardteachercertrecent'] = 'Recently issued certificates';
$string['dashboardteachercertstudent'] = 'Student';
$string['dashboardteachercertcourse'] = 'Course';
$string['dashboardteachercertissued'] = 'Issued on';
$string['dashboardteachercertdownload'] = 'Download PDF';
$string['dashboardteachercertnoissues'] = 'No certificates have been issued yet. They appear here when students complete the certificate requirements.';
$string['dashboardteacherstatcertificates'] = 'Certificates issued';
$string['dashboardteacherstudentattendance'] = '{$a->present} / {$a->total} weekends ({$a->percent}%)';
$string['dashboardteacheranalytics'] = 'Analytics dashboard';
$string['dashboardteacherstatcourses'] = 'Teaching courses';
$string['dashboardteacherstatstudents'] = 'Total students';
$string['dashboardteacherstatavgcompletion'] = 'Avg. completion rate';
$string['dashboardteacherstatpending'] = 'Pending grading';
$string['dashboardteacherschedule'] = 'Schedule / calendar';
$string['dashboardteachernoschedule'] = 'No upcoming deadlines or live classes in the next 30 days.';
$string['dashboardteacherlive'] = 'Live class';
$string['dashboardteachercommunication'] = 'Communication';
$string['dashboardteachermsgdesc'] = 'Send messages to students';
$string['dashboardteacherannounce'] = 'Announcements: {$a}';
$string['dashboardteacherlabel'] = 'Professors';
$string['dashboardteacherteachingconsole'] = 'Teaching Console';
$string['dashboardteacheractivelearners'] = 'Active Learners';
$string['dashboardteacherpendingreviews'] = 'Pending Reviews';
$string['dashboardteacheravgattendance'] = 'Avg Attendance';
$string['dashboardteacherlivecontrol'] = 'Live Class Control';
$string['dashboardteachergradingqueue'] = 'Grading Queue & Feedback';
$string['dashboardteacherlaunch'] = 'Launch';
$string['dashboardteachernavsessions'] = 'My Sessions';
$string['dashboardteachernavroster'] = 'Student roster';
$string['dashboardteacherroster'] = 'Enrolled students';
$string['dashboardteachernostudents'] = 'No students enrolled yet. Enrol learners in your course to see them here.';
$string['dashboardteacherstudentdetailtitle'] = 'Student: {$a}';
$string['dashboardteacherbacktoroster'] = 'Back to student roster';
$string['dashboardteacherstudentprofile'] = 'Profile';
$string['dashboardteacherstudentenrolled'] = 'Enrolled on';
$string['dashboardteacherstudentprogress'] = 'Course progress';
$string['dashboardteacherstudentweekends'] = 'Weekend progress';
$string['dashboardteacherstudentmessage'] = 'Message student';
$string['dashboardteacherstudentfullprofile'] = 'Full Moodle profile';
$string['dashboardteacherstudentattendancepending'] = 'Session not taken';
$string['dashboardteacherstudentattendanceawaiting'] = 'Awaiting live attendance mark';
$string['dashboardteacherstudentattendancecurriculum'] = 'Completed (online curriculum)';
$string['dashboardteacherstudentattendancelivemarked'] = 'Live attendance marked: {$a->present} / {$a->total} sessions present';
$string['dashboardteacherstudentattendancehelp'] = 'Weekend completion (above) tracks online activities. Live attendance is marked separately in the Attendance activity by the professors.';
$string['dashboardteacherstudentattendanceupcoming'] = '{$a} upcoming sessions scheduled (not shown until their date).';
$string['dashboardteacherstudentattendancenorecords'] = 'No attendance sessions to show yet.';
$string['dashboardteachernavgrading'] = 'Grading';
$string['dashboardteachernavcontent'] = 'Content Library';
$string['dashboardteachernavcapstone'] = 'Capstone';
$string['dashboardteacherstartsinn'] = 'starts in {$a} min';
$string['dashboardteacherlivestarting'] = 'starting now';
$string['dashboardteacherbtnstart'] = 'Start';
$string['dashboardteacherbtnsetup'] = 'Setup';
$string['dashboardteacherbtnenable'] = 'Enable';
$string['dashboardteacherbtnactive'] = 'Active';
$string['dashboardteacherbtnopen'] = 'Open';
$string['dashboardteacherbtnreview'] = 'Review';
$string['dashboardteacherbtnmoderate'] = 'Moderate';
$string['dashboardteacherbtncomment'] = 'Comment';
$string['dashboardteacherbreakoutrooms'] = 'Breakout rooms';
$string['dashboardteacherbreakoutmeta'] = 'auto-assign groups';
$string['dashboardteacherlivepoll'] = 'Live poll & hand-raise';
$string['dashboardteacherlivepollmeta'] = 'ready';
$string['dashboardteacherautorecord'] = 'Auto-record session';
$string['dashboardteacherautorecordmeta'] = 'on';
$string['dashboardteacheritemsgrade'] = '{$a} to grade';
$string['dashboardteacherdiscussionmod'] = 'Discussion moderation';
$string['dashboardteacherdiscussionmeta'] = '{$a} discussions';
$string['dashboardteachernolive'] = 'No upcoming live sessions in the next two weeks.';
$string['dashboardteacherassessmentsummary'] = 'Assessment summary';
$string['dashboardteacherassessmentattemptslabel'] = 'Quiz attempts';
$string['dashboardteacherassessmentaverage'] = 'Average score';
$string['dashboardteacherassessmenthighest'] = 'Highest score';
$string['dashboardteacherassessmentpending'] = 'Pending quizzes';
$string['dashboardteacherassessmentquizcol'] = 'Quiz';
$string['dashboardteacherassessmentscorecol'] = 'Score';
$string['dashboardteacherassessmentattemptscol'] = 'Attempts';
$string['dashboardteacherassessmentreport'] = 'View report';
$string['dashboardteacherassessmentquizlabel'] = 'Quiz {$a}';
$string['dashboardteacherassessmentattemptscount'] = '{$a} attempts';
$string['dashboardteacherassessmentsubmissions'] = 'Student quiz submissions';
$string['dashboardteacherassessmentstudentcol'] = 'Student';
$string['dashboardteacherassessmentstatuscol'] = 'Status';
$string['dashboardteacherassessmentdatecol'] = 'Submitted on';
$string['dashboardteacherassessmentstatussubmitted'] = 'Submitted';
$string['dashboardteacherassessmentstatuspending'] = 'Not submitted';
$string['dashboardteacherassessmentreview'] = 'Review';
$string['frontpageannouncementstitle'] = 'Latest announcements';
$string['frontpageannouncementstab'] = 'Announcements';
$string['frontpageannouncementsopen'] = 'Open announcements';
$string['frontpageannouncementsolder'] = 'Older topics ...';
$string['dashboardmycourses_desc'] = 'View and access your enrolled courses.';
$string['dashboardprofile'] = 'My profile';
$string['dashboardprofile_desc'] = 'Update your profile and account details.';
$string['dashboardhome'] = 'Site home';
$string['dashboardhome_desc'] = 'Return to the public site homepage.';
$string['dashboardmanagecourses'] = 'Manage courses';
$string['dashboardmanagecourses_desc'] = 'Create and manage courses on the site.';
$string['dashboardreports'] = 'Reports';
$string['dashboardreports_desc'] = 'View site and course reports.';
$string['dashboardusers'] = 'Users';
$string['dashboardusers_desc'] = 'Browse and manage user accounts.';
$string['dashboardcourses'] = 'Courses';
$string['dashboardcourses_desc'] = 'Course management and categories.';
$string['dashboardsiteadmin'] = 'Site administration';
$string['dashboardsiteadmin_desc'] = 'Open site administration settings.';
$string['entercourse'] = 'Enter course';
$string['coursedetailintro'] = 'Sign in to enrol and access all lessons, quizzes, and certificates.';
$string['coursedetaillogin'] = 'Login to start';
$string['coursecontentheading'] = 'Course Curriculum';
$string['coursecontentlectures'] = '{$a} lectures';
$string['curriculumintro'] = 'Explore the structured journey of this program. Click on any weekend to view the lectures and activities.';
$string['curriculumtotalduration'] = 'Total Duration';
$string['curriculumtotallectures'] = 'Total Lectures';
$string['curriculumlecturescount'] = '{$a} Lectures';
$string['courseinstructorsheading'] = 'Meet your Professors';
$string['coursefaqheading'] = 'Frequently asked questions';
$string['coursefaqempty'] = 'No questions have been added for this course yet.';
$string['mycoursespageintro'] = 'Browse and continue your enrolled courses.';

$string['aboutus'] = 'About us';
$string['aboutus_lead'] = 'Learn about IIIDEM, our mission, campus, and training programmes.';
$string['contactus'] = 'Contact us';
$string['contactus_lead'] = 'Get in touch with IIIDEM for enquiries about courses, admissions, and support.';
$string['contactus_getintouch'] = 'Get in touch';
$string['contactus_sendmessage'] = 'Send us a message';
$string['contactussubmit'] = 'Send message';
$string['contactusformsent'] = 'Thank you. Your message has been sent.';
$string['contactusformerror'] = 'Your message could not be sent. Please try again or email us directly.';
$string['contactusemailsubject'] = '[{$a->site}] Contact form: {$a->subject}';
$string['contactusemailbody'] = 'Contact form submission from {$a->name} ({$a->email}).

Subject: {$a->subject}

{$a->message}';
$string['contactususeremailsubject'] = '[{$a->site}] We received your message';
$string['contactususeremailbody'] = 'Dear {$a->name},

Thank you for contacting {$a->site}. We have received your message and will get back to you soon.

Subject: {$a->subject}

Your message:
{$a->message}';
$string['marketingtemplaterequired'] = 'A marketing page template must be set before rendering this layout.';

$string['aboutideasettings'] = 'About International IDEA';
$string['aboutideatitle'] = 'Section heading';
$string['aboutideatitle_desc'] = 'Title shown in the About IDEA block on the homepage.';
$string['aboutideabody'] = 'Description';
$string['aboutideabody_desc'] = 'Intro text for the About International IDEA section (supports basic formatting).';

$string['governancesettings'] = 'Program Governance';
$string['governancetitle'] = 'Section heading';
$string['governancetitle_desc'] = 'Title shown above the advisor cards on the homepage.';
$string['governanceadvisorheading'] = 'Advisor {$a}';
$string['governanceadvisorheading_desc'] = 'Leave the name empty to hide this card.';
$string['advisorname'] = 'Name';
$string['advisorrole1'] = 'Role / title (line 1)';
$string['advisorrole2'] = 'Role / title (line 2)';
$string['advisorrole2_desc'] = 'Optional second line shown under the name.';
$string['advisorimage'] = 'Photo';
$string['advisorimage_desc'] = 'Upload a portrait image (PNG or JPG).';

$string['testimonialssettings'] = 'Course testimonials';
$string['testimonialstitle'] = 'Section heading';
$string['testimonialstitle_desc'] = 'Heading above learner testimonial cards on course pages.';
$string['testimonialstitle_default'] = 'Why learners choose this course';
$string['testimonialheading'] = 'Testimonial {$a}';
$string['testimonialheading_desc'] = 'Leave name and quote empty to hide this card.';
$string['testimonialname'] = 'Learner name';
$string['testimonialsubtitle'] = 'Subtitle';
$string['testimonialsubtitle_desc'] = 'e.g. Learner since 2024 or student';
$string['testimonialquote'] = 'Quote';
$string['testimonialstars'] = 'Star rating';
$string['testimonialstars_desc'] = 'Displayed as filled stars on the testimonial card.';
$string['testimonialstarslabel'] = 'stars';
$string['testimonialimage'] = 'Photo';
$string['testimonialimage_desc'] = 'Optional learner photo. If empty, initials are shown.';

$string['studentreviewstitle'] = 'Student reviews';
$string['studentreviewscount'] = '{$a} reviews';
$string['studentreviewssince'] = 'Learner since {$a}';
$string['studentreviewswriteheading'] = 'Write your review';
$string['studentreviewseditheading'] = 'Update your review';
$string['studentreviewsratinglabel'] = 'Your rating';
$string['studentreviewstextlabel'] = 'Your review';
$string['studentreviewssubmitbtn'] = 'Submit review';
$string['studentreviewsupdatebtn'] = 'Update review';
$string['studentreviewsloginprompt'] = 'Log in to share your experience with this course.';
$string['studentreviewsenrolprompt'] = 'Enrol in this course to leave a review.';
$string['studentreviewsemptyenrolled'] = 'No student reviews yet. Be the first to share your experience.';

$string['coursefeepaymentlabel'] = 'Course fee';
$string['programmeaudienceeyebrow'] = 'Target audience';
$string['programmeaudiencetitle'] = 'Who is this Programme For';
$string['programmeaudienceintro'] = 'The course is designed for practitioners and advanced learners from a range of backgrounds. No technical background in AI is required — the programme will benefit the following, each of whom has a reason to build this knowledge:';
$string['programmeaudienceitem1title'] = 'Election Administrators & EMB Officials';
$string['programmeaudienceitem1text'] = 'Who need to govern AI during the election process.';
$string['programmeaudienceitem2title'] = 'Policymakers & Regulators';
$string['programmeaudienceitem2text'] = 'Who evaluate AI risks and frame rules.';
$string['programmeaudienceitem3title'] = 'Civil Society Groups';
$string['programmeaudienceitem3text'] = 'Who monitor AI and engage in policy debates.';
$string['programmeaudienceitem4title'] = 'Journalists';
$string['programmeaudienceitem4text'] = 'Who need practical skills to identify and report on AI-generated disinformation.';
$string['programmeaudienceitem5title'] = 'Academics & Legal Researchers';
$string['programmeaudienceitem5text'] = 'Working at the intersection of AI, electoral law, and democratic theory.';
$string['programmeaudienceitem6title'] = 'Political Campaign Managers';
$string['programmeaudienceitem6text'] = 'Who need to understand their responsibilities in AI-assisted campaigning.';
$string['programmeaudienceitem7title'] = 'International Development Professionals';
$string['programmeaudienceitem7text'] = 'Supporting democratic governance and integrating AI risk into their work.';
$string['programmeaudienceitem8title'] = 'Graduate Students';
$string['programmeaudienceitem8text'] = 'In law, political science, public policy, or technology studies.';
$string['programmetenetseyebrow'] = 'Foundations';
$string['programmetenetstitle'] = 'Programme\'s Six Core Tenets';
$string['programmetenetsitem1title'] = 'Election Integrity and Public Trust';
$string['programmetenetsitem1text'] = 'The programme is designed for practitioners and researchers seeking to protect the information environment, voter agency, and institutional legitimacy from AI-driven threats. The course will help them engage with these challenges from a governance, policy and institutional perspective, without requiring a technical background.';
$string['programmetenetsitem2title'] = 'AI Governance and Regulation';
$string['programmetenetsitem2text'] = 'This course seeks to develop an understanding of how institutions can actually apply or operationalise AI tools for transparency, risk assessment, auditability, oversight, and accountability.';
$string['programmetenetsitem3title'] = 'AI for Electoral Innovation and Service Delivery';
$string['programmetenetsitem3text'] = 'This course seeks to explore the responsible use of AI to improve electoral processes, including voter information and education, accessibility, inclusion, operational efficiency and service delivery.';
$string['programmetenetsitem4title'] = 'Real-World Application and Institutional Practice';
$string['programmetenetsitem4text'] = 'This course seeks to develop implementable solutions through case-based learning, simulations, and a capstone project anchored in each participant\'s own professional context.';
$string['programmetenetsitem5title'] = 'Global South Perspective and Comparative Experience';
$string['programmetenetsitem5text'] = 'This course seeks to apply international frameworks to contexts in South Asia, Africa, and Latin America where AI risks are acute, but governance capacity is nascent. The course will emphasise learning from comparative experience across jurisdictions.';
$string['programmetenetsitem6title'] = 'Interdisciplinary Lens';
$string['programmetenetsitem6text'] = 'Finally, this course seeks to integrate law, political science, technology studies, communications, and ethics. Since tackling AI issues is increasingly relying on inter-agency cooperation and coordination, the course will focus on cross-sectoral coordination and decision-making.';
$string['coursefeeinclusive'] = 'Inclusive of all taxes';
$string['coursestatduration'] = 'Duration';
$string['coursestatmode'] = 'Mode';
$string['coursestatlectures'] = 'Lectures';
$string['coursestatcertificate'] = 'Certificate';
$string['coursestatdurationweeks'] = '{$a} Weeks';
$string['coursestatmodelive'] = 'Live Online';
$string['coursewhatsincluded'] = 'What\'s Included';
$string['courseincludeditem1'] = 'Live interactive sessions with experts';
$string['courseincludeditem2'] = 'Certificate of Completion';
$string['courseincludeditem3'] = 'Access to recordings & live classes';
$string['courseincludeditem4'] = 'Lifetime access to course materials';
$string['coursesecurepayment'] = 'Secure Payment';
$string['coursepaymentencrypted'] = 'Your payment is secure and encrypted';
$string['paynow'] = 'Pay Now';
$string['paywithpnb'] = 'Pay with PNB';
$string['paywithicici'] = 'Pay with ICICI';
$string['paywithrazorpay'] = 'Pay with Razorpay';
$string['coursefeepaymentnote'] = 'Pay securely with Razorpay to complete your course enrolment.';
$string['coursefeepaymentpending'] = 'Online payment is being configured. Please contact the administrator.';
$string['coursepaymentsuccesstitle'] = 'Payment successful';
$string['coursepaymentsuccessbody'] = 'Your course fee payment was successful. You are now enrolled and can access the course content.';
$string['curriculumpaymentrequiredtitle'] = 'Payment required';
$string['curriculumpaymentrequiredalertheading'] = 'Course fee not paid';
$string['curriculumpaymentrequiredalertbody'] = 'Please complete the course fee payment ({$a}) to unlock lecture previews and full course access.';
$string['curriculumpaymentrequiredhint'] = 'Use the Pay Now button in the course fee panel on the right to complete your enrolment securely.';
$string['curriculumpaymentrequiredunderstood'] = 'OK, take me to payment';
$string['curriculumpaymentrequiredviewfee'] = 'View course fee';
$string['curriculumenrolrequiredtitle'] = 'Enrolment required';
$string['curriculumenrolrequiredalertheading'] = 'You are not enrolled yet';
$string['curriculumenrolrequiredalertbody'] = 'Please enrol in this course first to unlock lecture previews and full course access.';
$string['curriculumenrolrequiredhint'] = 'If you do not see an enrolment option on this page, contact the course administrator for access.';
$string['curriculumenrolrequiredunderstood'] = 'OK';

$string['loginsignup'] = 'Sign up';
$string['registerpagetitle'] = 'Create your account';
$string['registerpagesubtitle'] = 'Register to access IIIDEM courses, live classes, and certificates.';
$string['registerfirstname'] = 'First name';
$string['registermiddlename'] = 'Middle name';
$string['registerlastname'] = 'Last name';
$string['registercontact'] = 'Contact number';
$string['registercontact_help'] = 'Select your country code, then enter your mobile number. Example for India: 9876543210 (saved as +919876543210).';
$string['registerphoneinvalid'] = 'Please enter a valid contact number with country code.';
$string['registerphoneplaceholder'] = 'Mobile number';
$string['registercreateaccount'] = 'Create account';
$string['registerhaveaccount'] = 'Already have an account?';
$string['registersuccess'] = 'Your account has been created. Welcome!';
$string['registersuccesstitle'] = 'Registration successful';
$string['registersuccessbody'] = 'Your account has been created successfully. You can now explore the course and complete enrolment when you are ready.';
$string['registeremailusersubject'] = '{$a->sitename}: Account created';
$string['registeremailuserbody'] = 'Hi {$a->firstname},

Your account on {$a->sitename} has been created successfully.

Username: {$a->username}
Email: {$a->email}
Password: {$a->password}

You can sign in here:
{$a->loginurl}

If you need to reset your password later, use this secure link:
{$a->resetlink}

This link is valid for about {$a->resetminutes} minutes.

{$a->admin}
';
$string['registeremailuserhtml'] = '<p>Hi {$a->firstname},</p>
<p>Your account on <strong>{$a->sitename}</strong> has been created successfully.</p>
<ul>
<li><strong>Username:</strong> {$a->username}</li>
<li><strong>Email:</strong> {$a->email}</li>
<li><strong>Password:</strong> {$a->password}</li>
</ul>
<p>You can <a href="{$a->loginurl}">sign in here</a>.</p>
<p>If you need to reset your password later, use this secure link:</p>
<p><a href="{$a->resetlink}">{$a->resetlink}</a></p>
<p>This link is valid for about {$a->resetminutes} minutes.</p>
<p>{$a->admin}</p>';
$string['registeremailadminsubject'] = '{$a->sitename}: New user registration';
$string['registeremailadminbody'] = 'A new user has registered on {$a->sitename}.

Name: {$a->fullname}
Username: {$a->username}
Email: {$a->email}
Contact: {$a->phone}
Country: {$a->country}
City: {$a->city}
Role: {$a->occupation}

Profile: {$a->profileurl}

{$a->admin}
';
$string['registeremailadminhtml'] = '<p>A new user has registered on <strong>{$a->sitename}</strong>.</p>
<ul>
<li><strong>Name:</strong> {$a->fullname}</li>
<li><strong>Username:</strong> {$a->username}</li>
<li><strong>Email:</strong> {$a->email}</li>
<li><strong>Contact:</strong> {$a->phone}</li>
<li><strong>Country:</strong> {$a->country}</li>
<li><strong>City:</strong> {$a->city}</li>
<li><strong>Role:</strong> {$a->occupation}</li>
</ul>
<p><a href="{$a->profileurl}">View user profile</a></p>
<p>{$a->admin}</p>';
$string['registersmsbody'] = 'Hi {$a->firstname}, your {$a->sitename} account is ready. Username: {$a->username}. Set password: {$a->resetlink}';
$string['registerwhatsappbody'] = 'Hi {$a->firstname}, welcome to {$a->sitename}. Username: {$a->username}. Set your password here: {$a->resetlink}';
$string['registrationmessagingsettings'] = 'Registration SMS / WhatsApp';
$string['registrationmessagingheading'] = 'SMS and WhatsApp after registration';
$string['registrationmessagingheading_desc'] = 'Optional messaging. Without Twilio/MSG91, choose Local test (log only), enable SMS and WhatsApp, then register a user and check the log file. Email still goes to Mailpit. Real SMS/WhatsApp need a paid provider account.';
$string['enableregistrationsms'] = 'Send SMS on registration';
$string['enableregistrationsms_desc'] = 'Sends a welcome SMS with username and password-reset link to the user contact number. Provider charges apply per SMS.';
$string['enableregistrationwhatsapp'] = 'Send WhatsApp on registration';
$string['enableregistrationwhatsapp_desc'] = 'Sends a WhatsApp message to the user contact number. Requires WhatsApp Business setup; Meta/provider charges apply.';
$string['messagingprovider'] = 'Messaging provider';
$string['messagingprovider_desc'] = 'Use Local test (log only) until you have a paid Twilio or MSG91 account. Log mode writes SMS/WhatsApp text to local_dev_logs/registration_messages.log in the project folder (and also under Moodle dataroot).';
$string['messagingproviderlog'] = 'Local test (log only — no account needed)';
$string['registrationmessagingtestok'] = 'SMS/WhatsApp were NOT sent to {$a->phone}. Local test mode saved the message text in your project folder: local_dev_logs/registration_messages.log (full path: {$a->logfile}). Real delivery needs Twilio or MSG91.';
$string['messagingprovidertwilio'] = 'Twilio';
$string['messagingprovidermsg91'] = 'MSG91';
$string['registrationsmsbody'] = 'SMS message body';
$string['registrationsmsbody_desc'] = 'Leave blank for default. Placeholders: {$a->firstname}, {$a->username}, {$a->sitename}, {$a->resetlink}, {$a->loginurl}. Keep short (SMS length limits).';
$string['registrationwhatsappbody'] = 'WhatsApp message body';
$string['registrationwhatsappbody_desc'] = 'Leave blank for default. Same placeholders as SMS. For production WhatsApp, prefer approved templates (Twilio ContentSid / MSG91 template name).';
$string['twilioheading'] = 'Twilio credentials';
$string['twilioheading_desc'] = 'From your Twilio console: Account SID, Auth Token, SMS from-number, and WhatsApp-enabled from-number.';
$string['twilioaccountsid'] = 'Twilio Account SID';
$string['twilioauthtoken'] = 'Twilio Auth Token';
$string['twiliosmsfrom'] = 'Twilio SMS From number';
$string['twiliosmsfrom_desc'] = 'E.164 sender, e.g. +12025550123';
$string['twiliowhatsappfrom'] = 'Twilio WhatsApp From';
$string['twiliowhatsappfrom_desc'] = 'e.g. whatsapp:+14155238886 (sandbox) or your WhatsApp Business number';
$string['twiliowhatsappcontentsid'] = 'Twilio WhatsApp ContentSid (optional)';
$string['twiliowhatsappcontentsid_desc'] = 'Approved Content Template SID for business-initiated WhatsApp. If set, Body text is not used.';
$string['twiliowhatsappcontentvars'] = 'Twilio ContentVariables JSON (optional)';
$string['twiliowhatsappcontentvars_desc'] = 'Example: {"1":"Firstname","2":"username","3":"https://…/reset"} — must match your template variables.';
$string['msg91heading'] = 'MSG91 credentials';
$string['msg91heading_desc'] = 'From MSG91 dashboard: Auth key, SMS sender ID (DLT), and WhatsApp integrated number.';
$string['msg91authkey'] = 'MSG91 Auth key';
$string['msg91smssender'] = 'MSG91 SMS sender ID';
$string['msg91smssender_desc'] = 'Approved 6-character sender ID (India DLT).';
$string['msg91smsroute'] = 'MSG91 SMS route';
$string['msg91smsroute_desc'] = 'Usually 4 for transactional.';
$string['msg91smstemplateid'] = 'MSG91 / DLT SMS template ID (optional)';
$string['msg91smstemplateid_desc'] = 'Required in India for transactional SMS in many cases.';
$string['msg91whatsappfrom'] = 'MSG91 WhatsApp integrated number';
$string['msg91whatsappfrom_desc'] = 'Digits only or E.164 — your MSG91 WhatsApp Business number.';
$string['msg91whatsapptemplate'] = 'MSG91 WhatsApp template name';
$string['msg91whatsapptemplate_desc'] = 'Approved template name. Body variables map to firstname, username, resetlink. Leave blank to try plain text (session only).';
$string['msg91whatsappnamespace'] = 'MSG91 WhatsApp template namespace (optional)';
$string['msg91whatsappnamespace_desc'] = 'Meta template namespace if your MSG91 setup requires it.';
$string['registeroccupation'] = 'Role';
$string['registrationcourseids'] = 'Registration course IDs';
$string['registrationcourseids_desc'] = 'Comma-separated course IDs that receive newly registered users (for example: 4,5). Students and NON-EMB professionals are enrolled as suspended until fee payment succeeds; EMB professionals and instructors are enrolled as active participants. Each paid course must have an enabled Enrolment on payment method, and exempt users require an enabled Manual enrolment method.';
$string['registeroccupationworking'] = 'Working professional / NON-EMB';
$string['registeroccupationworkingemb'] = 'Working professional / EMB';
$string['registeroccupationstudent'] = 'Student';
$string['registeroccupationinstructor'] = 'Professor / Instructor';
$string['registeroccupationrequired'] = 'Please select your role.';
$string['registerworkingprofile'] = 'Working professional / NON-EMB details';
$string['registerworkingembprofile'] = 'Working professional / EMB details';
$string['registerstudentprofile'] = 'Student details';
$string['registerinstructorprofile'] = 'Professor / Instructor details';
$string['registerpasswordheader'] = 'Password';
$string['registerpasswordshouldbe'] = 'Password requirements';
$string['registeremb'] = 'I am from an EMB';
$string['registerembrequired'] = 'Please confirm that you are from an EMB.';
$string['registerpolicymaker'] = 'Policymaker';
$string['registerjournalist'] = 'Journalist';
$string['registerelectoralpractitioner'] = 'Electoral practitioner';
$string['registerresearcher'] = 'Researcher / Academician';
$string['registerworkingcategoryrequired'] = 'Please select one option (Policymaker, Journalist, or Researcher).';
$string['registerorganization'] = 'Organization';
$string['registerorganisation'] = 'Organisation';
$string['registerjobprofile'] = 'Job profile';
$string['registerdesignation'] = 'Designation';
$string['registerjobpostingcountry'] = 'Job posting country';
$string['registerembcountry'] = 'Country';
$string['registeruniversity'] = 'University';
$string['registerposition'] = 'Higher level of education';
$string['registerspecialization'] = 'Specialization';
$string['registercourse'] = 'Current position';
$string['registerpresentcountry'] = 'Present country';

// Live class / Webex create-update email notifications.
$string['liveclassnotify_na'] = 'Not provided';
$string['liveclassnotify_tbat'] = 'To be announced';
$string['liveclassnotify_createdsubject'] = 'New live class: {$a->sessionname} ({$a->coursename})';
$string['liveclassnotify_updatedsubject'] = 'Live class updated: {$a->sessionname} ({$a->coursename})';
$string['liveclassnotify_createdbody'] = 'Dear {$a->firstname},

A new live class has been scheduled in {$a->coursename} on {$a->sitename}.

Session: {$a->sessionname}
Date / time: {$a->sessiontime}
Join link: {$a->joinurl}
Meeting number: {$a->meetingnumber}
Password: {$a->password}

Open the activity: {$a->activityurl}
Course page: {$a->courseurl}

Regards,
{$a->sitename}';
$string['liveclassnotify_updatedbody'] = 'Dear {$a->firstname},

Live class details have been updated in {$a->coursename} on {$a->sitename}.

Session: {$a->sessionname}
Date / time: {$a->sessiontime}
Join link: {$a->joinurl}
Meeting number: {$a->meetingnumber}
Password: {$a->password}

Open the activity: {$a->activityurl}
Course page: {$a->courseurl}

Regards,
{$a->sitename}';
$string['liveclassnotify_remindersubject'] = 'Reminder: live class in 1 hour — {$a->sessionname} ({$a->coursename})';
$string['liveclassnotify_reminderbody'] = 'Dear {$a->firstname},

This is a reminder that your live class starts in about 1 hour.

Session: {$a->sessionname}
Course: {$a->coursename}
Date / time: {$a->sessiontime}
Join link: {$a->joinurl}
Meeting number: {$a->meetingnumber}
Password: {$a->password}

Open the activity: {$a->activityurl}
Course page: {$a->courseurl}

Regards,
{$a->sitename}';
$string['tasksendliveclassreminders'] = 'Send live class reminder emails (1 hour before)';
$string['assignnotify_opennow'] = 'Already open';
$string['assignnotify_createdsubject'] = 'New assignment: {$a->assignmentname} (due {$a->duedate})';
$string['assignnotify_updatedsubject'] = 'Assignment updated: {$a->assignmentname} (due {$a->duedate})';
$string['assignnotify_remindersubject'] = 'Reminder: {$a->assignmentname} is due soon ({$a->duedate})';
$string['assignnotify_createdbody'] = 'Dear {$a->firstname},

A new assignment has been posted in {$a->coursename} on {$a->sitename}.

Assignment: {$a->assignmentname}
Available from: {$a->allowfrom}
Due date: {$a->duedate}

Please complete and submit the assignment before the due date.

Open the assignment: {$a->activityurl}
Course page: {$a->courseurl}

Regards,
{$a->sitename}';
$string['assignnotify_updatedbody'] = 'Dear {$a->firstname},

An assignment has been updated in {$a->coursename} on {$a->sitename}.

Assignment: {$a->assignmentname}
Available from: {$a->allowfrom}
Due date: {$a->duedate}

Please complete and submit the assignment before the due date.

Open the assignment: {$a->activityurl}
Course page: {$a->courseurl}

Regards,
{$a->sitename}';
$string['assignnotify_reminderbody'] = 'Dear {$a->firstname},

This is a reminder that your assignment is due within 24 hours.

Assignment: {$a->assignmentname}
Course: {$a->coursename}
Due date: {$a->duedate}

Please complete and submit it before the deadline.

Open the assignment: {$a->activityurl}
Course page: {$a->courseurl}

Regards,
{$a->sitename}';
$string['tasksendassignreminders'] = 'Send assignment due-date reminder emails (24 hours before)';
