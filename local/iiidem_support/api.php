<?php
define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

use local_iiidem_support\manager;

require_login();
require_sesskey();

\theme_iiidem2\rate_limit::require_json('support_searchfaq', 30, 60);

$action = required_param('action', PARAM_ALPHANUMEXT);

header('Content-Type: application/json');

if ($action !== 'searchfaq') {
    throw new moodle_exception('invalidparameter', 'error');
}

$query = trim(clean_param(required_param('q', PARAM_TEXT), PARAM_TEXT));
if ($query === '' || core_text::strlen($query) > 200) {
    throw new moodle_exception('invalidparameter', 'error');
}
$results = manager::search_faqs($USER->id, $query);

echo json_encode([
    'results' => $results,
    'ticketurl' => (new moodle_url('/local/iiidem_support/ticket_new.php'))->out(false),
]);
