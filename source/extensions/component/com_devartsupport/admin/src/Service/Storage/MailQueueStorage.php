<?php
/**
 * @package     DevArt Support
 * @subpackage  com_devartsupport
 *
 * @copyright   Copyright (C) 2026 Stathopoulos Kostas - DevArt. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Devart\Component\Devartsupport\Administrator\Service\Storage;

defined('_JEXEC') or die;

use Devart\Component\Devartsupport\Administrator\Helper\MailQueueSettings;
use Joomla\CMS\Date\Date;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

final class MailQueueStorage
{
    use BindsIntegerIds;

    public const STATUS_PENDING = 'pending';
    public const STATUS_SENDING = 'sending';
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';

    public function __construct(private readonly DatabaseInterface $db)
    {
    }

    /**
     * @param list<string> $recipients
     * @param list<string> $bcc
     */
    public function enqueue(
        array $recipients,
        array $bcc,
        string $subject,
        string $body,
        int $ticketId,
        string $mailKind
    ): void {
        $recipients = array_values(array_filter(array_map('strval', $recipients)));
        $bcc = array_values(array_filter(array_map('strval', $bcc)));

        if ($recipients === []) {
            return;
        }

        $recipientsJson = json_encode($recipients, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $bccJson = json_encode($bcc, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($recipientsJson === false || $bccJson === false) {
            throw new \RuntimeException('Unable to encode mail queue payload.');
        }

        $now = (new Date('now'))->toSql();
        $maxAttempts = MailQueueSettings::maxAttempts();
        $kind = substr(trim($mailKind), 0, 32);
        $subject = substr(trim($subject), 0, 255);
        $ticketId = max(0, $ticketId);
        $attempts = 0;
        $status = self::STATUS_PENDING;
        $lastError = '';

        $query = $this->db->createQuery()
            ->insert($this->db->quoteName('#__devartsupport_mail_queue'))
            ->columns($this->db->quoteName([
                'ticket_id',
                'mail_kind',
                'recipients_json',
                'bcc_json',
                'subject',
                'body',
                'status',
                'attempts',
                'max_attempts',
                'last_error',
                'created',
                'available_at',
                'sent_at',
            ]))
            ->values(implode(', ', [
                ':ticketId',
                ':mailKind',
                ':recipientsJson',
                ':bccJson',
                ':subject',
                ':body',
                ':status',
                ':attempts',
                ':maxAttempts',
                ':lastError',
                ':created',
                ':availableAt',
                'NULL',
            ]));
        $query->bind(':ticketId', $ticketId, ParameterType::INTEGER);
        $query->bind(':mailKind', $kind);
        $query->bind(':recipientsJson', $recipientsJson);
        $query->bind(':bccJson', $bccJson);
        $query->bind(':subject', $subject);
        $query->bind(':body', $body);
        $query->bind(':status', $status);
        $query->bind(':attempts', $attempts, ParameterType::INTEGER);
        $query->bind(':maxAttempts', $maxAttempts, ParameterType::INTEGER);
        $query->bind(':lastError', $lastError);
        $query->bind(':created', $now);
        $query->bind(':availableAt', $now);
        $this->db->setQuery($query)->execute();
    }

    /**
     * Re-queue rows stuck in sending after a crashed worker run.
     */
    public function recoverStuckSending(int $olderThanMinutes = 30): void
    {
        $olderThanMinutes = max(5, $olderThanMinutes);
        $cutoff = (new Date('now -' . $olderThanMinutes . ' minutes'))->toSql();
        $sending = self::STATUS_SENDING;
        $pending = self::STATUS_PENDING;

        $query = $this->db->createQuery()
            ->update($this->db->quoteName('#__devartsupport_mail_queue'))
            ->set($this->db->quoteName('status') . ' = :pending')
            ->where($this->db->quoteName('status') . ' = :sending')
            ->where($this->db->quoteName('available_at') . ' < :cutoff');
        $query->bind(':pending', $pending);
        $query->bind(':sending', $sending);
        $query->bind(':cutoff', $cutoff);
        $this->db->setQuery($query)->execute();
    }

    /**
     * @return list<object>
     */
    public function listClaimCandidates(int $limit): array
    {
        $this->recoverStuckSending();

        $limit = max(1, min(MailQueueSettings::MAX_BATCH, $limit));
        $now = (new Date('now'))->toSql();
        $status = self::STATUS_PENDING;

        $query = $this->db->createQuery()
            ->select('*')
            ->from($this->db->quoteName('#__devartsupport_mail_queue'))
            ->where($this->db->quoteName('status') . ' = :status')
            ->where($this->db->quoteName('available_at') . ' <= :availableAt')
            ->order($this->db->quoteName('id') . ' ASC')
            ->bind(':status', $status)
            ->bind(':availableAt', $now)
            ->setLimit($limit);
        $this->db->setQuery($query);

        return array_values($this->db->loadObjectList() ?: []);
    }

    public function claim(int $id): bool
    {
        if ($id < 1) {
            return false;
        }

        $pending = self::STATUS_PENDING;
        $sending = self::STATUS_SENDING;
        $now = (new Date('now'))->toSql();

        $query = $this->db->createQuery()
            ->update($this->db->quoteName('#__devartsupport_mail_queue'))
            ->set($this->db->quoteName('status') . ' = :sending')
            ->set($this->db->quoteName('attempts') . ' = ' . $this->db->quoteName('attempts') . ' + 1')
            ->set($this->db->quoteName('available_at') . ' = :lockedAt')
            ->where($this->db->quoteName('id') . ' = :id')
            ->where($this->db->quoteName('status') . ' = :pending')
            ->where($this->db->quoteName('available_at') . ' <= :availableAt');
        $query->bind(':sending', $sending);
        $query->bind(':lockedAt', $now);
        $query->bind(':id', $id, ParameterType::INTEGER);
        $query->bind(':pending', $pending);
        $query->bind(':availableAt', $now);
        $this->db->setQuery($query)->execute();

        return $this->db->getAffectedRows() === 1;
    }

    public function markSent(int $id): void
    {
        if ($id < 1) {
            return;
        }

        $status = self::STATUS_SENT;
        $now = (new Date('now'))->toSql();
        $error = '';

        $query = $this->db->createQuery()
            ->update($this->db->quoteName('#__devartsupport_mail_queue'))
            ->set($this->db->quoteName('status') . ' = :status')
            ->set($this->db->quoteName('sent_at') . ' = :sentAt')
            ->set($this->db->quoteName('last_error') . ' = :lastError')
            ->where($this->db->quoteName('id') . ' = :id');
        $query->bind(':status', $status);
        $query->bind(':sentAt', $now);
        $query->bind(':lastError', $error);
        $query->bind(':id', $id, ParameterType::INTEGER);
        $this->db->setQuery($query)->execute();
    }

    public function markRetryOrFailed(int $id, int $attempts, int $maxAttempts, string $error): void
    {
        if ($id < 1) {
            return;
        }

        $error = substr(trim($error), 0, 512);
        $maxAttempts = max(1, $maxAttempts);

        if ($attempts >= $maxAttempts) {
            $status = self::STATUS_FAILED;
            $availableAt = (new Date('now'))->toSql();
        } else {
            $status = self::STATUS_PENDING;
            $delayMinutes = min(120, 15 * max(1, $attempts));
            $availableAt = (new Date('now +' . $delayMinutes . ' minutes'))->toSql();
        }

        $query = $this->db->createQuery()
            ->update($this->db->quoteName('#__devartsupport_mail_queue'))
            ->set($this->db->quoteName('status') . ' = :status')
            ->set($this->db->quoteName('available_at') . ' = :availableAt')
            ->set($this->db->quoteName('last_error') . ' = :lastError')
            ->where($this->db->quoteName('id') . ' = :id');
        $query->bind(':status', $status);
        $query->bind(':availableAt', $availableAt);
        $query->bind(':lastError', $error);
        $query->bind(':id', $id, ParameterType::INTEGER);
        $this->db->setQuery($query)->execute();
    }

    public function purgeOldSent(int $olderThanDays = MailQueueSettings::SENT_RETENTION_DAYS): int
    {
        $olderThanDays = max(1, $olderThanDays);
        $cutoff = (new Date('now -' . $olderThanDays . ' days'))->toSql();
        $status = self::STATUS_SENT;

        $query = $this->db->createQuery()
            ->delete($this->db->quoteName('#__devartsupport_mail_queue'))
            ->where($this->db->quoteName('status') . ' = :status')
            ->where($this->db->quoteName('sent_at') . ' IS NOT NULL')
            ->where($this->db->quoteName('sent_at') . ' < :cutoff');
        $query->bind(':status', $status);
        $query->bind(':cutoff', $cutoff);
        $this->db->setQuery($query)->execute();

        return $this->db->getAffectedRows();
    }

    /**
     * @param list<int> $ticketIds
     */
    public function deleteForTickets(array $ticketIds): int
    {
        if ($ticketIds === []) {
            return 0;
        }

        $query = $this->db->createQuery()
            ->delete($this->db->quoteName('#__devartsupport_mail_queue'));
        $query->where($this->whereIntegerIdsIn($query, $this->db->quoteName('ticket_id'), $ticketIds));
        $this->db->setQuery($query)->execute();

        return $this->db->getAffectedRows();
    }

    public function countPending(): int
    {
        $status = self::STATUS_PENDING;
        $query = $this->db->createQuery()
            ->select('COUNT(*)')
            ->from($this->db->quoteName('#__devartsupport_mail_queue'))
            ->where($this->db->quoteName('status') . ' = :status');
        $query->bind(':status', $status);
        $this->db->setQuery($query);

        return (int) $this->db->loadResult();
    }
}
