<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'IIIDEM Excel/CSV (multiple choice)';
$string['pluginname_help'] = 'Import multiple choice questions from a spreadsheet with columns: Question, Option1, IsOption1, Option2, IsOption2, … and TRUE/FALSE for the correct answer. Save Excel as CSV (UTF-8) before importing.';
$string['pluginname_link'] = 'qformat/iiidemcsv';
$string['privacy:metadata'] = 'The IIIDEM CSV question format plugin does not store any personal data.';
$string['missingquestioncolumn'] = 'The file must have a "Question" column in the first row.';
$string['norows'] = 'No question rows were found in the file.';
$string['rowmissingquestion'] = 'Row {$a}: question text is empty.';
$string['rowfewoptions'] = 'Row {$a}: at least two answer options are required.';
$string['rownocorrect'] = 'Row {$a}: mark exactly one option with TRUE in the IsOption column.';
$string['rowtoomanycorrect'] = 'Row {$a}: more than one option is marked TRUE (using multiple-response mode).';
