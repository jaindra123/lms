<?php
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');

$courseid = 4;
$sql = "SELECT cm.id, cm.instance, m.name AS modname, cm.visible
          FROM {course_modules} cm
          JOIN {modules} m ON m.id = cm.module
         WHERE cm.course = :courseid AND cm.deletioninprogress = 0
      ORDER BY cm.id";
$cms = $DB->get_records_sql($sql, ['courseid' => $courseid]);
echo "All CMs in course {$courseid}: " . count($cms) . "\n";
foreach ($cms as $cm) {
    $name = '';
    try {
        $table = $cm->modname;
        if ($DB->get_manager()->table_exists($table)) {
            $name = (string) $DB->get_field($table, 'name', ['id' => $cm->instance]);
        }
    } catch (Throwable $e) {
        $name = '(name n/a)';
    }
    echo "cm={$cm->id} mod={$cm->modname} instance={$cm->instance} name={$name}\n";
}

if ($DB->get_manager()->table_exists('webexactivity')) {
    $wx = $DB->get_records('webexactivity', ['course' => $courseid]);
    echo "webexactivity rows=" . count($wx) . "\n";
    foreach ($wx as $w) {
        echo "webex id={$w->id} name={$w->name} start={$w->starttime} duration={$w->duration}"
            . " meetingkey=" . ($w->meetingkey ?? '') . " link=" . ($w->meetinglink ?? '') . "\n";
    }
}

$urls = $DB->get_records_sql(
    "SELECT u.id, u.name, u.externalurl, cm.id AS cmid
       FROM {url} u
       JOIN {course_modules} cm ON cm.instance = u.id
       JOIN {modules} m ON m.id = cm.module AND m.name = 'url'
      WHERE u.course = ?",
    [$courseid]
);
echo "url activities=" . count($urls) . "\n";
foreach ($urls as $u) {
    echo "url cmid={$u->cmid} name={$u->name} url={$u->externalurl}\n";
}
