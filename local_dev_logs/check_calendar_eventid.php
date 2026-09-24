<?php
define('CLI_SCRIPT', true);
require __DIR__ . '/../config.php';

$cases = [
    'string0' => ['eventid' => '0'],
    'int0' => ['eventid' => 0],
    'missing' => [],
    'junk' => ['eventid' => 'abc'],
    'neg' => ['eventid' => -1],
    'empty' => ['eventid' => ''],
    'missingid' => ['eventid' => 999999999],
];

foreach ($cases as $name => $args) {
    $r = \theme_iiidem2\ajax_request_guard::validate_item([
        'index' => 0,
        'methodname' => 'core_calendar_get_calendar_event_by_id',
        'args' => $args,
    ]);
    $ok = is_array($r) && !empty($r['error']);
    $code = $ok ? ($r['exception']['errorcode'] ?? '?') : 'ALLOW';
    echo $name . ': ' . ($ok ? 'DENY ' : '') . $code . PHP_EOL;
}

global $DB;
$id = (int) $DB->get_field_sql('SELECT MIN(id) FROM {event} WHERE id > 0');
echo 'min_event_id=' . $id . PHP_EOL;

$admin = get_admin();
if ($admin) {
    \core\session\manager::set_user($admin);
    echo 'set_user=' . $admin->id . PHP_EOL;
}

if ($id >= 1) {
    $r = \theme_iiidem2\ajax_request_guard::validate_item([
        'index' => 0,
        'methodname' => 'core_calendar_get_calendar_event_by_id',
        'args' => ['eventid' => $id],
    ]);
    echo 'admin_valid_id: ' . ($r === null ? 'ALLOW' : ('DENY ' . ($r['exception']['errorcode'] ?? '?'))) . PHP_EOL;

    $row = $DB->get_record('event', ['id' => $id], 'id,eventtype,courseid,userid,name');
    if ($row) {
        echo 'event meta: type=' . $row->eventtype . ' course=' . $row->courseid . ' user=' . $row->userid . PHP_EOL;
    }
}

$r0 = \theme_iiidem2\ajax_request_guard::validate_item([
    'index' => 0,
    'methodname' => 'core_calendar_get_calendar_event_by_id',
    'args' => ['eventid' => '0'],
]);
echo 'admin_eventid0: ' . (is_array($r0) && !empty($r0['error']) ? 'DENY ' . $r0['exception']['errorcode'] : 'ALLOW') . PHP_EOL;
