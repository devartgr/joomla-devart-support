<?php
/**
 * @package     DevArt Support
 * @subpackage  com_devartsupport
 *
 * @copyright   Copyright (C) 2026 Stathopoulos Kostas - DevArt. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Devart\Component\Devartsupport\Administrator\Helper;

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Mail\MailHelper;
use Joomla\Registry\Registry;

final class EmailSettings
{
    /**
     * Email Settings keys live in extension custom_data so Joomla Options
     * (config.xml → params) cannot wipe them on save.
     *
     * @var list<string>
     */
    public const KEYS = [
        'email_enabled',
        'email_notify_new_ticket',
        'email_notify_reply',
        'email_notify_status_change',
        'email_notify_requester',
        'email_notify_author_copy',
        'email_recipients',
        'email_bcc',
        'email_from_name',
        'email_from_email',
        'email_reply_to',
        'email_mailer',
        'email_sendmail_path',
        'email_smtp_host',
        'email_smtp_port',
        'email_smtp_secure',
        'email_smtp_auth',
        'email_smtp_user',
        'email_smtp_pass',
        'email_test_recipient',
    ];

    public static function enabled(): bool
    {
        return ((int) self::params()->get('email_enabled', 0)) === 1;
    }

    public static function notifyNewTicket(): bool
    {
        return self::enabled()
            && ((int) self::params()->get('email_notify_new_ticket', 1)) === 1;
    }

    public static function notifyReply(): bool
    {
        return self::enabled()
            && ((int) self::params()->get('email_notify_reply', 1)) === 1;
    }

    public static function notifyStatusChange(): bool
    {
        return self::enabled()
            && ((int) self::params()->get('email_notify_status_change', 1)) === 1;
    }

    public static function notifyRequester(): bool
    {
        return self::enabled()
            && ((int) self::params()->get('email_notify_requester', 0)) === 1;
    }

    public static function notifyAuthorCopy(): bool
    {
        return self::enabled()
            && ((int) self::params()->get('email_notify_author_copy', 0)) === 1;
    }

    public static function fromName(): string
    {
        return trim((string) self::params()->get('email_from_name', ''));
    }

    public static function fromEmail(): string
    {
        return trim((string) self::params()->get('email_from_email', ''));
    }

    public static function replyTo(): string
    {
        return trim((string) self::params()->get('email_reply_to', ''));
    }

    public static function mailerMode(): string
    {
        $mode = strtolower(trim((string) self::params()->get('email_mailer', 'global')));

        return in_array($mode, ['global', 'mail', 'sendmail', 'smtp'], true) ? $mode : 'global';
    }

    /**
     * @return list<string>
     */
    public static function recipients(): array
    {
        return self::parseEmails((string) self::params()->get('email_recipients', ''));
    }

    /**
     * @return list<string>
     */
    public static function bcc(): array
    {
        return self::parseEmails((string) self::params()->get('email_bcc', ''));
    }

    /**
     * Mailer settings override for MailerFactory, or null to use Joomla global mail config only.
     */
    public static function mailerRegistry(): ?Registry
    {
        $mode = self::mailerMode();
        $app = Factory::getApplication();
        $settings = new Registry();

        $fromEmail = self::fromEmail();
        $fromName = self::fromName();

        $settings->set('mailfrom', $fromEmail !== '' ? $fromEmail : (string) $app->get('mailfrom'));
        $settings->set('fromname', $fromName !== '' ? $fromName : (string) $app->get('fromname'));
        $settings->set('replyto', self::replyTo());
        $settings->set('replytoname', $fromName !== '' ? $fromName : (string) $app->get('fromname'));

        if ($mode === 'global') {
            $settings->set('mailer', (string) $app->get('mailer', 'mail'));
            $settings->set('sendmail', (string) $app->get('sendmail', '/usr/sbin/sendmail'));
            $settings->set('smtphost', (string) $app->get('smtphost', 'localhost'));
            $settings->set('smtpport', (int) $app->get('smtpport', 25));
            $settings->set('smtpsecure', (string) $app->get('smtpsecure', 'none'));
            $settings->set('smtpauth', (int) $app->get('smtpauth', 0));
            $settings->set('smtpuser', (string) $app->get('smtpuser', ''));
            $settings->set('smtppass', (string) $app->get('smtppass', ''));

            return $settings;
        }

        $settings->set('mailer', $mode);
        $settings->set(
            'sendmail',
            trim((string) self::params()->get('email_sendmail_path', '/usr/sbin/sendmail')) ?: '/usr/sbin/sendmail'
        );
        $settings->set('smtphost', trim((string) self::params()->get('email_smtp_host', 'localhost')) ?: 'localhost');
        $settings->set('smtpport', max(1, min(65535, (int) self::params()->get('email_smtp_port', 587))));
        $secure = strtolower(trim((string) self::params()->get('email_smtp_secure', 'tls')));
        $settings->set('smtpsecure', in_array($secure, ['none', 'ssl', 'tls'], true) ? $secure : 'tls');
        $settings->set('smtpauth', ((int) self::params()->get('email_smtp_auth', 1)) === 1 ? 1 : 0);
        $settings->set('smtpuser', trim((string) self::params()->get('email_smtp_user', '')));
        $settings->set('smtppass', (string) self::params()->get('email_smtp_pass', ''));

        return $settings;
    }

    /**
     * @return list<string>
     */
    public static function parseEmails(string $raw): array
    {
        $parts = preg_split('/[\s,;]+/', $raw) ?: [];
        $emails = [];

        foreach ($parts as $part) {
            $email = strtolower(trim((string) $part));

            if ($email === '' || !MailHelper::isEmailAddress($email)) {
                continue;
            }

            $emails[$email] = $email;
        }

        return array_values($emails);
    }

    /**
     * Email settings for forms and getters (custom_data, with legacy params fallback).
     *
     * @return array<string, mixed>
     */
    public static function values(): array
    {
        return self::params()->toArray();
    }

    private static function params(): Registry
    {
        $component = ComponentHelper::getComponent('com_devartsupport');
        $stored = new Registry(self::decodeCustomData((string) ($component->custom_data ?? '')));
        $legacy = $component->getParams();
        $out = new Registry();

        foreach (self::KEYS as $key) {
            if ($stored->exists($key)) {
                $out->set($key, $stored->get($key));
            } elseif ($legacy->exists($key)) {
                $out->set($key, $legacy->get($key));
            }
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    public static function decodeCustomData(string $json): array
    {
        $json = trim($json);

        if ($json === '' || $json === 'Array') {
            return [];
        }

        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }
}
