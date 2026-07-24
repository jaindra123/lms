<?php
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');

require_once($CFG->dirroot . '/theme/iiidem2/classes/registration_profile.php');

$desired = [
    'iiidem_occupation',
    'iiidem_emb',
    'iiidem_policymaker',
    'iiidem_journalist',
    'iiidem_electoral_practitioner',
    'iiidem_researcher',
    'iiidem_organization',
    'iiidem_jobprofile',
    'iiidem_jobpostingcountry',
    'iiidem_university',
    'iiidem_position',
    'iiidem_specialization',
    'iiidem_instructor_university',
    'iiidem_instructor_course',
    'iiidem_presentcountry',
];

\theme_iiidem2\registration_profile::ensure_fields();

$sort = 1;
foreach ($desired as $shortname) {
    $field = $DB->get_record('user_info_field', ['shortname' => $shortname], 'id, shortname, name, sortorder');
    if (!$field) {
        echo "MISSING {$shortname}\n";
        continue;
    }
    $DB->set_field('user_info_field', 'sortorder', $sort, ['id' => $field->id]);
    echo "{$sort}\t{$shortname}\t{$field->name}\n";
    $sort++;
}

echo "Done.\n";
