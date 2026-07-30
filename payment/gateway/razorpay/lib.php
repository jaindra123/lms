<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Serve stored Razorpay invoice PDFs.
 *
 * @package   paygw_razorpay
 * @copyright 2026 IIIDEM
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Serve plugin files.
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param context $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @param array $options
 * @return bool
 */
function paygw_razorpay_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    global $USER;

    if ($filearea !== \paygw_razorpay\invoice::FILEAREA) {
        return false;
    }

    if ($context->contextlevel !== CONTEXT_USER) {
        return false;
    }

    require_login();

    $itemid = (int) array_shift($args);
    $filename = array_pop($args);
    $filepath = $args ? '/' . implode('/', $args) . '/' : '/';

    if ((int) $context->instanceid !== (int) $USER->id && !is_siteadmin()) {
        return false;
    }

    $fs = get_file_storage();
    $file = $fs->get_file($context->id, 'paygw_razorpay', $filearea, $itemid, $filepath, $filename);
    if (!$file || $file->is_directory()) {
        return false;
    }

    send_stored_file($file, 0, 0, true, $options);
    return true;
}
