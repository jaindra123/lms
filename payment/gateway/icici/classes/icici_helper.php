<?php
namespace paygw_icici;

defined('MOODLE_INTERNAL') || die();

/**
 * ICICI Eazypay / IPG redirect helpers.
 *
 * Checksum (request): SHA256(merchantid|mandatory|optional|returnurl|workingkey)
 * mandatory = order_id|amount|currency
 */
class icici_helper {

    public static function build_redirect_form(\stdClass $config, string $txnref, float $amount,
            string $currency, string $returnurl, string $description): array {
        $gatewayurl = ($config->environment ?? 'test') === 'live'
            ? ($config->gatewayurl ?? '')
            : ($config->testgatewayurl ?? $config->gatewayurl ?? '');

        $gatewayurl = self::resolve_gateway_url($gatewayurl);

        $amountstr = number_format($amount, 2, '.', '');
        $merchantid = trim($config->merchantid ?? '');
        $secret = $config->secretkey ?? '';
        $mandatory = implode('|', [$txnref, $amountstr, $currency]);
        $optional = '';
        if (!empty($config->submerchantid)) {
            $optional = 'submerchantid=' . $config->submerchantid;
        }

        $checksum = self::generate_request_checksum($merchantid, $mandatory, $optional, $returnurl, $secret);

        $fields = [
            ['name' => 'merchantid', 'value' => $merchantid],
            ['name' => 'mandatory fields', 'value' => $mandatory],
            ['name' => 'optional fields', 'value' => $optional],
            ['name' => 'returnurl', 'value' => $returnurl],
            ['name' => 'Checksum', 'value' => $checksum],
            // Explicit refs for mock.php (PHP converts spaces in POST names to underscores).
            ['name' => 'ReferenceNo', 'value' => $txnref],
            ['name' => 'ReturnURL', 'value' => $returnurl],
        ];

        if (!empty($config->brandname)) {
            $fields[] = ['name' => 'brandname', 'value' => $config->brandname];
        }
        if ($description !== '') {
            $fields[] = ['name' => 'TxnDescription', 'value' => $description];
        }

        return [
            'gatewayurl' => $gatewayurl,
            'fields' => $fields,
        ];
    }

    public static function resolve_gateway_url(string $gatewayurl): string {
        if (preg_match('#/mock\.ph$#i', $gatewayurl)) {
            $gatewayurl = preg_replace('#/mock\.ph$#i', '/mock.php', $gatewayurl);
        }
        if ($gatewayurl === '' || stripos($gatewayurl, 'gateway.example.icici.in') !== false) {
            return (new \moodle_url('/payment/gateway/icici/mock.php'))->out(false);
        }
        return $gatewayurl;
    }

    public static function generate_request_checksum(string $merchantid, string $mandatory,
            string $optional, string $returnurl, string $secret): string {
        $payload = implode('|', [$merchantid, $mandatory, $optional, $returnurl, $secret]);
        return hash('sha256', $payload);
    }

    public static function amounts_match(string $returnedamount, float $expectedamount): bool {
        return abs((float) $returnedamount - $expectedamount) < 0.005;
    }

    /**
     * Current server-side payable amount for a stored txn.
     */
    public static function expected_amount_for_txn(\stdClass $txn): float {
        $payable = \core_payment\helper::get_payable(
            $txn->component,
            $txn->paymentarea,
            (int) $txn->itemid
        );
        $surcharge = \core_payment\helper::get_gateway_surcharge('icici');
        return \core_payment\helper::get_rounded_cost(
            $payable->get_amount(),
            $payable->get_currency(),
            $surcharge
        );
    }

    /**
     * Fail closed if txn amount does not match current course fee.
     *
     * @throws \moodle_exception
     */
    public static function assert_txn_matches_payable(\stdClass $txn): void {
        $expected = self::expected_amount_for_txn($txn);
        if ($expected <= 0 || abs($expected - (float) $txn->amount) >= 0.005) {
            throw new \moodle_exception('amountmismatch', 'paygw_icici');
        }
    }

    public static function verify_return(\stdClass $config, array $params): bool {
        $received = $params['CHECKSUM'] ?? $params['Checksum'] ?? $params['checksum'] ?? '';
        if ($received === '') {
            return false;
        }

        $merchantid = $params['MERCHANTID'] ?? $params['merchantid'] ?? ($config->merchantid ?? '');
        $txnref = self::extract_order_id($params);
        $amount = $params['AMOUNT'] ?? $params['Amount'] ?? $params['amount'] ?? $params['TOTALAMOUNT'] ?? '';
        $currency = $params['CURRENCYCODE'] ?? $params['Currency'] ?? $params['currency'] ?? 'INR';
        $status = $params['STATUS'] ?? $params['ResponseCode'] ?? $params['responsecode'] ?? '';
        $bankref = $params['BANKREF'] ?? $params['UniqueRefNumber'] ?? $params['PaymentID'] ?? '';
        $secret = $config->secretkey ?? '';

        $payload = implode('|', [$merchantid, $txnref, $amount, $currency, $status, $bankref]);
        $expected = hash_hmac('sha256', $payload, $secret);

        if (hash_equals($expected, $received)) {
            return true;
        }

        // Eazypay-style SHA256 return checksum fallback.
        $eazypayload = implode('|', [$merchantid, $txnref, $amount, $status, $secret]);
        $eazyexpected = hash('sha256', $eazypayload);

        return hash_equals($eazyexpected, $received);
    }

    public static function extract_order_id(array $params): string {
        foreach (['TXNREFNO', 'txnrefno', 'ReferenceNo', 'referenceno', 'ORDERID', 'orderid'] as $key) {
            if (!empty($params[$key])) {
                return (string) $params[$key];
            }
        }
        return '';
    }

    public static function is_success_status(string $status): bool {
        $status = strtoupper(trim($status));
        return in_array($status, ['SUCCESS', 'S', 'E000', '000', '00', 'APPROVED', 'CAPTURED'], true);
    }

    public static function generate_txnref(): string {
        return 'ICICI' . time() . random_int(1000, 9999);
    }

    public static function render_result_page($output, string $type, string $heading,
            string $message, \moodle_url $continueurl, array $details = []): string {
        $icon = $type === 'success' ? 'i/valid' : ($type === 'error' ? 'i/invalid' : 'i/info');
        $alertclass = $type === 'success' ? 'alert-success' : ($type === 'error' ? 'alert-danger' : 'alert-info');

        $html = $output->header();
        $html .= \html_writer::start_tag('div', ['class' => 'container py-5']);
        $html .= \html_writer::start_tag('div', ['class' => 'row justify-content-center']);
        $html .= \html_writer::start_tag('div', ['class' => 'col-md-8 col-lg-6']);
        $html .= \html_writer::start_tag('div', ['class' => 'card shadow border-0']);
        $html .= \html_writer::start_tag('div', ['class' => 'card-body p-4 p-md-5 text-center']);

        $html .= $output->pix_icon($icon, '', 'moodle', ['class' => 'mb-3', 'style' => 'width:64px;height:64px;']);
        $html .= \html_writer::tag('h2', $heading, ['class' => 'mb-3']);
        $html .= \html_writer::div($message, 'alert ' . $alertclass . ' text-start');

        if (!empty($details)) {
            $html .= \html_writer::start_tag('dl', ['class' => 'text-start small text-muted mb-4']);
            foreach ($details as $label => $value) {
                $html .= \html_writer::tag('dt', $label);
                $html .= \html_writer::tag('dd', $value, ['class' => 'mb-2']);
            }
            $html .= \html_writer::end_tag('dl');
        }

        $html .= \html_writer::link($continueurl, \get_string('continuetocourse', 'paygw_icici'), [
            'class' => 'btn btn-primary btn-lg w-100',
        ]);

        $html .= \html_writer::end_tag('div');
        $html .= \html_writer::end_tag('div');
        $html .= \html_writer::end_tag('div');
        $html .= \html_writer::end_tag('div');
        $html .= \html_writer::end_tag('div');
        $html .= $output->footer();

        return $html;
    }
}
