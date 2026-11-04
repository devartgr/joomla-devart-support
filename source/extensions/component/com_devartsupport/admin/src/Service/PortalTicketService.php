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

use Devart\Component\Devartsupport\Administrator\Exception\TicketNotFoundException;
use Devart\Component\Devartsupport\Administrator\Exception\TicketValidationException;
use Devart\Component\Devartsupport\Administrator\Helper\AttachmentSettings;
use Devart\Component\Devartsupport\Administrator\Helper\PortalRateLimits;
use Devart\Component\Devartsupport\Administrator\Helper\TicketActionLog;
use Devart\Component\Devartsupport\Administrator\Service\Storage\MessageStorage;
use Joomla\CMS\Date\Date;
use Joomla\CMS\Factory;
use Joomla\CMS\Mail\MailHelper;
use Joomla\CMS\User\UserFactoryInterface;

/**
 * Registered-user site portal ticket service (ownership-scoped).
 */
final class PortalTicketService extends AbstractTicketService
{
    /**
     * @return array{items: list<object>, total: int, limit: int, offset: int}
     */
    public function listForOwner(
        int $ownerUserId,
        string $search,
        string $status,
        int $limit,
        int $offset
    ): array {
        $ownerUserId = max(0, $ownerUserId);

        if ($ownerUserId < 1) {
            throw new TicketValidationException('COM_DEVARTSUPPORT_ERROR_PORTAL_LOGIN');
        }

        $search = $this->normaliseSearch($search);
        $status = $this->normaliseFilterStatus($status);
        $limit = max(1, min(self::MAX_LIMIT, $limit <= 0 ? self::MAX_LIMIT : $limit));
        $offset = max(0, $offset);

        return [
            'items'  => $this->tickets->list(
                $search,
                $status,
                '',
                0,
                $limit,
                $offset,
                $ownerUserId,
                self::PUBLISHED
            ),
            'total'  => $this->tickets->count($search, $status, '', 0, $ownerUserId, self::PUBLISHED),
            'limit'  => $limit,
            'offset' => $offset,
        ];
    }

    /**
     * @return array{
     *     ticket: object,
     *     messages: list<object>,
     *     attachments: list<object>,
     *     messages_total: int,
     *     messages_truncated: bool,
     *     messages_limit: int
     * }
     */
    public function getForOwner(int $id, int $ownerUserId, int $messagesLimit = MessageStorage::THREAD_PAGE): array
    {
        $ownerUserId = max(0, $ownerUserId);

        if ($ownerUserId < 1) {
            throw new TicketValidationException('COM_DEVARTSUPPORT_ERROR_PORTAL_LOGIN');
        }

        $ticket = $this->tickets->find($id);

        if (
            $ticket === null
            || (int) $ticket->user_id !== $ownerUserId
            || (int) ($ticket->published ?? self::PUBLISHED) !== self::PUBLISHED
        ) {
            throw new TicketNotFoundException('COM_DEVARTSUPPORT_ERROR_NOT_FOUND');
        }

        $messagesLimit = MessageStorage::normaliseLimit($messagesLimit);
        $messages = $this->messages->listForTicket($id, $messagesLimit);
        $messagesTotal = $this->messages->countForTicket($id);

        return [
            'ticket'             => $ticket,
            'messages'           => $messages,
            'attachments'        => $this->attachments->listForTicket($id),
            'messages_total'     => $messagesTotal,
            'messages_truncated' => $messagesTotal > count($messages),
            'messages_limit'     => $messagesLimit,
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @param list<array<string, mixed>> $uploads
     */
    public function createFromPortal(array $input, int $ownerUserId, array $uploads = []): int
    {
        $ownerUserId = max(0, $ownerUserId);

        if ($ownerUserId < 1) {
            throw new TicketValidationException('COM_DEVARTSUPPORT_ERROR_PORTAL_LOGIN');
        }

        $user = Factory::getContainer()->get(UserFactoryInterface::class)->loadUserById($ownerUserId);

        if ($user === null || (int) $user->id < 1 || (int) $user->block === 1) {
            throw new TicketValidationException('COM_DEVARTSUPPORT_ERROR_PORTAL_LOGIN');
        }

        $name = trim((string) $user->name);
        $email = trim((string) $user->email);

        if ($name === '') {
            $name = 'User #' . $ownerUserId;
        }

        $input['user_id'] = $ownerUserId;
        $input['requester_name'] = $name;
        $input['requester_email'] = $email;
        $input['status'] = self::STATUS_OPEN;

        if (!isset($input['priority']) || trim((string) $input['priority']) === '') {
            $input['priority'] = self::PRIORITY_NORMAL;
        }

        $input['_log_channel'] = 'portal';

        if ($uploads !== [] && !AttachmentSettings::frontendEnabled()) {
            throw new TicketValidationException('COM_DEVARTSUPPORT_ERROR_ATTACHMENT_DISABLED');
        }

        $this->assertPortalCreateAllowed($ownerUserId);

        return $this->createTicket($input, $ownerUserId, $uploads);
    }

    public function setStatusFromPortal(int $id, string $status, int $ownerUserId): void
    {
        $ownerUserId = max(0, $ownerUserId);
        $status = $this->normaliseStatus($status);

        if ($ownerUserId < 1) {
            throw new TicketValidationException('COM_DEVARTSUPPORT_ERROR_PORTAL_LOGIN');
        }

        // Portal owners may only close or reopen (not mark Pending — that is staff-only).
        if ($status !== self::STATUS_OPEN && $status !== self::STATUS_CLOSED) {
            throw new TicketValidationException('COM_DEVARTSUPPORT_ERROR_STATUS');
        }

        $existing = $this->requireOwnedPublishedTicket($id, $ownerUserId);
        $previous = (string) $existing->status;

        if ($previous === $status) {
            return;
        }

        $now = $this->now();
        $this->tickets->updateStatus([$id], $status, $now, $ownerUserId);

        $updated = $this->tickets->find($id);

        if ($updated !== null) {
            $this->mail->notifyStatusChange($updated, $previous, $status);
            $action = $status === self::STATUS_CLOSED ? 'closed' : 'reopened';
            TicketActionLog::log($action, $id, (string) $updated->subject, [
                'status'  => $status,
                'channel' => 'portal',
            ]);
        }
    }

    public function updatePriorityFromPortal(int $id, string $priority, int $ownerUserId): void
    {
        $ownerUserId = max(0, $ownerUserId);
        $priority = $this->normalisePriority($priority);

        if ($ownerUserId < 1) {
            throw new TicketValidationException('COM_DEVARTSUPPORT_ERROR_PORTAL_LOGIN');
        }

        $existing = $this->requireOwnedPublishedTicket($id, $ownerUserId);

        if ((string) ($existing->priority ?? self::PRIORITY_NORMAL) === $priority) {
            return;
        }

        $now = $this->now();
        $this->tickets->updatePriority($id, $priority, $now, $ownerUserId);

        TicketActionLog::log('priority_changed', $id, (string) $existing->subject, [
            'priority' => $priority,
            'channel'  => 'portal',
        ]);
    }

    /**
     * @param list<array<string, mixed>> $uploads
     */
    public function replyFromPortal(int $id, string $reply, int $ownerUserId, array $uploads = []): void
    {
        $ownerUserId = max(0, $ownerUserId);

        if ($ownerUserId < 1) {
            throw new TicketValidationException('COM_DEVARTSUPPORT_ERROR_PORTAL_LOGIN');
        }

        $existing = $this->requireOwnedPublishedTicket($id, $ownerUserId);

        if ($uploads !== [] && !AttachmentSettings::frontendEnabled()) {
            throw new TicketValidationException('COM_DEVARTSUPPORT_ERROR_ATTACHMENT_DISABLED');
        }

        $this->assertPortalReplyAllowed($ownerUserId);

        $reply = $this->normaliseBody($reply, true);
        $files = $this->normaliseUploads($uploads, (int) $existing->category_id);
        $now = $this->now();
        $previousStatus = (string) $existing->status;

        $storedNames = [];
        $this->tickets->transactionStart();

        try {
            $this->messages->insert($id, $ownerUserId, $reply, $now);
            $storedNames = $this->storeUploads($id, $files, $now, $ownerUserId);
            // Reopen only if still closed; never overwrite with a stale pre-txn status.
            $this->tickets->touchAfterReply($id, $now, $ownerUserId, $now);
            $this->tickets->transactionCommit();
        } catch (\Throwable $error) {
            $this->tickets->transactionRollback();
            $this->discardStoredFiles($storedNames);
            throw $error;
        }

        $updated = $this->tickets->find($id);

        if ($updated !== null) {
            $this->mail->notifyReply($updated, $reply);
            $authorEmail = $this->resolveUserEmail($ownerUserId);

            if ($authorEmail === '') {
                $authorEmail = strtolower(trim((string) ($updated->requester_email ?? '')));
            }

            $this->mail->notifyAuthorCopy($updated, $reply, $authorEmail);

            if ($previousStatus !== (string) $updated->status) {
                $this->mail->notifyStatusChange($updated, $previousStatus, (string) $updated->status);
            }

            TicketActionLog::log('replied', $id, (string) $updated->subject, ['channel' => 'portal']);

            foreach ($files as $file) {
                TicketActionLog::log('attachment_uploaded', $id, (string) $updated->subject, [
                    'filename' => (string) $file['filename'],
                    'channel'  => 'portal',
                ]);
            }
        }
    }

    /**
     * @return array{filename: string, mime: string, size: int, path: string, disposition: string}
     */
    public function downloadForOwner(int $attachmentId, int $ownerUserId): array
    {
        $ownerUserId = max(0, $ownerUserId);

        if ($ownerUserId < 1) {
            throw new TicketValidationException('COM_DEVARTSUPPORT_ERROR_PORTAL_LOGIN');
        }

        if (!AttachmentSettings::frontendEnabled()) {
            throw new TicketNotFoundException('COM_DEVARTSUPPORT_ERROR_ATTACHMENT_NOT_FOUND');
        }

        $attachment = $this->attachments->find($attachmentId);

        if ($attachment === null) {
            throw new TicketNotFoundException('COM_DEVARTSUPPORT_ERROR_ATTACHMENT_NOT_FOUND');
        }

        $this->requireOwnedPublishedTicket((int) $attachment->ticket_id, $ownerUserId);

        return $this->downloadAttachment($attachmentId);
    }

    private function requireOwnedPublishedTicket(int $id, int $ownerUserId): object
    {
        $existing = $this->tickets->find($id);

        if (
            $existing === null
            || (int) $existing->user_id !== $ownerUserId
            || (int) ($existing->published ?? self::PUBLISHED) !== self::PUBLISHED
        ) {
            throw new TicketNotFoundException('COM_DEVARTSUPPORT_ERROR_NOT_FOUND');
        }

        return $existing;
    }

    private function assertPortalCreateAllowed(int $ownerUserId): void
    {
        $since = (new Date('now -1 hour'))->toSql();

        if ($this->tickets->countCreatedByUserSince($ownerUserId, $since) >= PortalRateLimits::createHourlyCap()) {
            throw new TicketValidationException('COM_DEVARTSUPPORT_ERROR_PORTAL_RATE_LIMIT');
        }
    }

    private function assertPortalReplyAllowed(int $ownerUserId): void
    {
        $since = (new Date('now -1 hour'))->toSql();

        if ($this->messages->countByUserSince($ownerUserId, $since) >= PortalRateLimits::replyHourlyCap()) {
            throw new TicketValidationException('COM_DEVARTSUPPORT_ERROR_PORTAL_RATE_LIMIT');
        }
    }

    /**
     * @param list<int> $ids
     */
    private function resolveUserEmail(int $userId): string
    {
        $userId = max(0, $userId);

        if ($userId < 1) {
            return '';
        }

        try {
            $user = Factory::getContainer()->get(UserFactoryInterface::class)->loadUserById($userId);
            $email = strtolower(trim((string) ($user->email ?? '')));

            return MailHelper::isEmailAddress($email) ? $email : '';
        } catch (\Throwable) {
            return '';
        }
    }

}
