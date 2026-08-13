<?php
defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_iiidem_webexattendance';
$plugin->version   = 2026081301;
$plugin->requires  = 2024100100;
$plugin->maturity  = MATURITY_BETA;
$plugin->release   = '1.1.0';
$plugin->dependencies = [
    'local_iiidem_classvideos' => 2026081100,
];