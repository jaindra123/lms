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

$string['headerlogo'] = 'Header Logo';
$string['headerlogo_desc'] = 'Upload the main header logo.';
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
$string['quizmcq_customize_hint'] = 'Quiz attempt UI is controlled by theme layouts and CSS: layout/quizattempt.php, templates/layout/quizattempt.mustache, style/quiz-mcq.css, and classes/output/mod_quiz/renderer.php.';
$string['coursequizzes_eyebrow'] = 'Assessment';
$string['curriculumpreview'] = 'Preview';
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
$string['loginbackgroundimage_desc'] = 'The image to display as a background for the login page.';
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
$string['dashboardjoin'] = 'Join';
$string['dashboardnolive'] = 'No upcoming live sessions in the next two weeks.';
$string['dashboardnotifications'] = 'Notifications';
$string['dashboardrecentactivity'] = 'Recent activity';
$string['dashboardnorecent'] = 'No recent course or assignment activity yet.';
$string['dashboardprogresslabel'] = '{$a}% completed';
$string['dashboardnoprogress'] = 'Progress tracking not enabled';
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
$string['dashboardquicklinks'] = 'Quick links';
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
$string['dashboardteacherlabel'] = 'Instructor';
$string['dashboardteacherteachingconsole'] = 'Teaching Console';
$string['dashboardteacheractivelearners'] = 'Active Learners';
$string['dashboardteacherpendingreviews'] = 'Pending Reviews';
$string['dashboardteacheravgattendance'] = 'Avg Attendance';
$string['dashboardteacherlivecontrol'] = 'Live Class Control';
$string['dashboardteachergradingqueue'] = 'Grading Queue & Feedback';
$string['dashboardteacherlaunch'] = 'Launch';
$string['dashboardteachernavsessions'] = 'My Sessions';
$string['dashboardteachernavroster'] = 'Roster & Attend.';
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

$string['coursefeepaymentlabel'] = 'Course fee';
$string['paywithpnb'] = 'Pay with PNB';
$string['coursefeepaymentnote'] = 'Secure payment via Punjab National Bank Internet Payment Gateway.';
$string['coursefeepaymentpending'] = 'Online payment is being configured. Please contact the administrator.';

$string['loginsignup'] = 'Sign up';
$string['registerpagetitle'] = 'Create your account';
$string['registerpagesubtitle'] = 'Register to access IIIDEM courses, live classes, and certificates.';
$string['registerfirstname'] = 'First name';
$string['registermiddlename'] = 'Middle name';
$string['registerlastname'] = 'Last name';
$string['registercontact'] = 'Contact number';
$string['registercreateaccount'] = 'Create account';
$string['registerhaveaccount'] = 'Already have an account?';
$string['registersuccess'] = 'Your account has been created. Welcome!';
$string['registeroccupation'] = 'Occupation';
$string['registeroccupationworking'] = 'Working profile';
$string['registeroccupationstudent'] = 'University student';
$string['registeroccupationinstructor'] = 'Instructor';
$string['registeroccupationrequired'] = 'Please select one occupation option.';
$string['registerworkingprofile'] = 'Working profile details';
$string['registerstudentprofile'] = 'University student details';
$string['registerinstructorprofile'] = 'Instructor details';
$string['registeremb'] = 'EMB';
$string['registerorganization'] = 'Organization';
$string['registerjobprofile'] = 'Job profile';
$string['registerjobpostingcountry'] = 'Job posting country';
$string['registeruniversity'] = 'University';
$string['registerposition'] = 'Position';
$string['registerspecialization'] = 'Specialization';
$string['registercourse'] = 'Course';
$string['registerpresentcountry'] = 'Present country';
