<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_paygw_razorpay_install() {
    global $CFG;

    $order = (!empty($CFG->paygw_plugins_sortorder)) ? explode(',', $CFG->paygw_plugins_sortorder) : [];
    if (!in_array('razorpay', $order, true)) {
        set_config('paygw_plugins_sortorder', join(',', array_merge($order, ['razorpay'])));
    }

    return true;
}
