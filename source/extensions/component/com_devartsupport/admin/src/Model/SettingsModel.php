<?php
/**
 * @package     DevArt Support
 * @subpackage  com_devartsupport
 *
 * @copyright   Copyright (C) 2026 Stathopoulos Kostas - DevArt. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Devart\Component\Devartsupport\Administrator\Model;

defined('_JEXEC') or die;

use Devart\Component\Devartsupport\Administrator\Helper\EmailSettings;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Mail\MailHelper;
use Joomla\CMS\MVC\Model\FormModel;
use Joomla\Database\ParameterType;
use Joomla\Registry\Registry;

final class SettingsModel extends FormModel
{
    public const USER_STATE = 'com_devartsupport.settings.data';

    private const ALLOWED_FIELDS = EmailSettings::KEYS;

    public function getForm($data = [], $loadData = true): Form|false
    {
        return $this->loadForm(
            'com_devartsupport.settings',
            'settings',
            [
                'control'   => 'jform',
                'load_data' => $loadData,
            ]
        );
    }

    protected function loadFormData(): array
    {
        $saved = Factory::getApplication()->getUserState(self::USER_STATE);

        if (is_array($saved) && $saved !== []) {
            return $this->filterAllowed($saved);
        }

        $data = EmailSettings::values();
        $filtered = [];

        foreach (self::ALLOWED_FIELDS as $field) {
            if ($field === 'email_smtp_pass') {
                $filtered[$field] = '';

                continue;
            }

            if (array_key_exists($field, $data)) {
                $filtered[$field] = $data[$field];
            }
        }

        return $filtered;
    }

    public function save(array $data): bool
    {
        $existingEmail = EmailSettings::values();
        $clean = $this->filterAllowed($data);

        // Switchers: use posted value when present; otherwise keep the stored value.
        // Missing keys must not silently reset to 0 (Form filter can omit showon fields).
        $clean['email_enabled'] = $this->switchValue($clean, $existingEmail, 'email_enabled', 0);
        $clean['email_notify_new_ticket'] = $this->switchValue($clean, $existingEmail, 'email_notify_new_ticket', 1);
        $clean['email_notify_reply'] = $this->switchValue($clean, $existingEmail, 'email_notify_reply', 1);
        $clean['email_notify_status_change'] = $this->switchValue($clean, $existingEmail, 'email_notify_status_change', 1);
        $clean['email_notify_requester'] = $this->switchValue($clean, $existingEmail, 'email_notify_requester', 0);
        $clean['email_notify_author_copy'] = $this->switchValue($clean, $existingEmail, 'email_notify_author_copy', 0);
        $clean['email_smtp_auth'] = $this->switchValue($clean, $existingEmail, 'email_smtp_auth', 1);
        $clean['email_from_name'] = trim((string) ($clean['email_from_name'] ?? ''));
        $clean['email_from_email'] = trim((string) ($clean['email_from_email'] ?? ''));
        $clean['email_reply_to'] = trim((string) ($clean['email_reply_to'] ?? ''));
        $clean['email_test_recipient'] = trim((string) ($clean['email_test_recipient'] ?? ''));
        $clean['email_recipients'] = $this->normaliseRecipients((string) ($clean['email_recipients'] ?? ''));
        $clean['email_bcc'] = $this->normaliseRecipients((string) ($clean['email_bcc'] ?? ''));
        $clean['email_mailer'] = $this->normaliseMailer((string) ($clean['email_mailer'] ?? 'global'));
        $clean['email_sendmail_path'] = trim((string) ($clean['email_sendmail_path'] ?? '/usr/sbin/sendmail')) ?: '/usr/sbin/sendmail';
        $clean['email_smtp_host'] = trim((string) ($clean['email_smtp_host'] ?? 'localhost')) ?: 'localhost';
        $clean['email_smtp_port'] = max(1, min(65535, (int) ($clean['email_smtp_port'] ?? 587)));
        $clean['email_smtp_secure'] = $this->normaliseSecure((string) ($clean['email_smtp_secure'] ?? 'tls'));
        $clean['email_smtp_user'] = trim((string) ($clean['email_smtp_user'] ?? ''));

        $submittedPass = (string) ($clean['email_smtp_pass'] ?? '');
        $clean['email_smtp_pass'] = $submittedPass !== ''
            ? $submittedPass
            : (string) ($existingEmail['email_smtp_pass'] ?? '');

        if ($clean['email_from_email'] !== '' && !MailHelper::isEmailAddress($clean['email_from_email'])) {
            throw new \RuntimeException(Text::_('COM_DEVARTSUPPORT_SETTINGS_ERROR_FROM_EMAIL'));
        }

        if ($clean['email_reply_to'] !== '' && !MailHelper::isEmailAddress($clean['email_reply_to'])) {
            throw new \RuntimeException(Text::_('COM_DEVARTSUPPORT_SETTINGS_ERROR_REPLY_TO'));
        }

        if ($clean['email_test_recipient'] !== '' && !MailHelper::isEmailAddress($clean['email_test_recipient'])) {
            throw new \RuntimeException(Text::_('COM_DEVARTSUPPORT_SETTINGS_ERROR_TEST_TO'));
        }

        if ($clean['email_mailer'] === 'smtp' && trim($clean['email_smtp_host']) === '') {
            throw new \RuntimeException(Text::_('COM_DEVARTSUPPORT_SETTINGS_ERROR_SMTP_HOST'));
        }

        if ($clean['email_enabled'] === 1 && $clean['email_recipients'] === '') {
            throw new \RuntimeException(Text::_('COM_DEVARTSUPPORT_SETTINGS_ERROR_RECIPIENTS_REQUIRED'));
        }

        $component = ComponentHelper::getComponent('com_devartsupport');
        $custom = EmailSettings::decodeCustomData((string) ($component->custom_data ?? ''));

        foreach ($clean as $key => $value) {
            $custom[$key] = $value;
        }

        $customJson = json_encode($custom, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if (!is_string($customJson)) {
            throw new \RuntimeException(Text::_('JERROR_AN_ERROR_HAS_OCCURRED'));
        }

        // Keep Options params free of email keys so a later Options save cannot
        // resurrect stale email_* values from params over custom_data.
        $params = new Registry($component->getParams()->toArray());

        foreach (self::ALLOWED_FIELDS as $key) {
            $params->remove($key);
        }

        $paramsJson = $params->toString();
        $db = $this->getDatabase();
        $element = 'com_devartsupport';
        $type = 'component';

        $query = $db->createQuery()
            ->update($db->quoteName('#__extensions'))
            ->set($db->quoteName('custom_data') . ' = :custom')
            ->set($db->quoteName('params') . ' = :params')
            ->where($db->quoteName('element') . ' = :element')
            ->where($db->quoteName('type') . ' = :type')
            ->bind(':custom', $customJson)
            ->bind(':params', $paramsJson)
            ->bind(':element', $element)
            ->bind(':type', $type, ParameterType::STRING);

        $db->setQuery($query)->execute();

        $component->custom_data = $customJson;
        $component->setParams($params);

        // ComponentHelper loads extensions through the _system callback cache.
        // Without a clean, the next request still sees empty custom_data and the
        // Notify requester switch appears stuck on No.
        $this->cleanCache('_system');

        return true;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function filterAllowed(array $data): array
    {
        $clean = [];

        foreach (self::ALLOWED_FIELDS as $field) {
            if (!array_key_exists($field, $data)) {
                continue;
            }

            $clean[$field] = $data[$field];
        }

        return $clean;
    }

    /**
     * @param  array<string, mixed>  $clean
     * @param  array<string, mixed>  $existing
     */
    private function switchValue(array $clean, array $existing, string $key, int $default): int
    {
        if (array_key_exists($key, $clean)) {
            return (int) $clean[$key] === 1 ? 1 : 0;
        }

        return (int) ($existing[$key] ?? $default) === 1 ? 1 : 0;
    }

    private function normaliseMailer(string $mailer): string
    {
        $mailer = strtolower(trim($mailer));

        return in_array($mailer, ['global', 'mail', 'sendmail', 'smtp'], true) ? $mailer : 'global';
    }

    private function normaliseSecure(string $secure): string
    {
        $secure = strtolower(trim($secure));

        return in_array($secure, ['none', 'ssl', 'tls'], true) ? $secure : 'tls';
    }

    private function normaliseRecipients(string $raw): string
    {
        $parts = preg_split('/[\s,;]+/', $raw) ?: [];
        $emails = [];

        foreach ($parts as $part) {
            $email = strtolower(trim((string) $part));

            if ($email === '') {
                continue;
            }

            if (!MailHelper::isEmailAddress($email)) {
                throw new \RuntimeException(Text::sprintf('COM_DEVARTSUPPORT_SETTINGS_ERROR_RECIPIENT', $email));
            }

            $emails[$email] = $email;
        }

        return implode(', ', array_values($emails));
    }
}
