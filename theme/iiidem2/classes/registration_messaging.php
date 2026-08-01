<?php
// This file is part of Moodle - http://moodle.org/
//
// @package   theme_iiidem2
// @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace theme_iiidem2;

defined('MOODLE_INTERNAL') || die();

/**
 * Send registration SMS and WhatsApp via configured paid providers (Twilio / MSG91).
 *
 * @package theme_iiidem2
 */
class registration_messaging {

    /**
     * Send SMS and/or WhatsApp welcome + password-reset notice to the new user.
     *
     * Failures are logged and never block registration or emails.
     *
     * @param \stdClass $user New user (needs phone1).
     * @param \stdClass $userdata Placeholder object used in lang strings (firstname, sitename, username, resetlink, …).
     * @return array{provider: string, phone: string, sms: bool, whatsapp: bool, logfile: string, sent_real: bool}
     */
    public static function notify_user(\stdClass $user, \stdClass $userdata): array {
        $result = [
            'provider' => '',
            'phone' => '',
            'sms' => false,
            'whatsapp' => false,
            'logfile' => '',
            'sent_real' => false,
        ];

        $phone = self::normalize_e164((string) ($user->phone1 ?? ''));
        if ($phone === '') {
            return $result;
        }
        $result['phone'] = $phone;

        $provider = trim((string) get_config('theme_iiidem2', 'messagingprovider'));
        if ($provider === '') {
            $provider = 'log';
        }
        $result['provider'] = $provider;

        $enablesms = (bool) get_config('theme_iiidem2', 'enableregistrationsms');
        $enablewa = (bool) get_config('theme_iiidem2', 'enableregistrationwhatsapp');

        if (!$enablesms && !$enablewa) {
            return $result;
        }

        $smsbody = self::render_message_template(
            (string) get_config('theme_iiidem2', 'registrationsmsbody'),
            'registersmsbody',
            $userdata
        );
        $wabody = self::render_message_template(
            (string) get_config('theme_iiidem2', 'registrationwhatsappbody'),
            'registerwhatsappbody',
            $userdata
        );

        try {
            if ($provider === 'log') {
                if ($enablesms) {
                    self::log_local_message('SMS', $phone, $smsbody);
                    $result['sms'] = true;
                }
                if ($enablewa) {
                    self::log_local_message('WhatsApp', $phone, $wabody);
                    $result['whatsapp'] = true;
                }
                // Prefer the project-folder copy (visible in Windows Explorer).
                $result['logfile'] = self::project_log_file_path();
                $result['sent_real'] = false;
            } else if ($provider === 'msg91') {
                if ($enablesms) {
                    self::msg91_send_sms($phone, $smsbody);
                    $result['sms'] = true;
                }
                if ($enablewa) {
                    self::msg91_send_whatsapp($phone, $wabody, $userdata);
                    $result['whatsapp'] = true;
                }
                $result['sent_real'] = true;
            } else {
                if ($enablesms) {
                    self::twilio_send_message($phone, $smsbody, false, $userdata);
                    $result['sms'] = true;
                }
                if ($enablewa) {
                    self::twilio_send_message($phone, $wabody, true, $userdata);
                    $result['whatsapp'] = true;
                }
                $result['sent_real'] = true;
            }
        } catch (\Throwable $e) {
            // Keep quiet on the page (developer mode would otherwise break the redirect).
            error_log('theme_iiidem2 registration messaging: ' . $e->getMessage());
        }

        return $result;
    }

    /**
     * Moodle dataroot log path (inside Docker volume on DDEV).
     *
     * @return string
     */
    public static function log_file_path(): string {
        global $CFG;
        return $CFG->dataroot . '/temp/theme_iiidem2/registration_messages.log';
    }

    /**
     * Project folder log path — visible on the Windows host under the site directory.
     *
     * @return string
     */
    public static function project_log_file_path(): string {
        global $CFG;
        return $CFG->dirroot . '/local_dev_logs/registration_messages.log';
    }

    /**
     * Local test mode: write the message to moodledata + project folder (no provider account needed).
     *
     * @param string $channel SMS|WhatsApp
     * @param string $phone
     * @param string $body
     * @return void
     */
    protected static function log_local_message(string $channel, string $phone, string $body): void {
        $line = sprintf(
            "[%s] %s to %s\n%s\n%s\n",
            date('c'),
            $channel,
            $phone,
            $body,
            str_repeat('-', 60)
        );

        // Do not use debugging() here — it prints on the page and blocks redirect.
        foreach ([self::log_file_path(), self::project_log_file_path()] as $logfile) {
            $dir = dirname($logfile);
            if (!is_dir($dir)) {
                // Project folder may not use Moodle's make_writable_directory permissions model.
                if (strpos($dir, '/local_dev_logs') !== false || strpos($dir, '\\local_dev_logs') !== false) {
                    @mkdir($dir, 0777, true);
                } else {
                    make_writable_directory($dir);
                }
            }
            @file_put_contents($logfile, $line, FILE_APPEND | LOCK_EX);
        }
        error_log('theme_iiidem2 registration ' . $channel . ' (test log only, not sent) → ' . $phone);
    }

    /**
     * @param string $configured
     * @param string $langkey Fallback lang string in theme_iiidem2
     * @param \stdClass $userdata
     * @return string
     */
    protected static function render_message_template(string $configured, string $langkey, \stdClass $userdata): string {
        $template = trim($configured);
        if ($template === '') {
            $template = get_string($langkey, 'theme_iiidem2');
        }
        return trim(self::interpolate($template, $userdata));
    }

    /**
     * Replace {$a->field} placeholders (Moodle-style) in a raw template.
     *
     * @param string $template
     * @param \stdClass $a
     * @return string
     */
    protected static function interpolate(string $template, \stdClass $a): string {
        return preg_replace_callback('/\{\$a->([a-z0-9_]+)\}/i', static function (array $m) use ($a) {
            $key = $m[1];
            return isset($a->{$key}) ? (string) $a->{$key} : '';
        }, $template) ?? $template;
    }

    /**
     * Keep digits and leading +, require enough length.
     *
     * @param string $phone
     * @return string E.164-ish or empty
     */
    protected static function normalize_e164(string $phone): string {
        $phone = trim($phone);
        if ($phone === '') {
            return '';
        }
        if ($phone[0] === '+') {
            $digits = '+' . preg_replace('/\D+/', '', substr($phone, 1));
        } else {
            $digits = preg_replace('/\D+/', '', $phone);
        }
        $len = strlen(ltrim($digits, '+'));
        return ($len >= 10 && $len <= 15) ? $digits : '';
    }

    /**
     * Digits only with country code (MSG91 style, no +).
     *
     * @param string $e164
     * @return string
     */
    protected static function digits_only(string $e164): string {
        return preg_replace('/\D+/', '', $e164) ?? '';
    }

    /**
     * @param string $to E.164
     * @param string $body
     * @param bool $whatsapp
     * @param \stdClass $userdata
     * @return void
     */
    protected static function twilio_send_message(string $to, string $body, bool $whatsapp, \stdClass $userdata): void {
        $sid = trim((string) get_config('theme_iiidem2', 'twilioaccountsid'));
        $token = trim((string) get_config('theme_iiidem2', 'twilioauthtoken'));
        $from = $whatsapp
            ? trim((string) get_config('theme_iiidem2', 'twiliowhatsappfrom'))
            : trim((string) get_config('theme_iiidem2', 'twiliosmsfrom'));

        if ($sid === '' || $token === '' || $from === '' || ($body === '' && !$whatsapp)) {
            debugging('theme_iiidem2: Twilio ' . ($whatsapp ? 'WhatsApp' : 'SMS') . ' not configured', DEBUG_NORMAL);
            return;
        }

        $toaddr = $whatsapp ? 'whatsapp:' . $to : $to;
        $fromaddr = $from;
        if ($whatsapp && stripos($fromaddr, 'whatsapp:') !== 0) {
            $fromaddr = 'whatsapp:' . $fromaddr;
        }

        $url = 'https://api.twilio.com/2010-04-01/Accounts/' . rawurlencode($sid) . '/Messages.json';
        $post = [
            'To' => $toaddr,
            'From' => $fromaddr,
            'Body' => $body,
        ];

        // Optional Content API template (production WhatsApp).
        if ($whatsapp) {
            $contentsid = trim((string) get_config('theme_iiidem2', 'twiliowhatsappcontentsid'));
            if ($contentsid !== '') {
                unset($post['Body']);
                $post['ContentSid'] = $contentsid;
                $vars = trim((string) get_config('theme_iiidem2', 'twiliowhatsappcontentvars'));
                if ($vars === '') {
                    // Default map: {{1}} firstname, {{2}} username, {{3}} reset link.
                    $vars = json_encode([
                        '1' => (string) ($userdata->firstname ?? ''),
                        '2' => (string) ($userdata->username ?? ''),
                        '3' => (string) ($userdata->resetlink ?? ''),
                    ], JSON_UNESCAPED_SLASHES);
                } else {
                    $vars = self::interpolate($vars, $userdata);
                }
                $post['ContentVariables'] = $vars;
            } else if ($body === '') {
                debugging('theme_iiidem2: Twilio WhatsApp body empty and no ContentSid', DEBUG_NORMAL);
                return;
            }
        }

        $curl = new \curl();
        $curl->setopt(['CURLOPT_USERPWD' => $sid . ':' . $token]);
        $response = $curl->post($url, $post);
        $info = $curl->get_info();
        $httpcode = (int) ($info['http_code'] ?? 0);
        if ($httpcode < 200 || $httpcode >= 300) {
            debugging('theme_iiidem2 Twilio error HTTP ' . $httpcode . ': ' . substr((string) $response, 0, 500), DEBUG_NORMAL);
        }
    }

    /**
     * MSG91 SMS (simple text API).
     *
     * @param string $to E.164
     * @param string $body
     * @return void
     */
    protected static function msg91_send_sms(string $to, string $body): void {
        $authkey = trim((string) get_config('theme_iiidem2', 'msg91authkey'));
        $sender = trim((string) get_config('theme_iiidem2', 'msg91smssender'));
        $route = trim((string) get_config('theme_iiidem2', 'msg91smsroute'));
        if ($route === '') {
            $route = '4';
        }
        $templateid = trim((string) get_config('theme_iiidem2', 'msg91smstemplateid'));

        if ($authkey === '' || $sender === '' || $body === '') {
            debugging('theme_iiidem2: MSG91 SMS not configured', DEBUG_NORMAL);
            return;
        }

        $payload = [
            'sender' => $sender,
            'route' => $route,
            'country' => '0',
            'sms' => [
                [
                    'message' => $body,
                    'to' => [self::digits_only($to)],
                ],
            ],
        ];
        // India DLT: include registered template id when provided.
        if ($templateid !== '') {
            $payload['template_id'] = $templateid;
        }

        $curl = new \curl();
        $curl->setHeader([
            'authkey: ' . $authkey,
            'Content-Type: application/json',
            'Accept: application/json',
        ]);
        $response = $curl->post('https://control.msg91.com/api/v2/sendsms', json_encode($payload));
        $info = $curl->get_info();
        $httpcode = (int) ($info['http_code'] ?? 0);
        if ($httpcode < 200 || $httpcode >= 300) {
            debugging('theme_iiidem2 MSG91 SMS error HTTP ' . $httpcode . ': ' . substr((string) $response, 0, 500), DEBUG_NORMAL);
        }
    }

    /**
     * MSG91 WhatsApp outbound (template-oriented).
     *
     * @param string $to E.164
     * @param string $body Fallback / Integrated number text if templates unused
     * @param \stdClass $userdata
     * @return void
     */
    protected static function msg91_send_whatsapp(string $to, string $body, \stdClass $userdata): void {
        $authkey = trim((string) get_config('theme_iiidem2', 'msg91authkey'));
        $integrated = trim((string) get_config('theme_iiidem2', 'msg91whatsappfrom'));
        $template = trim((string) get_config('theme_iiidem2', 'msg91whatsapptemplate'));
        $namespace = trim((string) get_config('theme_iiidem2', 'msg91whatsappnamespace'));

        if ($authkey === '' || $integrated === '') {
            debugging('theme_iiidem2: MSG91 WhatsApp not configured', DEBUG_NORMAL);
            return;
        }

        $payload = [
            'integrated_number' => preg_replace('/\D+/', '', $integrated),
            'content_type' => 'template',
            'payload' => [
                'messaging_product' => 'whatsapp',
                'type' => 'template',
                'template' => [
                    'name' => $template !== '' ? $template : 'registration_welcome',
                    'language' => [
                        'code' => 'en',
                        'policy' => 'deterministic',
                    ],
                    'to_and_components' => [
                        [
                            'to' => [self::digits_only($to)],
                            'components' => [
                                'body_1' => [
                                    'type' => 'text',
                                    'value' => (string) ($userdata->firstname ?? ''),
                                ],
                                'body_2' => [
                                    'type' => 'text',
                                    'value' => (string) ($userdata->username ?? ''),
                                ],
                                'body_3' => [
                                    'type' => 'text',
                                    'value' => (string) ($userdata->resetlink ?? ''),
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        if ($namespace !== '') {
            $payload['payload']['template']['namespace'] = $namespace;
        }

        // If no template configured, attempt a simple text payload (may only work in session windows).
        if ($template === '') {
            $payload['content_type'] = 'text';
            $payload['payload'] = [
                'messaging_product' => 'whatsapp',
                'type' => 'text',
                'text' => [
                    'body' => $body,
                ],
                'to' => [self::digits_only($to)],
            ];
        }

        $curl = new \curl();
        $curl->setHeader([
            'authkey: ' . $authkey,
            'Content-Type: application/json',
            'Accept: application/json',
        ]);
        $response = $curl->post(
            'https://api.msg91.com/api/v5/whatsapp/whatsapp-outbound-message/bulk/',
            json_encode($payload)
        );
        $info = $curl->get_info();
        $httpcode = (int) ($info['http_code'] ?? 0);
        if ($httpcode < 200 || $httpcode >= 300) {
            debugging('theme_iiidem2 MSG91 WhatsApp error HTTP ' . $httpcode . ': ' . substr((string) $response, 0, 500), DEBUG_NORMAL);
        }
    }
}
