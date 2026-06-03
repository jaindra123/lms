<?php
require('../../config.php');

global $DB;

require_login();

$id = required_param('id', PARAM_INT);

$DB->delete_records('local_coursefaq', ['id' => $id]);

redirect(new moodle_url('/local/coursefaq/index.php'), 'Deleted successfully');