<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_paygw_icici_install() {
    global $CFG;

    $order = (!empty($CFG->paygw_plugins_sortorder)) ? explode(',', $CFG->paygw_plugins_sortorder) : [];
    if (!in_array('icici', $order, true)) {
        set_config('paygw_plugins_sortorder', join(',', array_merge($order, ['icici'])));
    }

    return true;
}
