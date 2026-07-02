<?php
namespace paygw_icici;

defined('MOODLE_INTERNAL') || die();

class gateway extends \core_payment\gateway {

    public static function get_supported_currencies(): array {
        return ['INR'];
    }

    public static function add_configuration_to_gateway_form(\core_payment\form\account_gateway $form): void {
        $mform = $form->get_mform();

        $mform->addElement('text', 'brandname', get_string('brandname', 'paygw_icici'));
        $mform->setType('brandname', PARAM_TEXT);
        $mform->addHelpButton('brandname', 'brandname', 'paygw_icici');

        $mform->addElement('text', 'merchantid', get_string('merchantid', 'paygw_icici'));
        $mform->setType('merchantid', PARAM_TEXT);
        $mform->addHelpButton('merchantid', 'merchantid', 'paygw_icici');

        $mform->addElement('text', 'submerchantid', get_string('submerchantid', 'paygw_icici'));
        $mform->setType('submerchantid', PARAM_TEXT);
        $mform->addHelpButton('submerchantid', 'submerchantid', 'paygw_icici');

        $mform->addElement('passwordunmask', 'secretkey', get_string('secretkey', 'paygw_icici'));
        $mform->setType('secretkey', PARAM_TEXT);
        $mform->addHelpButton('secretkey', 'secretkey', 'paygw_icici');

        $mform->addElement('text', 'gatewayurl', get_string('gatewayurl', 'paygw_icici'),
            ['size' => 60]);
        $mform->setType('gatewayurl', PARAM_URL);
        $mform->addHelpButton('gatewayurl', 'gatewayurl', 'paygw_icici');

        $mform->addElement('text', 'testgatewayurl', get_string('testgatewayurl', 'paygw_icici'),
            ['size' => 60]);
        $mform->setType('testgatewayurl', PARAM_URL);
        $mform->addHelpButton('testgatewayurl', 'testgatewayurl', 'paygw_icici');

        $options = [
            'live' => get_string('live', 'paygw_icici'),
            'test' => get_string('test', 'paygw_icici'),
        ];
        $mform->addElement('select', 'environment', get_string('environment', 'paygw_icici'), $options);
        $mform->addHelpButton('environment', 'environment', 'paygw_icici');
    }

    public static function validate_gateway_form(\core_payment\form\account_gateway $form,
            \stdClass $data, array $files, array &$errors): void {
        if (empty($data->merchantid)) {
            $errors['merchantid'] = get_string('required');
        }
        if (empty($data->secretkey)) {
            $errors['secretkey'] = get_string('required');
        }
    }
}
