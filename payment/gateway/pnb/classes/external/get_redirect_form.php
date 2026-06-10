<?php
declare(strict_types=1);

namespace paygw_pnb\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use core_external\external_single_structure;
use core_external\external_multiple_structure;
use core_payment\helper;
use paygw_pnb\pnb_helper;

class get_redirect_form extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'component' => new external_value(PARAM_COMPONENT, 'Component'),
            'paymentarea' => new external_value(PARAM_AREA, 'Payment area'),
            'itemid' => new external_value(PARAM_INT, 'Item id'),
            'description' => new external_value(PARAM_TEXT, 'Payment description'),
        ]);
    }

    public static function execute(string $component, string $paymentarea, int $itemid, string $description): array {
        global $DB, $USER;

        self::validate_parameters(self::execute_parameters(), [
            'component' => $component,
            'paymentarea' => $paymentarea,
            'itemid' => $itemid,
            'description' => $description,
        ]);

        require_login();

        $payable = helper::get_payable($component, $paymentarea, $itemid);
        $config = (object) helper::get_gateway_configuration($component, $paymentarea, $itemid, 'pnb');
        $surcharge = helper::get_gateway_surcharge('pnb');
        $amount = helper::get_rounded_cost($payable->get_amount(), $payable->get_currency(), $surcharge);
        $currency = $payable->get_currency();

        $txnref = pnb_helper::generate_txnref();
        $returnurl = (new \moodle_url('/payment/gateway/pnb/return.php'))->out(false);

        $now = time();
        $record = (object) [
            'txnref' => $txnref,
            'userid' => (int) $USER->id,
            'component' => $component,
            'paymentarea' => $paymentarea,
            'itemid' => $itemid,
            'accountid' => $payable->get_account_id(),
            'amount' => $amount,
            'currency' => $currency,
            'status' => 'pending',
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        $DB->insert_record('paygw_pnb_txn', $record);

        $form = pnb_helper::build_redirect_form($config, $txnref, $amount, $currency, $returnurl, $description);

        if (empty($form['gatewayurl'])) {
            throw new \moodle_exception('error', 'core_error', '', 'PNB gateway URL is not configured.');
        }

        return [
            'gatewayurl' => $form['gatewayurl'],
            'fields' => $form['fields'],
            'method' => 'post',
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'gatewayurl' => new external_value(PARAM_URL, 'PNB gateway URL'),
            'method' => new external_value(PARAM_ALPHA, 'HTTP method'),
            'fields' => new external_multiple_structure(
                new external_single_structure([
                    'name' => new external_value(PARAM_ALPHANUMEXT, 'Field name'),
                    'value' => new external_value(PARAM_RAW, 'Field value'),
                ])
            ),
        ]);
    }
}
