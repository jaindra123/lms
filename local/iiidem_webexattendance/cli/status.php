<?php
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');

$enabled = (int) get_config('local_iiidem_webexattendance', 'enabled');
$autosync = (int) get_config('local_iiidem_webexattendance', 'autosync');
$connected = \local_iiidem_webexattendance\oauth::is_connected() ? 1 : 0;
$configured = \local_iiidem_webexattendance\oauth::is_configured() ? 1 : 0;

echo "enabled={$enabled} autosync={$autosync} oauth_configured={$configured} oauth_connected={$connected}\n";

$map = $DB->get_records('local_iiidem_webexatt', null, 'id DESC', '*', 0, 20);
echo "mappings=" . count($map) . "\n";
foreach ($map as $r) {
    echo "map id={$r->id} cmid={$r->cmid} attendancecmid={$r->attendancecmid} sessionid={$r->sessionid}"
        . " meetingid={$r->meetingid} status={$r->syncstatus} name={$r->sessionname}\n";
}

$sql = "SELECT cm.id, cm.course, m.name AS modname
          FROM {course_modules} cm
          JOIN {modules} m ON m.id = cm.module
         WHERE cm.course = 4 AND m.name IN ('webexactivity','url','attendance')
           AND cm.deletioninprogress = 0
      ORDER BY m.name, cm.id";
foreach ($DB->get_records_sql($sql) as $cm) {
    echo "course4 cm={$cm->id} mod={$cm->modname}\n";
}
