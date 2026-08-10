<?php
// This file is part of Moodle - http://moodle.org/
//
// Download an issued IIIDEM certificate PDF.

require(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/theme/iiidem2/lib.php');

require_login();

$issueid = required_param('id', PARAM_INT);
\theme_iiidem2\rate_limit::require_allowed('certificate_download', 10, 60);
$issue = \theme_iiidem2\certificate_issuer::require_issue_for_user($issueid, (int) $USER->id);
\theme_iiidem2\certificate_pdf::send_download($issue);
