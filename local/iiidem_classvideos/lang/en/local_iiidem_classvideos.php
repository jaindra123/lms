<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Class videos (Webex recordings)';
$string['privacy:metadata'] = 'Stores class video metadata, uploaded files, and student access requests.';

$string['managevideos'] = 'Class videos';
$string['managevideos_desc'] = 'Share Webex / class recordings (prefer a link) and approve student access requests.';
$string['studentvideos'] = 'Class recordings';
$string['studentvideos_desc'] = 'Request access to class recordings or watch videos shared with you.';

$string['videotitle'] = 'Video title';
$string['videodesc'] = 'Description (optional)';
$string['sessiondate'] = 'Class / session date';
$string['accesstype'] = 'Who can watch';
$string['accesstype_help'] = 'Public to class: all enrolled students can watch immediately. Request required: students ask for access; you approve or reject.';
$string['accesstype_public'] = 'Public to enrolled students';
$string['accesstype_request'] = 'Request required (approve each student)';
$string['uploadtip'] = 'For recordings around 50-200+ MB, paste the Webex (or SharePoint / OneDrive) link below. Do not upload the full file into Moodle - it is slow and can time out. Use Upload video file only for short clips.';
$string['externalurl'] = 'Webex / recording URL (recommended)';
$string['externalurl_help'] = 'Best for large Webex recordings: paste the HTTPS link from Webex (or your org SharePoint/OneDrive). Students watch via that link after access is granted. Faster and more reliable than uploading 100-200+ MB files.';
$string['videofile'] = 'Upload video file (short clips only)';
$string['videofile_help'] = 'Optional. Use only for small files (e.g. under about 50 MB). Full-length Webex sessions should use the URL field instead - large uploads are slow and may fail.';
$string['savevideo'] = 'Save video';
$string['updatevideo'] = 'Update video';
$string['addvideo'] = 'Add class video';
$string['editvideo'] = 'Edit class video';
$string['novideos'] = 'No class videos for this course yet.';
$string['novideosstudent'] = 'No recordings are available yet.';
$string['yourvideos'] = 'Videos in this course';
$string['sharedcoursevideos'] = 'Videos belong to the course. Teachers who can manage this course see the same list (including admin uploads).';
$string['selectcourse'] = 'Course';
$string['needfileorurl'] = 'Provide a recording URL and/or upload a video file.';
$string['invalurl'] = 'Enter a valid http(s) URL.';
$string['invalidtitle'] = 'Enter a valid title.';
$string['invalidfile'] = 'That file is not allowed ({$a}).';
$string['requestnotneeded'] = 'This video is public — open Watch instead.';
$string['notenrolled'] = 'You must be enrolled in the course to request this video.';

$string['status_pending'] = 'Pending';
$string['status_approved'] = 'Approved';
$string['status_rejected'] = 'Rejected';

$string['requestaccess'] = 'Request access';
$string['requestsent'] = 'Access request sent. A teacher will review it.';
$string['requestnote'] = 'Note to teacher (optional)';
$string['approve'] = 'Approve';
$string['reject'] = 'Reject';
$string['watch'] = 'Watch';
$string['pendingrequests'] = 'Pending access requests';
$string['nopending'] = 'No pending requests.';
$string['videosaved'] = 'Video saved.';
$string['videoupdated'] = 'Video updated.';
$string['videodeleted'] = 'Video deleted.';
$string['confirmdeletevideo'] = 'Delete video "{$a}"? This removes the file/link and any student access requests. This cannot be undone.';
$string['requestresolved'] = 'Request updated.';
$string['hide'] = 'Hide';
$string['show'] = 'Show';
$string['visibilityupdated'] = 'Visibility updated.';
$string['openexternal'] = 'Open recording link';
$string['noaccess'] = 'You do not have access to this recording yet.';
$string['course'] = 'Course';
$string['student'] = 'Student';
$string['requestedon'] = 'Requested';
$string['actions'] = 'Actions';

$string['iiidem_classvideos:manage'] = 'Manage class videos and approve access';
$string['iiidem_classvideos:request'] = 'Request access to class videos';
$string['iiidem_classvideos:view'] = 'View class video listings';
