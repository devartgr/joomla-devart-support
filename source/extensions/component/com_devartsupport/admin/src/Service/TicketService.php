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
use Devart\Component\Devartsupport\Administrator\Helper\TicketActionLog;
use Devart\Component\Devartsupport\Administrator\Service\Storage\AttachmentStorage;
use Devart\Component\Devartsupport\Administrator\Service\Storage\CategoryStorage;
use Devart\Component\Devartsupport\Administrator\Service\Storage\MessageStorage;
use Devart\Component\Devartsupport\Administrator\Service\Storage\TicketStorage;
use Joomla\CMS\Date\Date;
use Joomla\CMS\Factory;
use Joomla\CMS\Mail\MailHelper;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\Filesystem\File;
use Joomla\Filesystem\Exception\FilesystemException;

final class TicketService
{
    public const STATUS_OPEN = 'open';
    public const STATUS_CLOSED = 'closed';

    public const PUBLISHED = 1;

    public const TRASHED = -2;

    public const PRIORITY_LOW = 'low';
    public const PRIORITY_NORMAL = 'normal';
    public const PRIORITY_HIGH = 'high';
    public const PRIORITY_URGENT = 'urgent';

    private const STATUSES = [
        self::STATUS_OPEN,
        self::STATUS_CLOSED,
    ];

    private const PRIORITIES = [
        self::PRIORITY_LOW,
        self::PRIORITY_NORMAL,
        self::PRIORITY_HIGH,
        self::PRIORITY_URGENT,
    ];

    private const MAX_SUBJECT = 255;
    private const MAX_NAME = 255;
    private const MAX_EMAIL = 255;
    private const MAX_BODY = 20000;
    private const MAX_SEARCH = 200;
    private const DEFAULT_LIMIT = 50;
    private const MAX_LIMIT = 100;

    /** Portal flood control: new tickets per user per rolling hour. */
    private const PORTAL_CREATE_HOURLY_CAP = 10;

    /** Portal flood control: replies per user per rolling hour. */
    private const PORTAL_REPLY_HOURLY_CAP = 30;

    public function __construct(
        private readonly TicketStorage $tickets,
        private readonly MessageStorage $messages,
        private readonly CategoryStorage $categories,
        private readonly AttachmentStorage $attachments,
        private readonly TicketMailService $mail
    ) {
    }

    /**
     * @return array{items: list<object>, total: int, limit: int, offset: int}
     */
    public function list(
        string $search,
        string $status,
        string $priority,
        int $categoryId,
        int $limit,
        int $offset,
        int $published = self::PUBLISHED
    ): array {
        $search = $this->normaliseSearch($search);
        $status = $this->normaliseFilterStatus($status);
        $priority = $this->normaliseFilterPriority($priority);
        $categoryId = $this->normaliseFilterCategory($categoryId);
        $published = $published === self::TRASHED ? self::TRASHED : self::PUBLISHED;
        $limit = max(1, min(self::MAX_LIMIT, $limit <= 0 ? self::MAX_LIMIT : $limit));
        $offset = max(0, $offset);

        return [
            'items'  => $this->tickets->list($search, $status, $priority, $categoryId, $limit, $offset, 0, $published),
            'total'  => $this->tickets->count($search, $status, $priority, $categoryId, 0, $published),
            'limit'  => $limit,
            'offset' => $offset,
        ];
    }

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
     * @return array{open: int, closed: int}
     */
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
    public function get(int $id, int $messagesLimit = MessageStorage::THREAD_PAGE): array
    {
        $ticket = $this->tickets->find($id);

        if ($ticket === null) {
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

        return $this->create($input, $ownerUserId, $uploads);
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
        $status = (string) $existing->status;

        // Owner reply reopens a closed ticket.
        if ($status === self::STATUS_CLOSED) {
            $status = self::STATUS_OPEN;
        }

        $storedNames = [];
        $this->tickets->transactionStart();

        try {
            $this->messages->insert($id, $ownerUserId, $reply, $now);
            $storedNames = $this->storeUploads($id, $files, $now, $ownerUserId);
            $this->tickets->touchAfterReply($id, $status, $now, $ownerUserId, $now);
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

            if ($status !== (string) $existing->status) {
                $this->mail->notifyStatusChange($updated, (string) $existing->status, $status);
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

        return $this->download($attachmentId);
    }

    /**
     * @param array<string, mixed> $input
     * @param list<array<string, mixed>> $uploads
     */
    public function create(array $input, int $staffUserId, array $uploads = []): int
    {
        $now = $this->now();
        $ticket = $this->normaliseTicketInput($input, true);
        $body = $this->normaliseBody((string) ($input['message'] ?? ''), true);
        $files = $this->normaliseUploads($uploads, (int) $ticket['category_id']);

        $storedNames = [];
        $this->tickets->transactionStart();

        try {
            $id = $this->tickets->insert([
                'subject'         => $ticket['subject'],
                'status'          => $ticket['status'],
                'category_id'     => $ticket['category_id'],
                'priority'        => $ticket['priority'],
                'user_id'         => $ticket['user_id'],
                'requester_name'  => $ticket['requester_name'],
                'requester_email' => $ticket['requester_email'],
                'created'         => $now,
                'created_by'      => max(0, $staffUserId),
                'modified'        => $now,
                'modified_by'     => max(0, $staffUserId),
                'last_message_at' => $now,
            ]);
            $this->messages->insert($id, max(0, $staffUserId), $body, $now);
            $storedNames = $this->storeUploads($id, $files, $now, $staffUserId);
            $this->tickets->transactionCommit();
        } catch (\Throwable $error) {
            $this->tickets->transactionRollback();
            $this->discardStoredFiles($storedNames);
            throw $error;
        }

        $created = $this->tickets->find($id);

        if ($created !== null) {
            $this->mail->notifyNewTicket($created, $body);
            TicketActionLog::log('created', $id, (string) $created->subject, [
                'channel' => (string) ($input['_log_channel'] ?? 'admin'),
            ]);

            foreach ($files as $file) {
                TicketActionLog::log('attachment_uploaded', $id, (string) $created->subject, [
                    'filename' => (string) $file['filename'],
                    'channel'  => (string) ($input['_log_channel'] ?? 'admin'),
                ]);
            }
        }

        return $id;
    }

    /**
     * @param array<string, mixed> $input
     * @param list<array<string, mixed>> $uploads
     */
    public function update(int $id, array $input, int $staffUserId, array $uploads = []): void
    {
        $existing = $this->tickets->find($id);

        if ($existing === null) {
            throw new TicketNotFoundException('COM_DEVARTSUPPORT_ERROR_NOT_FOUND');
        }

        $now = $this->now();
        $ticket = $this->normaliseTicketInput($input, false, (int) ($existing->category_id ?? 0));
        $reply = $this->normaliseBody((string) ($input['reply'] ?? ''), false);
        $files = $this->normaliseUploads($uploads, (int) $ticket['category_id']);
        $data = [
            'subject'         => $ticket['subject'],
            'status'          => $ticket['status'],
            'category_id'     => $ticket['category_id'],
            'priority'        => $ticket['priority'],
            'user_id'         => $ticket['user_id'],
            'requester_name'  => $ticket['requester_name'],
            'requester_email' => $ticket['requester_email'],
            'modified'        => $now,
            'modified_by'     => max(0, $staffUserId),
        ];

        $previousStatus = (string) $existing->status;
        $storedNames = [];
        $this->tickets->transactionStart();

        try {
            if ($reply !== '') {
                $this->messages->insert($id, max(0, $staffUserId), $reply, $now);
                $data['last_message_at'] = $now;
            }

            $storedNames = $this->storeUploads($id, $files, $now, $staffUserId);
            $this->tickets->update($id, $data);
            $this->tickets->transactionCommit();
        } catch (\Throwable $error) {
            $this->tickets->transactionRollback();
            $this->discardStoredFiles($storedNames);
            throw $error;
        }

        $updated = $this->tickets->find($id);

        if ($updated !== null) {
            if ($reply !== '') {
                $this->mail->notifyReply($updated, $reply);
                $this->mail->notifyRequesterReply($updated);
                TicketActionLog::log('replied', $id, (string) $updated->subject, ['channel' => 'admin']);
            }

            if ($previousStatus !== (string) $updated->status) {
                $this->mail->notifyStatusChange($updated, $previousStatus, (string) $updated->status);

                // Status-only staff saves: notify requester. When a reply was also
                // posted, the reply mail already covers the portal login nudge.
                if ($reply === '') {
                    $this->mail->notifyRequesterStatusChange(
                        $updated,
                        $previousStatus,
                        (string) $updated->status
                    );
                }

                TicketActionLog::log('status_changed', $id, (string) $updated->subject, [
                    'status'  => (string) $updated->status,
                    'channel' => 'admin',
                ]);
            }

            TicketActionLog::log('updated', $id, (string) $updated->subject, ['channel' => 'admin']);

            foreach ($files as $file) {
                TicketActionLog::log('attachment_uploaded', $id, (string) $updated->subject, [
                    'filename' => (string) $file['filename'],
                    'channel'  => 'admin',
                ]);
            }
        }
    }

    /**
     * @param list<int> $ids
     */
    public function trash(array $ids): int
    {
        $ids = $this->normaliseIds($ids);

        if ($ids === []) {
            return 0;
        }

        $ids = $this->tickets->filterIdsByPublished($ids, self::PUBLISHED);

        if ($ids === []) {
            return 0;
        }

        $tickets = $this->tickets->findMany($ids);
        $count = $this->tickets->setPublished($ids, self::TRASHED);

        foreach ($ids as $id) {
            $ticket = $tickets[$id] ?? null;
            TicketActionLog::log('trashed', $id, $ticket !== null ? (string) $ticket->subject : '', ['channel' => 'admin']);
        }

        return $count;
    }

    /**
     * @param list<int> $ids
     */
    public function publish(array $ids): int
    {
        $ids = $this->normaliseIds($ids);

        if ($ids === []) {
            return 0;
        }

        $ids = $this->tickets->filterIdsByPublished($ids, self::TRASHED);

        if ($ids === []) {
            return 0;
        }

        $tickets = $this->tickets->findMany($ids);
        $count = $this->tickets->setPublished($ids, self::PUBLISHED);

        foreach ($ids as $id) {
            $ticket = $tickets[$id] ?? null;
            TicketActionLog::log('restored', $id, $ticket !== null ? (string) $ticket->subject : '', ['channel' => 'admin']);
        }

        return $count;
    }

    /**
     * Permanently delete tickets that are already in the trash.
     *
     * @param list<int> $ids
     */
    public function delete(array $ids): int
    {
        $ids = $this->normaliseIds($ids);

        if ($ids === []) {
            return 0;
        }

        return $this->hardDelete($this->tickets->filterIdsByPublished($ids, self::TRASHED));
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

    private function hardDelete(array $ids): int
    {
        $ids = $this->normaliseIds($ids);

        if ($ids === []) {
            return 0;
        }

        $storedFiles = $this->attachments->listForTickets($ids);
        $tickets = $this->tickets->findMany($ids);

        $this->tickets->transactionStart();

        try {
            $this->attachments->deleteForTickets($ids);
            $this->messages->deleteForTickets($ids);
            $deleted = $this->tickets->delete($ids);
            $this->tickets->transactionCommit();
        } catch (\Throwable $error) {
            $this->tickets->transactionRollback();
            throw $error;
        }

        foreach ($ids as $id) {
            $ticket = $tickets[$id] ?? null;
            TicketActionLog::log('deleted', $id, $ticket !== null ? (string) $ticket->subject : '', ['channel' => 'admin']);
        }

        foreach ($storedFiles as $file) {
            $this->attachments->deleteStoredFile((string) $file->stored_name);
        }

        return $deleted;
    }

    /**
     * @return array{
     *     tickets: list<array<string, mixed>>,
     *     messages: list<array<string, mixed>>,
     *     attachments: list<array<string, mixed>>
     * }
     */
    public function privacyCollect(string $email, int $userId): array
    {
        $email = trim($email);
        $userId = max(0, $userId);

        if ($email === '' && $userId < 1) {
            return ['tickets' => [], 'messages' => [], 'attachments' => []];
        }

        $ticketIds = $this->tickets->findIdsForPrivacySubject($email, $userId);
        $tickets = $ticketIds === [] ? [] : array_values($this->tickets->findMany($ticketIds));
        $messages = $this->messages->listForPrivacy($userId, $ticketIds);
        $attachments = $this->attachments->listForPrivacy($userId, $ticketIds);

        return [
            'tickets' => array_map(
                static function (object $ticket): array {
                    return [
                        'id'              => (int) $ticket->id,
                        'subject'         => (string) $ticket->subject,
                        'status'          => (string) $ticket->status,
                        'category_id'     => (int) $ticket->category_id,
                        'priority'        => (string) ($ticket->priority ?? ''),
                        'user_id'         => (int) $ticket->user_id,
                        'requester_name'  => (string) $ticket->requester_name,
                        'requester_email' => (string) $ticket->requester_email,
                        'created'         => (string) $ticket->created,
                        'created_by'      => (int) $ticket->created_by,
                        'modified'        => (string) $ticket->modified,
                        'modified_by'     => (int) $ticket->modified_by,
                        'last_message_at' => (string) $ticket->last_message_at,
                    ];
                },
                $tickets
            ),
            'messages' => array_map(
                static function (object $message): array {
                    return [
                        'id'        => (int) $message->id,
                        'ticket_id' => (int) $message->ticket_id,
                        'user_id'   => (int) $message->user_id,
                        'body'      => (string) $message->body,
                        'created'   => (string) $message->created,
                    ];
                },
                $messages
            ),
            'attachments' => array_map(
                static function (object $attachment): array {
                    return [
                        'id'         => (int) $attachment->id,
                        'ticket_id'  => (int) $attachment->ticket_id,
                        'filename'   => (string) $attachment->filename,
                        'mime'       => (string) $attachment->mime,
                        'size'       => (int) $attachment->size,
                        'created'    => (string) $attachment->created,
                        'created_by' => (int) $attachment->created_by,
                    ];
                },
                $attachments
            ),
        ];
    }

    public function privacyErase(string $email, int $userId): void
    {
        $email = trim($email);
        $userId = max(0, $userId);

        if ($email === '' && $userId < 1) {
            return;
        }

        $ownedTicketIds = $this->tickets->findIdsForPrivacySubject($email, $userId);

        if ($ownedTicketIds !== []) {
            $this->hardDelete($ownedTicketIds);
        }

        if ($userId > 0) {
            $this->messages->anonymiseForUserExcludingTickets($userId, $ownedTicketIds);
            $orphanAttachments = $this->attachments->listCreatedByExcludingTickets($userId, $ownedTicketIds);

            if ($orphanAttachments !== []) {
                $ids = [];
                $stored = [];

                foreach ($orphanAttachments as $row) {
                    $ids[] = (int) $row->id;
                    $stored[] = (string) $row->stored_name;
                }

                $this->attachments->delete($ids);

                foreach ($stored as $storedName) {
                    $this->attachments->deleteStoredFile($storedName);
                }
            }
        }
    }

    /**
     * @param list<int> $ids
     */
    public function changeStatus(array $ids, string $status, int $staffUserId): int
    {
        $ids = $this->normaliseIds($ids);
        $status = $this->normaliseStatus($status);

        if ($ids === []) {
            return 0;
        }

        $tickets = $this->tickets->findMany($ids);
        $changedIds = [];
        $previousById = [];

        foreach ($tickets as $id => $ticket) {
            if ((int) ($ticket->published ?? self::PUBLISHED) !== self::PUBLISHED) {
                continue;
            }

            $previous = (string) $ticket->status;

            if ($previous === $status) {
                continue;
            }

            $changedIds[] = $id;
            $previousById[$id] = $previous;
        }

        if ($changedIds === []) {
            return 0;
        }

        $updated = $this->tickets->updateStatus($changedIds, $status, $this->now(), max(0, $staffUserId));

        foreach ($changedIds as $id) {
            $ticket = $tickets[$id] ?? null;

            if ($ticket === null) {
                continue;
            }

            $ticket->status = $status;
            $this->mail->notifyStatusChange($ticket, $previousById[$id], $status);
            $this->mail->notifyRequesterStatusChange($ticket, $previousById[$id], $status);
            TicketActionLog::log('status_changed', $id, (string) $ticket->subject, [
                'status'  => $status,
                'channel' => 'admin',
            ]);
        }

        return $updated;
    }

    /**
     * @return array{filename: string, mime: string, size: int, path: string, disposition: string}
     */
    public function download(int $id): array
    {
        $attachment = $this->attachments->find($id);

        if ($attachment === null || $this->tickets->find((int) $attachment->ticket_id) === null) {
            throw new TicketNotFoundException('COM_DEVARTSUPPORT_ERROR_ATTACHMENT_NOT_FOUND');
        }

        $path = $this->attachments->absolutePath((string) $attachment->stored_name);

        if (!is_file($path)) {
            throw new TicketNotFoundException('COM_DEVARTSUPPORT_ERROR_ATTACHMENT_NOT_FOUND');
        }

        $mime = (string) $attachment->mime;

        $bytes = filesize($path);

        return [
            'filename'    => (string) $attachment->filename,
            'mime'        => $mime,
            'size'        => $bytes === false ? 0 : (int) $bytes,
            'path'        => $path,
            'disposition' => 'attachment',
        ];
    }

    public function attachmentsAllowed(int $categoryId): bool
    {
        if ($categoryId < 1) {
            return AttachmentSettings::allowedForCategory(null);
        }

        return AttachmentSettings::allowedForCategory($this->categories->find($categoryId));
    }

    /**
     * @return array<int, int>
     */
    public function categoryAttachmentFlags(): array
    {
        $flags = [];

        foreach ($this->categories->options() as $category) {
            $flags[(int) $category->id] = (int) ($category->attachments_enabled ?? 1) === 1 ? 1 : 0;
        }

        return $flags;
    }

    public function deleteAttachment(int $id): int
    {
        $attachment = $this->attachments->find($id);

        if ($attachment === null || $this->tickets->find((int) $attachment->ticket_id) === null) {
            throw new TicketNotFoundException('COM_DEVARTSUPPORT_ERROR_ATTACHMENT_NOT_FOUND');
        }

        $ticketId = (int) $attachment->ticket_id;
        $storedName = (string) $attachment->stored_name;
        $filename = (string) $attachment->filename;
        $ticket = $this->tickets->find($ticketId);

        $this->tickets->transactionStart();

        try {
            $this->attachments->delete([$id]);
            $this->tickets->transactionCommit();
        } catch (\Throwable $error) {
            $this->tickets->transactionRollback();
            throw $error;
        }

        $this->attachments->deleteStoredFile($storedName);
        TicketActionLog::log(
            'attachment_deleted',
            $ticketId,
            $ticket !== null ? (string) $ticket->subject : '',
            ['filename' => $filename, 'channel' => 'admin']
        );

        return $ticketId;
    }

    /**
     * @param array<string, mixed> $input
     * @return array{subject: string, status: string, category_id: int, priority: string, user_id: int, requester_name: string, requester_email: string}
     */
    private function normaliseTicketInput(array $input, bool $requireMessageContext, int $existingCategoryId = 0): array
    {
        $subject = trim((string) ($input['subject'] ?? ''));
        $status = $this->normaliseStatus((string) ($input['status'] ?? self::STATUS_OPEN));
        $categoryId = $this->normaliseCategory((int) ($input['category_id'] ?? 0), $existingCategoryId);
        $priority = $this->normalisePriority((string) ($input['priority'] ?? self::PRIORITY_NORMAL));
        $userId = max(0, (int) ($input['user_id'] ?? 0));
        $name = trim((string) ($input['requester_name'] ?? ''));
        $email = trim((string) ($input['requester_email'] ?? ''));

        if ($subject === '' || mb_strlen($subject) > self::MAX_SUBJECT) {
            throw new TicketValidationException('COM_DEVARTSUPPORT_ERROR_SUBJECT');
        }

        if ($userId > 0) {
            try {
                $user = Factory::getContainer()->get(UserFactoryInterface::class)->loadUserById($userId);

                if ((int) $user->id === $userId && !$user->guest) {
                    if ($name === '') {
                        $name = trim((string) $user->name);
                    }

                    if ($email === '') {
                        $email = trim((string) $user->email);
                    }
                }
                // Missing or deleted users keep the stored user_id for history.
            } catch (\Throwable) {
                // Keep user_id and submitted requester fields.
            }
        }

        if ($name === '' || mb_strlen($name) > self::MAX_NAME) {
            throw new TicketValidationException('COM_DEVARTSUPPORT_ERROR_NAME');
        }

        if ($email !== '' && (mb_strlen($email) > self::MAX_EMAIL || !MailHelper::isEmailAddress($email))) {
            throw new TicketValidationException('COM_DEVARTSUPPORT_ERROR_EMAIL');
        }

        unset($requireMessageContext);

        return [
            'subject'         => $subject,
            'status'          => $status,
            'category_id'     => $categoryId,
            'priority'        => $priority,
            'user_id'         => $userId,
            'requester_name'  => $name,
            'requester_email' => $email,
        ];
    }

    private function normaliseBody(string $body, bool $required): string
    {
        $body = trim($body);
        $body = str_replace(["\r\n", "\r"], "\n", $body);

        if ($required && $body === '') {
            throw new TicketValidationException('COM_DEVARTSUPPORT_ERROR_MESSAGE');
        }

        if (mb_strlen($body) > self::MAX_BODY) {
            throw new TicketValidationException('COM_DEVARTSUPPORT_ERROR_MESSAGE');
        }

        return $body;
    }

    private function normaliseStatus(string $status): string
    {
        $status = strtolower(trim($status));

        if (!in_array($status, self::STATUSES, true)) {
            throw new TicketValidationException('COM_DEVARTSUPPORT_ERROR_STATUS');
        }

        return $status;
    }

    private function normaliseFilterStatus(string $status): string
    {
        $status = strtolower(trim($status));

        if ($status === '') {
            return '';
        }

        return $this->normaliseStatus($status);
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

    private function normalisePriority(string $priority): string
    {
        $priority = strtolower(trim($priority));

        if (!in_array($priority, self::PRIORITIES, true)) {
            throw new TicketValidationException('COM_DEVARTSUPPORT_ERROR_PRIORITY');
        }

        return $priority;
    }

    private function normaliseFilterPriority(string $priority): string
    {
        $priority = strtolower(trim($priority));

        if ($priority === '') {
            return '';
        }

        return $this->normalisePriority($priority);
    }

    private function normaliseCategory(int $categoryId, int $existingCategoryId = 0): int
    {
        $categoryId = max(0, $categoryId);

        if ($categoryId < 1) {
            throw new TicketValidationException('COM_DEVARTSUPPORT_ERROR_CATEGORY_REQUIRED');
        }

        $category = $this->categories->find($categoryId);

        if ($category === null) {
            throw new TicketValidationException('COM_DEVARTSUPPORT_ERROR_CATEGORY');
        }

        if ((int) $category->published !== 1 && $categoryId !== $existingCategoryId) {
            throw new TicketValidationException('COM_DEVARTSUPPORT_ERROR_CATEGORY');
        }

        return $categoryId;
    }

    private function normaliseFilterCategory(int $categoryId): int
    {
        return max(0, $categoryId);
    }

    private function normaliseSearch(string $search): string
    {
        $search = trim($search);

        if (mb_strlen($search) > self::MAX_SEARCH) {
            return mb_substr($search, 0, self::MAX_SEARCH);
        }

        return $search;
    }

    /**
     * @param list<int|string> $ids
     * @return list<int>
     */
    private function normaliseIds(array $ids): array
    {
        $clean = [];

        foreach ($ids as $id) {
            $id = (int) $id;

            if ($id > 0) {
                $clean[$id] = $id;
            }
        }

        return array_values($clean);
    }

    private function assertPortalCreateAllowed(int $ownerUserId): void
    {
        $since = (new Date('now -1 hour'))->toSql();

        if ($this->tickets->countCreatedByUserSince($ownerUserId, $since) >= self::PORTAL_CREATE_HOURLY_CAP) {
            throw new TicketValidationException('COM_DEVARTSUPPORT_ERROR_PORTAL_RATE_LIMIT');
        }
    }

    private function assertPortalReplyAllowed(int $ownerUserId): void
    {
        $since = (new Date('now -1 hour'))->toSql();

        if ($this->messages->countByUserSince($ownerUserId, $since) >= self::PORTAL_REPLY_HOURLY_CAP) {
            throw new TicketValidationException('COM_DEVARTSUPPORT_ERROR_PORTAL_RATE_LIMIT');
        }
    }

    private function now(): string
    {
        return (new Date('now'))->toSql();
    }

    /**
     * @param list<array<string, mixed>> $uploads
     * @return list<array{tmp_name: string, filename: string, mime: string, size: int, extension: string}>
     */
    private function normaliseUploads(array $uploads, int $categoryId): array
    {
        $clean = [];

        foreach ($uploads as $upload) {
            if (!is_array($upload)) {
                continue;
            }

            $error = (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE);

            if ($error === UPLOAD_ERR_NO_FILE || trim((string) ($upload['tmp_name'] ?? '')) === '') {
                continue;
            }

            if ($error !== UPLOAD_ERR_OK) {
                throw new TicketValidationException('COM_DEVARTSUPPORT_ERROR_ATTACHMENT');
            }

            $tmpName = (string) $upload['tmp_name'];
            $size = (int) ($upload['size'] ?? 0);
            $name = $this->sanitizeDisplayFilename((string) ($upload['name'] ?? ''));
            $extension = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));

            if ($name === '' || $extension === '' || !is_uploaded_file($tmpName)) {
                throw new TicketValidationException('COM_DEVARTSUPPORT_ERROR_ATTACHMENT');
            }

            if (
                in_array($extension, File::FORBIDDEN_FILE_EXTENSIONS, true)
                || !AttachmentSettings::isExtensionAllowed($extension)
            ) {
                throw new TicketValidationException('COM_DEVARTSUPPORT_ERROR_ATTACHMENT_TYPE');
            }

            if ($size < 1 || $size > AttachmentSettings::maxBytes()) {
                throw new TicketValidationException('COM_DEVARTSUPPORT_ERROR_ATTACHMENT_SIZE');
            }

            $mime = $this->detectMimeType($tmpName, $extension);

            $clean[] = [
                'tmp_name'  => $tmpName,
                'filename'  => $name,
                'mime'      => $mime,
                'size'      => $size,
                'extension' => $extension,
            ];
        }

        if ($clean !== [] && !$this->attachmentsAllowed($categoryId)) {
            throw new TicketValidationException('COM_DEVARTSUPPORT_ERROR_ATTACHMENT_DISABLED');
        }

        if (count($clean) > AttachmentSettings::maxFiles()) {
            throw new TicketValidationException('COM_DEVARTSUPPORT_ERROR_ATTACHMENT_LIMIT');
        }

        return $clean;
    }

    /**
     * @param list<array{tmp_name: string, filename: string, mime: string, size: int, extension: string}> $files
     * @return list<string>
     */
    private function storeUploads(int $ticketId, array $files, string $created, int $staffUserId): array
    {
        $storedNames = [];

        try {
            foreach ($files as $file) {
                $storedName = bin2hex(random_bytes(16)) . '.' . $file['extension'];

                try {
                    $this->attachments->storeUploadedFile($file['tmp_name'], $storedName);
                } catch (FilesystemException) {
                    throw new TicketValidationException('COM_DEVARTSUPPORT_ERROR_ATTACHMENT');
                }

                $storedNames[] = $storedName;

                $this->attachments->insert([
                    'ticket_id'   => $ticketId,
                    'filename'    => $file['filename'],
                    'stored_name' => $storedName,
                    'mime'        => $file['mime'],
                    'size'        => $file['size'],
                    'created'     => $created,
                    'created_by'  => max(0, $staffUserId),
                ]);
            }
        } catch (\Throwable $error) {
            $this->discardStoredFiles($storedNames);
            throw $error;
        }

        return $storedNames;
    }

    /**
     * @param list<string> $storedNames
     */
    private function discardStoredFiles(array $storedNames): void
    {
        foreach ($storedNames as $storedName) {
            $this->attachments->deleteStoredFile($storedName);
        }
    }

    private function detectMimeType(string $tmpName, string $extension): string
    {
        $canonical = AttachmentSettings::MIME_BY_EXTENSION[$extension] ?? null;
        $allowed = AttachmentSettings::ALLOWED_DETECTED_MIMES[$extension] ?? null;

        if ($canonical === null || $allowed === null) {
            throw new TicketValidationException('COM_DEVARTSUPPORT_ERROR_ATTACHMENT_TYPE');
        }

        if (!class_exists(\finfo::class)) {
            throw new TicketValidationException('COM_DEVARTSUPPORT_ERROR_ATTACHMENT');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $detected = strtolower(trim((string) $finfo->file($tmpName)));

        if ($detected === '' || !in_array($detected, $allowed, true)) {
            throw new TicketValidationException('COM_DEVARTSUPPORT_ERROR_ATTACHMENT_TYPE');
        }

        return $canonical;
    }

    private function sanitizeDisplayFilename(string $original): string
    {
        $original = str_replace(["\0", "\r", "\n"], '', $original);
        $basename = basename(str_replace(['\\', '/'], '/', $original));
        // Avoid hex escapes in the pattern (JAMSS/JED false positives); strip controls + reserved name chars.
        $basename = preg_replace('/[[:cntrl:]<>:"|?*\\\\\/]+/u', '_', $basename) ?? '';
        $basename = trim($basename, " .");

        if ($basename === '' || $basename === '.' || $basename === '..') {
            return '';
        }

        if (mb_strlen($basename) > 255) {
            $extension = strtolower((string) pathinfo($basename, PATHINFO_EXTENSION));
            $stem = (string) pathinfo($basename, PATHINFO_FILENAME);
            $maxStem = max(1, 255 - ($extension !== '' ? strlen($extension) + 1 : 0));
            $stem = mb_substr($stem, 0, $maxStem);
            $basename = $extension !== '' ? $stem . '.' . $extension : $stem;
        }

        return $basename;
    }
}
