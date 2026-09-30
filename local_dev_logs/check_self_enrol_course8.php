<?php
define('CLI_SCRIPT', true);
require __DIR__ . '/../config.php';

$instances = $DB->get_records('enrol', ['courseid' => 8, 'enrol' => 'self']);
foreach ($instances as $i) {
    echo json_encode($i, JSON_PRETTY_PRINT) . PHP_EOL;
}

$ctx = context_course::instance(8);
echo "enrol/self:enrolself for guest? skip\n";
$roles = $DB->get_records_sql(
    "SELECT r.shortname, rc.capability, rc.permission
       FROM {role_capabilities} rc
       JOIN {role} r ON r.id = rc.roleid
      WHERE rc.capability = 'enrol/self:enrolself'"
);
foreach ($roles as $r) {
    echo "role={$r->shortname} perm={$r->permission}\n";
}
