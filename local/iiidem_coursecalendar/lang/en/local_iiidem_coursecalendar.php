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

// Google Calendar API (live class scheduling).
$string['googlesettingsheading'] = 'Google Calendar API';
$string['googlesettingsheading_desc'] = 'Create live-class events with enrolled students as attendees. Google sends invitation emails (Accept / Decline). Requires a Google Cloud service account and a calendar shared with that account (Make changes to events). For Google Workspace, optionally set impersonate email with domain-wide delegation.';
$string['google_service_account_json'] = 'Service account JSON';
$string['google_service_account_json_desc'] = 'Paste the full JSON key file from Google Cloud Console (APIs & Services → Credentials → Service account → Keys). Enable Google Calendar API for the project.';
$string['google_calendar_id'] = 'Google Calendar ID';
$string['google_calendar_id_desc'] = 'Calendar where live classes are created (Settings → Integrate calendar → Calendar ID). Share this calendar with the service account email and grant Make changes to events.';
$string['google_impersonate_email'] = 'Impersonate user email (optional)';
$string['google_impersonate_email_desc'] = 'Google Workspace only: a user email to impersonate via domain-wide delegation (e.g. training@yourdomain.gov.in). Leave empty if the service account owns or is editor on the calendar.';
$string['googleapinotconfigured'] = 'Google Calendar API is not configured. Ask the site administrator to add credentials under Site administration → Plugins → Local plugins → IIIDEM course calendar.';
$string['googleapinotconfiguredteacher'] = 'Google Calendar API is not configured yet. You can still save the schedule in Moodle, but Google invitations will not be sent until the administrator adds API credentials.';
$string['googleapicredentialsinvalid'] = 'Google service account JSON is invalid.';
$string['googleapitokenfailed'] = 'Could not obtain Google access token: {$a}';
$string['googleapisyncfailed'] = 'Google Calendar sync failed: {$a}';
$string['googleapisyncsuccess'] = 'Google Calendar event created/updated for {$a->when}. Google invitations sent to {$a->count} student(s).';
$string['googleapisyncsuccessmoodle'] = 'Google Calendar event created for {$a->when}. Google cannot email invites from a service account on personal Gmail — Moodle emailed {$a->count} student(s) instead.';
$string['googleapicreatefailed'] = 'Google Calendar event could not be created.';
$string['googleapierror'] = 'Google Calendar API error: {$a}';

$string['scheduleliveclass'] = 'Schedule live class';
$string['scheduleliveclassheading'] = 'Schedule live class for {$a}';
$string['scheduleliveclasshelp'] = 'Creates a Google Calendar event and invites all enrolled students by email.';
$string['liveclasstitle'] = 'Session title';
$string['liveclassdescription'] = 'Description';
$string['liveclassstart'] = 'Start date and time';
$string['liveclassduration'] = 'Duration';
$string['liveclasslocation'] = 'Meeting link';
$string['liveclasslocation_help'] = 'Webex, Zoom, Google Meet, or other join URL shown in the calendar event.';
$string['liveclassstartpast'] = 'Start time must be in the future.';
$string['invalidurllocation'] = 'Please enter a valid meeting URL.';
$string['sendgoogleinvites'] = 'Send Google Calendar invitations to enrolled students';
$string['sendgoogleinvites_help'] = 'Adds each enrolled student email as an attendee. Google sends invitation emails; students click Accept to add the event to their calendar.';
$string['liveclassattendeepreview'] = '{$a} enrolled student(s) will be invited (students with a valid email on their profile).';
$string['upcomingliveclasses'] = 'Upcoming scheduled live classes';
$string['liveclassattendees'] = '{$a} invite(s)';
$string['liveclassscheduled'] = 'Live class scheduled. {$a->count} student(s). {$a->invites}';
$string['liveclassinvitessent'] = 'Google Calendar invitations were sent.';
$string['liveclassinvitesnotsent'] = 'Saved in Moodle (Google invites were not sent).';
$string['liveclasscancelled'] = 'Live class cancelled.';
$string['liveclassnotificationsubject'] = 'Live class: {$a->title} ({$a->coursename})';
$string['liveclassnotificationbody'] = 'A live class has been scheduled for "{$a->coursename}".

Title: {$a->title}
When: {$a->when}
Meeting link: {$a->location}

Check your email for a Google Calendar invitation — click Accept to add it to your calendar.

Course: {$a->courseurl}';
$string['autowebexgoogle'] = 'Auto-create Google Calendar for Webex / Webex URL activities';
$string['autowebexgoogle_desc'] = 'When a teacher saves a Webex activity, OR a URL activity whose External URL is a Webex link, Moodle creates/updates a Google Calendar event and invites enrolled students. For URL activities, enable Set reminder in Timeline (that date/time is used as the session start). Requires Google Calendar API credentials above.';
$string['urlliveduration'] = 'Default duration (minutes) for URL Webex sessions';
$string['urlliveduration_desc'] = 'Used when the teacher adds Webex as a URL resource (URL modules have no duration field). Default 60.';
$string['webexjoindescription'] = 'Join Webex: {$a}';
