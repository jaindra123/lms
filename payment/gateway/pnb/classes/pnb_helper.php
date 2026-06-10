<?php
namespace paygw_pnb;

defined('MOODLE_INTERNAL') || die();

class pnb_helper {

    /**
     * Build redirect form fields for PNB IPG.
     *
     * @param \stdClass $config Gateway configuration.
     * @param string $txnref Unique transaction reference.
     * @param float $amount Amount in currency units.
     * @param string $currency ISO currency code.
     * @param string $returnurl Return URL after payment.
     * @param string $description Payment description.
     * @return array{gatewayurl: string, fields: array<int, array{name: string, value: string}>}
     */
    public static function build_redirect_form(\stdClass $config, string $txnref, float $amount,
            string $currency, string $returnurl, string $description): array {
        $gatewayurl = ($config->environment ?? 'test') === 'live'
            ? ($config->gatewayurl ?? '')
            : ($config->testgatewayurl ?? $config->gatewayurl ?? '');

        $gatewayurl = self::resolve_gateway_url($gatewayurl);

        $amountstr = number_format($amount, 2, '.', '');
        $merchantid = $config->merchantid ?? '';
        $secret = $config->secretkey ?? '';

        $checksum = self::generate_checksum($merchantid, $txnref, $amountstr, $currency, $returnurl, $secret);

        $fields = [
            ['name' => 'MERCHANTID', 'value' => $merchantid],
            ['name' => 'TXNREFNO', 'value' => $txnref],
            ['name' => 'AMOUNT', 'value' => $amountstr],
            ['name' => 'CURRENCYCODE', 'value' => $currency],
            ['name' => 'RETURNURL', 'value' => $returnurl],
            ['name' => 'DESCRIPTION', 'value' => $description],
            ['name' => 'CHECKSUM', 'value' => $checksum],
        ];

        if (!empty($config->brandname)) {
            $fields[] = ['name' => 'BRANDNAME', 'value' => $config->brandname];
        }

        return [
            'gatewayurl' => $gatewayurl,
            'fields' => $fields,
        ];
    }

    /**
     * Use the local mock simulator when placeholder bank URLs are still configured.
     */
    public static function resolve_gateway_url(string $gatewayurl): string {
        if (preg_match('#/mock\.ph$#i', $gatewayurl)) {
            $gatewayurl = preg_replace('#/mock\.ph$#i', '/mock.php', $gatewayurl);
        }
        if ($gatewayurl === '' || stripos($gatewayurl, 'gateway.example.pnb.in') !== false) {
            return (new \moodle_url('/payment/gateway/pnb/mock.php'))->out(false);
        }
        return $gatewayurl;
    }

    /**
     * Render a clear payment result page (success or failure).
     *
     * @param object $output Page renderer ($PAGE->get_renderer('core') or $OUTPUT after header)
     * @param string $type success|error|info
     * @param string $heading
     * @param string $message
     * @param \moodle_url $continueurl
     * @param array $details Optional key/value rows
     * @return string
     */
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

        $html .= \html_writer::link($continueurl, \get_string('continuetocourse', 'paygw_pnb'), [
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

    /**
     * Generate payment request checksum.
     */
    public static function generate_checksum(string $merchantid, string $txnref, string $amount,
            string $currency, string $returnurl, string $secret): string {
        $payload = implode('|', [$merchantid, $txnref, $amount, $currency, $returnurl]);
        return hash_hmac('sha256', $payload, $secret);
    }

    /**
     * Verify response checksum from PNB return callback.
     *
     * @param \stdClass $config Gateway configuration.
     * @param array $params Response parameters (uppercase keys).
     * @return bool
     */
    public static function verify_return(\stdClass $config, array $params): bool {
        $received = $params['CHECKSUM'] ?? $params['checksum'] ?? '';
        if ($received === '') {
            return false;
        }

        $merchantid = $params['MERCHANTID'] ?? $params['merchantid'] ?? ($config->merchantid ?? '');
        $txnref = $params['TXNREFNO'] ?? $params['txnrefno'] ?? '';
        $amount = $params['AMOUNT'] ?? $params['amount'] ?? '';
        $currency = $params['CURRENCYCODE'] ?? $params['currencycode'] ?? 'INR';
        $status = $params['STATUS'] ?? $params['status'] ?? '';
        $bankref = $params['BANKREF'] ?? $params['bankref'] ?? '';
        $secret = $config->secretkey ?? '';

        $payload = implode('|', [$merchantid, $txnref, $amount, $currency, $status, $bankref]);
        $expected = hash_hmac('sha256', $payload, $secret);

        return hash_equals($expected, $received);
    }

    /**
     * Whether the bank reported a successful payment.
     */
    public static function is_success_status(string $status): bool {
        $status = strtoupper(trim($status));
        return in_array($status, ['SUCCESS', 'S', '000', '00', 'APPROVED'], true);
    }

    /**
     * Create a unique transaction reference.
     */
    public static function generate_txnref(): string {
        return 'PNB' . time() . random_int(1000, 9999);
    }
}
