<?php
/**
 * @package     DevArt Support
 * @subpackage  com_devartsupport
 *
 * @copyright   Copyright (C) 2026 Stathopoulos Kostas - DevArt. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Devart\Component\Devartsupport\Administrator\Service;

defined('_JEXEC') or die;

use Devart\Component\Devartsupport\Administrator\Helper\EmailSettings;
use Devart\Component\Devartsupport\Administrator\Service\Storage\MailQueueStorage;
use Devart\Component\Devartsupport\Site\Helper\PortalRoute;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Mail\MailerFactoryInterface;
use Joomla\CMS\Mail\MailHelper;
use Joomla\CMS\User\UserFactoryInterface;

/**
 * Ticket notification emails. Notifications are queued; test mail sends immediately.
 * Failures never abort ticket create/update/status changes.
 */
final class TicketMailService
{
    public function __construct(private readonly MailQueueStorage $queue)
    {
    }

    public function notifyNewTicket(object $ticket, string $messageBody): void
    {
        if (!EmailSettings::notifyNewTicket()) {
            return;
        }

        $this->ensureLanguage();
        $this->sendStaff(
            Text::sprintf('COM_DEVARTSUPPORT_MAIL_NEW_SUBJECT', (int) $ticket->id, (string) $ticket->subject),
            $this->mailText(
                'COM_DEVARTSUPPORT_MAIL_NEW_BODY',
                (int) $ticket->id,
                (string) $ticket->subject,
                (string) $ticket->status,
                (string) $ticket->priority,
                (string) $ticket->requester_name,
                (string) $ticket->requester_email,
                $this->plainBody($messageBody)
            ),
            (int) $ticket->id,
            'new_ticket'
        );
    }

    public function notifyReply(object $ticket, string $replyBody): void
    {
        if (!EmailSettings::notifyReply()) {
            return;
        }

        // Language must load before Text::sprintf — portal replies run in site context.
        $this->ensureLanguage();
        $this->sendStaff(
            Text::sprintf('COM_DEVARTSUPPORT_MAIL_REPLY_SUBJECT', (int) $ticket->id, (string) $ticket->subject),
            $this->mailText(
                'COM_DEVARTSUPPORT_MAIL_REPLY_BODY',
                (int) $ticket->id,
                (string) $ticket->subject,
                (string) $ticket->status,
                $this->plainBody($replyBody)
            ),
            (int) $ticket->id,
            'staff_reply'
        );
    }

    public function notifyStatusChange(object $ticket, string $previousStatus, string $newStatus): void
    {
        if (!EmailSettings::notifyStatusChange() || $previousStatus === $newStatus) {
            return;
        }

        $this->ensureLanguage();
        $this->sendStaff(
            Text::sprintf('COM_DEVARTSUPPORT_MAIL_STATUS_SUBJECT', (int) $ticket->id, (string) $ticket->subject),
            $this->mailText(
                'COM_DEVARTSUPPORT_MAIL_STATUS_BODY',
                (int) $ticket->id,
                (string) $ticket->subject,
                $previousStatus,
                $newStatus
            ),
            (int) $ticket->id,
            'status'
        );
    }

    /**
     * Notify the ticket requester that staff replied. Never includes the reply body.
     */
    public function notifyRequesterReply(object $ticket): void
    {
        if (!EmailSettings::notifyRequester()) {
            return;
        }

        $this->ensureLanguage();
        $portalUrl = PortalRoute::absoluteTicket((int) $ticket->id);

        $this->dispatchRequester(
            $ticket,
            Text::sprintf(
                'COM_DEVARTSUPPORT_MAIL_REQUESTER_SUBJECT',
                (int) $ticket->id,
                (string) $ticket->subject
            ),
            $this->mailText(
                'COM_DEVARTSUPPORT_MAIL_REQUESTER_BODY',
                (int) $ticket->id,
                (string) $ticket->subject,
                $portalUrl
            ),
            'requester'
        );
    }

    /**
     * Notify the requester that staff changed status (login link only).
     * Not used when a staff reply mail already went out for the same save.
     */
    public function notifyRequesterStatusChange(object $ticket, string $previousStatus, string $newStatus): void
    {
        if (!EmailSettings::notifyRequester() || $previousStatus === $newStatus) {
            return;
        }

        $this->ensureLanguage();
        $portalUrl = PortalRoute::absoluteTicket((int) $ticket->id);

        $this->dispatchRequester(
            $ticket,
            Text::sprintf(
                'COM_DEVARTSUPPORT_MAIL_REQUESTER_STATUS_SUBJECT',
                (int) $ticket->id,
                (string) $ticket->subject
            ),
            $this->mailText(
                'COM_DEVARTSUPPORT_MAIL_REQUESTER_STATUS_BODY',
                (int) $ticket->id,
                (string) $ticket->subject,
                $previousStatus,
                $newStatus,
                $portalUrl
            ),
            'requester_status'
        );
    }

    private function dispatchRequester(object $ticket, string $subject, string $body, string $kind): void
    {
        $to = $this->resolveRequesterAddress($ticket);

        if ($to === '' || !MailHelper::isEmailAddress($to)) {
            return;
        }

        $this->enqueue([$to], [], $subject, $body, (int) ($ticket->id ?? 0), $kind);
    }

    /**
     * Prefer the live Joomla account email when the ticket has a user_id.
     */
    private function resolveRequesterAddress(object $ticket): string
    {
        $userId = (int) ($ticket->user_id ?? 0);

        if ($userId > 0) {
            try {
                $user = Factory::getContainer()->get(UserFactoryInterface::class)->loadUserById($userId);
                $live = strtolower(trim((string) ($user->email ?? '')));

                if ($live !== '' && MailHelper::isEmailAddress($live)) {
                    return $live;
                }
            } catch (\Throwable) {
                // Fall back to stored requester_email.
            }
        }

        return strtolower(trim((string) ($ticket->requester_email ?? '')));
    }

    /**
     * Send a portal user a copy of the reply they just posted.
     * Staff replies from the administrator never use this path.
     */
    public function notifyAuthorCopy(object $ticket, string $messageBody, string $authorEmail): void
    {
        if (!EmailSettings::notifyAuthorCopy()) {
            return;
        }

        $to = strtolower(trim($authorEmail));

        if ($to === '' || !MailHelper::isEmailAddress($to)) {
            return;
        }

        $this->ensureLanguage();
        $portalUrl = PortalRoute::absoluteTicket((int) $ticket->id);

        $this->enqueue(
            [$to],
            [],
            Text::sprintf(
                'COM_DEVARTSUPPORT_MAIL_AUTHOR_COPY_SUBJECT',
                (int) $ticket->id,
                (string) $ticket->subject
            ),
            $this->mailText(
                'COM_DEVARTSUPPORT_MAIL_AUTHOR_COPY_BODY',
                (int) $ticket->id,
                (string) $ticket->subject,
                $this->plainBody($messageBody),
                $portalUrl
            ),
            (int) $ticket->id,
            'author_copy'
        );
    }

    public function sendTest(string $to): void
    {
        if (!EmailSettings::enabled()) {
            throw new \RuntimeException(Text::_('COM_DEVARTSUPPORT_MAIL_TEST_DISABLED'));
        }

        $to = strtolower(trim($to));

        if ($to === '' || !MailHelper::isEmailAddress($to)) {
            throw new \RuntimeException(Text::_('COM_DEVARTSUPPORT_MAIL_TEST_INVALID'));
        }

        $this->ensureLanguage();
        $this->sendNow(
            [$to],
            [],
            Text::_('COM_DEVARTSUPPORT_MAIL_TEST_SUBJECT'),
            Text::_('COM_DEVARTSUPPORT_MAIL_TEST_BODY')
        );
    }

    /**
     * Immediate SMTP send used by test mail and the queue worker.
     *
     * @param list<string> $recipients
     * @param list<string> $bcc
     */
    public function sendNow(array $recipients, array $bcc, string $subject, string $body): void
    {
        if (!Factory::getApplication()->get('mailonline', 1)) {
            throw new \RuntimeException(Text::_('COM_DEVARTSUPPORT_MAIL_SITE_OFFLINE'));
        }

        if (!EmailSettings::enabled()) {
            throw new \RuntimeException(Text::_('COM_DEVARTSUPPORT_MAIL_TEST_DISABLED'));
        }

        $mailer = Factory::getContainer()
            ->get(MailerFactoryInterface::class)
            ->createMailer(EmailSettings::mailerRegistry());

        $fromEmail = EmailSettings::fromEmail();
        $fromName = EmailSettings::fromName();
        $siteMail = trim((string) Factory::getApplication()->get('mailfrom'));
        $siteName = trim((string) Factory::getApplication()->get('fromname'));

        if ($fromEmail !== '' && MailHelper::isEmailAddress($fromEmail)) {
            $mailer->setSender($fromEmail, $fromName !== '' ? $fromName : $fromEmail);
        } elseif ($siteMail !== '' && MailHelper::isEmailAddress($siteMail)) {
            $mailer->setSender($siteMail, $fromName !== '' ? $fromName : $siteName);
        }

        $replyTo = EmailSettings::replyTo();

        if ($replyTo !== '' && MailHelper::isEmailAddress($replyTo)) {
            $mailer->addReplyTo($replyTo, $fromName !== '' ? $fromName : $siteName);
        }

        foreach ($recipients as $email) {
            $mailer->addRecipient($email);
        }

        foreach ($bcc as $email) {
            if (!in_array($email, $recipients, true)) {
                $mailer->addBcc($email);
            }
        }

        $mailer->setSubject($this->oneLine($subject));
        $mailer->setBody($body);
        $mailer->send();
    }

    private function mailText(string $key, mixed ...$args): string
    {
        return str_replace('\\n', "\n", Text::sprintf($key, ...$args));
    }

    private function sendStaff(string $subject, string $body, int $ticketId, string $kind): void
    {
        $recipients = EmailSettings::recipients();

        if ($recipients === []) {
            $this->warn(Text::_('COM_DEVARTSUPPORT_MAIL_NO_RECIPIENTS'));

            return;
        }

        $this->enqueue($recipients, EmailSettings::bcc(), $subject, $body, $ticketId, $kind);
    }

    /**
     * @param list<string> $recipients
     * @param list<string> $bcc
     */
    private function enqueue(
        array $recipients,
        array $bcc,
        string $subject,
        string $body,
        int $ticketId,
        string $kind
    ): void {
        if (!EmailSettings::enabled()) {
            return;
        }

        try {
            $this->queue->enqueue($recipients, $bcc, $subject, $body, $ticketId, $kind);
        } catch (\Throwable $error) {
            $this->warn(Text::sprintf('COM_DEVARTSUPPORT_MAIL_SEND_FAILED_DETAIL', $error->getMessage()));
        }
    }

    private function warn(string $message): void
    {
        try {
            $app = Factory::getApplication();

            // Site clients must not see SMTP/host details from exception text.
            if (!$app->isClient('administrator')) {
                $message = Text::_('COM_DEVARTSUPPORT_MAIL_SEND_FAILED');
            }

            $app->enqueueMessage($message, 'warning');
        } catch (\Throwable) {
            // Never block the ticket workflow.
        }
    }

    private function plainBody(string $body): string
    {
        $body = trim(strip_tags($body));
        $body = str_replace(["\r\n", "\r"], "\n", $body);

        return $body !== '' ? $body : '-';
    }

    private function oneLine(string $value): string
    {
        return trim(str_replace(["\r", "\n"], ' ', $value));
    }

    private function ensureLanguage(): void
    {
        try {
            $language = Factory::getApplication()->getLanguage();
            $path = JPATH_ADMINISTRATOR . '/components/com_devartsupport';
            // Reload so portal (site) requests pick up administrator .ini strings.
            $language->load('com_devartsupport', $path, $language->getTag(), true, false);
            $language->load('com_devartsupport', $path, 'en-GB', false, true);
        } catch (\Throwable) {
            // Fall back to already loaded keys.
        }
    }
}
