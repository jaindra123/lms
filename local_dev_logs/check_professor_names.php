<?php
define('CLI_SCRIPT', true);
require __DIR__ . '/../config.php';
require_once($CFG->libdir . '/clilib.php');

$like = $DB->sql_like('firstname', ':q', false) . ' OR ' . $DB->sql_like('lastname', ':q2', false);
foreach (['Nikhil', 'Naren', 'Charru', 'Malhotra', 'Arvind'] as $q) {
    $rows = $DB->get_records_select(
        'user',
        $like,
        ['q' => '%' . $q . '%', 'q2' => '%' . $q . '%'],
        'id ASC',
        'id,username,firstname,lastname,deleted,suspended',
        0,
        10
    );
    cli_writeln('--- search ' . $q . ' count=' . count($rows));
    foreach ($rows as $u) {
        cli_writeln('id=' . $u->id . ' ' . $u->username . ' ' . fullname($u) . ' del=' . $u->deleted);
    }
}

$c7 = $DB->get_record('course', ['id' => 7], 'id,shortname,fullname,visible');
if ($c7) {
    cli_writeln('course7 visible=' . $c7->visible . ' ' . $c7->shortname);
}
