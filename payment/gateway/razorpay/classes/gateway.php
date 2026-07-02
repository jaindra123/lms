<?php
namespace paygw_razorpay;

defined('MOODLE_INTERNAL') || die();

class gateway extends \core_payment\gateway {

    public static function get_supported_currencies(): array {
        return ['INR'];
    }

    public static function add_configuration_to_gateway_form(\core_payment\form\account_gateway $form): void {
        $mform = $form->get_mform();

        $mform->addElement('text', 'brandname', get_string('brandname', 'paygw_razorpay'));
        $mform->setType('brandname', PARAM_TEXT);
        $mform->addHelpButton('brandname', 'brandname', 'paygw_razorpay');

        $mform->addElement('text', 'keyid', get_string('keyid', 'paygw_razorpay'));
        $mform->setType('keyid', PARAM_TEXT);
        $mform->addHelpButton('keyid', 'keyid', 'paygw_razorpay');

        $mform->addElement('passwordunmask', 'keysecret', get_string('keysecret', 'paygw_razorpay'));
        $mform->setType('keysecret', PARAM_TEXT);
        $mform->addHelpButton('keysecret', 'keysecret', 'paygw_razorpay');

        $options = [
            'live' => get_string('live', 'paygw_razorpay'),
            'test' => get_string('test', 'paygw_razorpay'),
        ];
        $mform->addElement('select', 'environment', get_string('environment', 'paygw_razorpay'), $options);
        $mform->addHelpButton('environment', 'environment', 'paygw_razorpay');

        $mform->addElement('text', 'coursefeeamount', get_string('coursefeeamount', 'paygw_razorpay'));
        $mform->setType('coursefeeamount', PARAM_FLOAT);
        $mform->addHelpButton('coursefeeamount', 'coursefeeamount', 'paygw_razorpay');
    }

    public static function validate_gateway_form(\core_payment\form\account_gateway $form,
            \stdClass $data, array $files, array &$errors): void {
        if (empty($data->keyid)) {
            $errors['keyid'] = get_string('required');
        }
        if (empty($data->keysecret)) {
            $errors['keysecret'] = get_string('required');
        }

        $keyid = trim($data->keyid ?? '');
        $environment = $data->environment ?? 'test';
        if ($keyid !== '' && str_starts_with($keyid, 'rzp_live_') && $environment !== 'live') {
            $errors['environment'] = get_string('environmentkeymismatch', 'paygw_razorpay');
        }
        if ($keyid !== '' && str_starts_with($keyid, 'rzp_test_') && $environment !== 'test') {
            $errors['environment'] = get_string('environmentkeymismatch', 'paygw_razorpay');
        }

        if (!empty($data->enabled) && course_fee_amount::parse_amount($data->coursefeeamount ?? null) === null) {
            $errors['coursefeeamount'] = get_string('invalidcoursefeeamount', 'paygw_razorpay');
        }
    }
}
