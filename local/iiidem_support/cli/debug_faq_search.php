<?php
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/local/iiidem_support/lib.php');

$userid = (int) ($argv[1] ?? 0);
$query = $argv[2] ?? 'background knowledge';

if (!$userid) {
    $user = $DB->get_record('user', ['email' => 'chanchal@iiidem.com'], 'id', IGNORE_MISSING);
    $userid = $user ? (int) $user->id : 2;
}

echo "User ID: $userid\n";
$faqs = \local_iiidem_support\manager::get_user_faqs($userid);
echo 'FAQ count for user: ' . count($faqs) . "\n";
foreach ($faqs as $faq) {
    echo "- [{$faq->courseid}] {$faq->question}\n";
}

$results = \local_iiidem_support\manager::search_faqs($userid, $query);
echo "\nSearch '$query': " . count($results) . " results\n";
foreach ($results as $r) {
    echo "- {$r['question']}\n";
}
