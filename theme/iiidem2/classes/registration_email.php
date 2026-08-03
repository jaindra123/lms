<?php
// This file is part of Moodle - http://moodle.org/.

namespace theme_iiidem2;

defined('MOODLE_INTERNAL') || die();

/**
 * Registration email quality checks (disposable / test domains + deliverability).
 *
 * Allows genuine consumer providers (Gmail, Yahoo, Hotmail, …) and organisation
 * domains that publish MX records. Blocks disposable / temporary / fake hosts.
 *
 * @package   theme_iiidem2
 * @copyright 2026 IIIDEM
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class registration_email {

    /**
     * Known disposable / temporary email domains (lowercase, no @).
     *
     * @return string[]
     */
    public static function disposable_domains(): array {
        return [
            'mailinator.com', 'mailinator.net', 'mailinator.org', 'mailinator2.com', 'mailinator.us',
            'guerrillamail.com', 'guerrillamail.de', 'guerrillamail.net', 'guerrillamail.org',
            'guerrillamailblock.com', 'grr.la', 'sharklasers.com', 'guerrillamail.info',
            'tempmail.com', 'temp-mail.org', 'temp-mail.io', 'temp-mail.com', 'tempmailo.com',
            'tempmail.net', 'tempail.com', 'tempinbox.com', 'tempmailaddress.com', 'tempmailer.com',
            'throwawaymail.com', 'throwaway.email', 'trashmail.com', 'trashmail.me', 'trashmail.net',
            'trash-mail.com', 'trashmail.org', 'trashymail.com', 'yopmail.com', 'yopmail.fr', 'yopmail.net',
            '10minutemail.com', '10minutemail.net', '10minmail.com', '10minemail.com', 'minutemail.com',
            'minuteinbox.com', 'emailondeck.com', 'mailnesia.com', 'maildrop.cc', 'discard.email',
            'discardmail.com', 'discardmail.de', 'fakeinbox.com', 'fakemailgenerator.com', 'fakemail.net',
            'mailcatch.com', 'getnada.com', 'nada.email', 'emailfake.com', 'generator.email',
            'mohmal.com', 'moakt.com', 'tmpmail.org', 'tmpmail.net', 'tmpeml.com', 'mailnull.com',
            'spamgourmet.com', 'spamhole.com', 'spam.la', 'mailtemp.info', 'mytemp.email', 'tempr.email',
            'tmpbox.net', 'burnermail.io', 'mailpoof.com', 'dispostable.com', 'mailforspam.com',
            'spam4.me', 'inboxbear.com', 'getairmail.com', '1secmail.com', '1secmail.org', '1secmail.net',
            'harakirimail.com', 'spamobox.com', 'maildrop.cc', 'guerrillamail.com', 'cool.fr.nf',
            'jetable.org', 'jetable.fr.nf', 'meltmail.com', 'mozmail.com', 'inboxkitten.com',
            'dropmail.me', 'emltmp.com', 'emlhub.com', 'emlpro.com', 'bccto.me', 'chacuo.net',
            'armyspy.com', 'cuvox.de', 'dayrep.com', 'einrot.com', 'fleckens.hu', 'gustr.com',
            'jourrapide.com', 'rhyta.com', 'superrito.com', 'teleworm.us', 'mailzilla.com',
            'spamfree24.org', 'spamfree24.de', 'spamfree24.eu', 'spamfree24.net', 'spamfree24.info',
            'wegwerfmail.de', 'wegwerfmail.net', 'wegwerfmail.org', 'trash2009.com', 'mt2014.com',
            'mt2015.com', 'mailin8r.com', 'safetymail.info', 'sogetthis.com', 'spamherelots.com',
            'thisisnotmyrealemail.com', 'veryrealemail.com', 'zippymail.info', '0-mail.com',
            '0clickemail.com', '0815.ru', 'filzmail.com', 'getonemail.com', 'gishpuppy.com',
            'haltospam.com', 'kasmail.com', 'klassmaster.com', 'kurzepost.de', 'mailbidon.com',
            'mailmetrash.com', 'mailmoat.com', 'mailnator.com', 'mailshell.com', 'mailsiphon.com',
            'mailslite.com', 'mytrashmail.com', 'neomailbox.com', 'nervmich.net', 'neverbox.com',
            'nospam.ze.tc', 'nospam4.us', 'nospamfor.us', 'nospammail.net', 'nowmymail.com',
            'objectmail.com', 'oneoffemail.com', 'onewaymail.com', 'oopi.org', 'ordinaryamerican.net',
            'otherinbox.com', 'pancakemail.com', 'pookmail.com', 'proxymail.eu', 'putthisinyourspamdatabase.com',
            'quickinbox.com', 'rcpt.at', 'reallymymail.com', 'recode.me', 'recursor.net', 'rejectmail.com',
            'rppkn.com', 'safe-mail.net', 'selfdestructingmail.com', 'sendspamhere.com', 'shiftmail.com',
            'shitmail.me', 'shitmail.org', 'skeefmail.com', 'slopsbox.com', 'smellfear.com', 'snakemail.com',
            'sneakemail.com', 'sofimail.com', 'sofort-mail.de', 'sogetthis.com', 'spambob.com', 'spambob.net',
            'spambob.org', 'spambog.com', 'spambog.de', 'spambox.us', 'spamcannon.com', 'spamcannon.net',
            'spamcero.com', 'spamcon.org', 'spamcorptastic.com', 'spamcowboy.com', 'spamcowboy.net',
            'spamcowboy.org', 'spamday.com', 'spamex.com', 'spamfree24.com', 'spamgoes.com',
            'spamgourmet.com', 'spamhole.com', 'spamify.com', 'spaminator.de', 'spamkill.info',
            'spaml.com', 'spaml.de', 'spammotel.com', 'spamobox.com', 'spamoff.de', 'spamslicer.com',
            'spamspot.com', 'spamthis.co.uk', 'spamthisplease.com', 'spamtrail.com', 'speed.1s.fr',
            'spoofmail.de', 'stuffmail.de', 'super-auswahl.de', 'supergreatmail.com', 'supermailer.jp',
            'suremail.info', 'talkinator.com', 'teewars.org', 'teleworm.com', 'tempalias.com',
            'tempe-mail.com', 'tempemail.biz', 'tempemail.co.za', 'tempemail.com', 'tempinbox.co.uk',
            'tempmail.eu', 'tempmail.it', 'tempmail2.com', 'tempmaildemo.com', 'tempmailer.de',
            'tempomail.fr', 'temporarily.de', 'tempthe.net', 'thankyou2010.com', 'throwawayemailaddress.com',
            'tilien.com', 'tmailinator.com', 'tradermail.info', 'trash-mail.at', 'trash-mail.de',
            'trashdevil.com', 'trashemail.de', 'trashmail.at', 'trashmail.de', 'trashmailer.com',
            'trashymail.net', 'trbvm.com', 'trillianpro.com', 'tryalert.com', 'twinmail.de', 'tyldd.com',
            'uggsrock.com', 'upliftnow.com', 'uroid.com', 'venompen.com', 'viditag.com', 'viewcastmedia.com',
            'webm4il.info', 'wegwerfadresse.de', 'wegwerfemail.de', 'wh4f.org', 'whyspam.me',
            'willselfdestruct.com', 'winemaven.info', 'wronghead.com', 'wuzup.net', 'wuzupmail.net',
            'xagloo.com', 'xemaps.com', 'xents.com', 'xmaily.com', 'xoxy.net', 'yep.it', 'yogamaven.com',
            'youmailr.com', 'yuurok.com', 'z1p.biz', 'zehnminuten.de', 'zehnminutenmail.de', 'zoemail.org',
            'zomg.info',
            // Fake / placeholder / lab domains.
            'test.com', 'test.org', 'test.net', 'test.edu', 'test.co',
            'example.com', 'example.org', 'example.net', 'example.edu',
            'domain.com', 'email.test', 'mail.test', 'localhost.com',
            'invalid.com', 'mailinator.com', 'tempmail.com',
        ];
    }

    /**
     * Reserved / fake hostname labels (RFC 2606-style and common placeholders).
     * Blocks addresses like user@test.nic.in even when under an allowlisted suffix.
     *
     * @return string[]
     */
    protected static function reserved_labels(): array {
        return ['test', 'invalid', 'localhost', 'example', 'local', 'fake', 'temp', 'tmp', 'dummy'];
    }

    /**
     * Reserved TLDs (RFC 2606 / 6761).
     *
     * @return string[]
     */
    protected static function reserved_tlds(): array {
        return ['test', 'invalid', 'localhost', 'example', 'local'];
    }

    /**
     * Trusted consumer providers (never treated as disposable).
     * Organisation domains are allowed via MX check instead of this list.
     *
     * @return string[]
     */
    protected static function allowlist(): array {
        return [
            'gmail.com', 'googlemail.com',
            'outlook.com', 'hotmail.com', 'live.com', 'msn.com',
            'yahoo.com', 'yahoo.co.in', 'yahoo.co.uk',
            'icloud.com', 'me.com', 'mac.com',
            'proton.me', 'protonmail.com',
            'aol.com', 'rediffmail.com', 'zoho.com', 'yandex.com', 'gmx.com',
            'mail.com', 'inbox.com',
            'gov.in', 'nic.in', 'edu.in', 'ac.in',
        ];
    }

    /**
     * @param string $email
     * @return string
     */
    public static function domain_from_email(string $email): string {
        $email = \core_text::strtolower(trim($email));
        $pos = strrpos($email, '@');
        if ($pos === false) {
            return '';
        }
        return rtrim(substr($email, $pos + 1), '.');
    }

    /**
     * Whether domain is a trusted consumer / government provider.
     *
     * @param string $domain
     * @return bool
     */
    public static function is_allowlisted_domain(string $domain): bool {
        $domain = \core_text::strtolower(trim($domain));
        $domain = rtrim($domain, '.');
        foreach (self::allowlist() as $allowed) {
            if ($domain === $allowed || str_ends_with($domain, '.' . $allowed)) {
                return true;
            }
        }
        return false;
    }

    /**
     * True when any hostname label is reserved/fake (e.g. test.nic.in).
     *
     * @param string $domain
     * @return bool
     */
    public static function has_reserved_label(string $domain): bool {
        $domain = \core_text::strtolower(trim($domain));
        $domain = rtrim($domain, '.');
        if ($domain === '') {
            return true;
        }
        foreach (explode('.', $domain) as $label) {
            if ($label !== '' && in_array($label, self::reserved_labels(), true)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param string $domain
     * @return bool
     */
    public static function is_disposable_domain(string $domain): bool {
        $domain = \core_text::strtolower(trim($domain));
        $domain = rtrim($domain, '.');
        if ($domain === '' || strpos($domain, '.') === false) {
            return true;
        }

        // Reject user@test.nic.in / user@fake.gov.in before allowlist trust.
        if (self::has_reserved_label($domain)) {
            return true;
        }

        if (self::is_allowlisted_domain($domain)) {
            return false;
        }

        $tld = substr($domain, strrpos($domain, '.') + 1);
        if (in_array($tld, self::reserved_tlds(), true)) {
            return true;
        }

        static $blocked = null;
        if ($blocked === null) {
            $blocked = array_fill_keys(self::disposable_domains(), true);
        }

        if (isset($blocked[$domain])) {
            return true;
        }

        foreach (array_keys($blocked) as $blockeddomain) {
            if (str_ends_with($domain, '.' . $blockeddomain)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Organisation / unknown domains must publish MX.
     * Allowlisted providers (Gmail, Yahoo, *.nic.in, *.gov.in, …) are trusted.
     *
     * @param string $domain
     * @return bool
     */
    public static function domain_has_mail_dns(string $domain): bool {
        $domain = \core_text::strtolower(trim($domain));
        if ($domain === '' || !preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/i', $domain)) {
            return false;
        }

        // Trusted providers / Indian gov mail domains — skip brittle DNS lookups.
        if (self::is_allowlisted_domain($domain)) {
            return true;
        }

        $hasmx = false;
        if (function_exists('checkdnsrr') && @checkdnsrr($domain, 'MX')) {
            $hasmx = true;
        }
        if (!$hasmx && function_exists('getmxrr')) {
            $hosts = [];
            if (@getmxrr($domain, $hosts) && !empty($hosts)) {
                $hasmx = true;
            }
        }
        if (!$hasmx && function_exists('dns_get_record')) {
            $mx = @dns_get_record($domain, DNS_MX);
            $hasmx = is_array($mx) && count($mx) > 0;
        }
        return $hasmx;
    }

    /**
     * @param string $email
     * @return array{ok:bool,reason:string,message:string,toast:string}
     */
    public static function validate(string $email): array {
        $email = \core_text::strtolower(trim($email));
        $toast = get_string_manager()->string_exists('registeremailtoast', 'theme_iiidem2')
            ? get_string('registeremailtoast', 'theme_iiidem2')
            : 'Please check the email';

        if ($email === '' || !validate_email($email)) {
            return [
                'ok' => false,
                'reason' => 'invalid',
                'message' => get_string('invalidemail'),
                'toast' => $toast,
            ];
        }

        $domain = self::domain_from_email($email);
        if ($domain === '' || self::is_disposable_domain($domain)) {
            return [
                'ok' => false,
                'reason' => 'disposable',
                'message' => get_string_manager()->string_exists('registeremaildisposable', 'theme_iiidem2')
                    ? get_string('registeremaildisposable', 'theme_iiidem2')
                    : 'Please check the email. Temporary, disposable, or test email addresses are not allowed.',
                'toast' => $toast,
            ];
        }

        if (!self::domain_has_mail_dns($domain)) {
            return [
                'ok' => false,
                'reason' => 'undeliverable',
                'message' => get_string_manager()->string_exists('registeremailundeliverable', 'theme_iiidem2')
                    ? get_string('registeremailundeliverable', 'theme_iiidem2')
                    : 'This email domain does not appear to accept mail. Please use a genuine email address.',
                'toast' => $toast,
            ];
        }

        return [
            'ok' => true,
            'reason' => 'ok',
            'message' => '',
            'toast' => '',
        ];
    }
}
