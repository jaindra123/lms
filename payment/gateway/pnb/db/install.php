<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_paygw_pnb_install() {
    global $CFG;

    $order = (!empty($CFG->paygw_plugins_sortorder)) ? explode(',', $CFG->paygw_plugins_sortorder) : [];
    if (!in_array('pnb', $order, true)) {
        set_config('paygw_plugins_sortorder', join(',', array_merge($order, ['pnb'])));
    }

    return true;
}
