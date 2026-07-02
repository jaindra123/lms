<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'IIIDEM course calendar';
$string['manage'] = 'Course schedule calendar';
$string['manageheading'] = 'Google Calendar for {$a}';
$string['calendarurl'] = 'Google Calendar URL';
$string['calendarurl_help'] = 'Paste a Google Calendar share, embed, or iCal (.ics) link. Students will see upcoming dates on the course page and can be notified by email when you save.';
$string['calendarurlrequired'] = 'Please enter a Google Calendar URL.';
$string['invalidcalendarurl'] = 'That does not look like a valid Google Calendar URL.';
$string['saved'] = 'Course calendar saved. Enrolled students have been notified.';
$string['deleted'] = 'Course calendar removed.';
$string['deletecalendar'] = 'Remove calendar';
$string['deleteconfirm'] = 'Remove the linked Google Calendar from this course? Students will no longer see the schedule here.';
$string['scheduleheading'] = 'Course schedule';
$string['scheduleintro'] = 'Upcoming sessions and important dates for this course.';
$string['viewfullcalendar'] = 'Open full calendar';
$string['noevents'] = 'No upcoming events found in this calendar.';
$string['nocalendar'] = 'No course calendar has been linked yet.';
$string['eventdate'] = '{$a}';
$string['notificationsubject'] = 'Course schedule: {$a}';
$string['notificationbody'] = 'A schedule calendar has been linked to the course "{$a->coursename}".

Upcoming dates:
{$a->events}

View the course: {$a->courseurl}
Open the calendar: {$a->calendarurl}';
$string['notificationbodyupdate'] = 'The schedule calendar for "{$a->coursename}" has been updated.

Upcoming dates:
{$a->events}

View the course: {$a->courseurl}
Open the calendar: {$a->calendarurl}';
$string['notificationbodynewevents'] = 'New dates have been added to the schedule for "{$a->coursename}".

New or updated dates:
{$a->events}

View the course: {$a->courseurl}
Open the calendar: {$a->calendarurl}';
$string['messageprovider:schedule'] = 'Course schedule calendar notifications';
$string['taskchecknewevents'] = 'Check course calendars for new events';
