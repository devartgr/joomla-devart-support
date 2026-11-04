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
use Devart\Component\Devartsupport\Administrator\Helper\TicketActionLog;
use Devart\Component\Devartsupport\Administrator\Service\Storage\MessageStorage;

/**
 * Administrator staff ticket desk service.
 */
final class TicketService extends AbstractTicketService
{
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
     * @param array<string, mixed> $input
     * @param list<array<string, mixed>> $uploads
     */
    public function update(int $id, array $input, int $staffUserId, array $uploads = []): void
    {
        $existing = $this->tickets->find($id);

        if ($existing === null) {
            throw new TicketNotFoundException('COM_DEVARTSUPPORT_ERROR_NOT_FOUND');
        }

        $expectedVersion = (int) ($input['expected_version'] ?? 0);
        $liveVersion = max(1, (int) ($existing->version ?? 1));

        if ($expectedVersion < 1 || $expectedVersion !== $liveVersion) {
            throw new TicketValidationException('COM_DEVARTSUPPORT_ERROR_TICKET_STALE');
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

            if (!$this->tickets->update($id, $data, $expectedVersion)) {
                throw new TicketValidationException('COM_DEVARTSUPPORT_ERROR_TICKET_STALE');
            }

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
     * @param list<array<string, mixed>> $uploads
     */
    public function create(array $input, int $staffUserId, array $uploads = []): int
    {
        return $this->createTicket($input, $staffUserId, $uploads);
    }

    /**
     * @return array{filename: string, mime: string, size: int, path: string, disposition: string}
     */
    public function download(int $id): array
    {
        return $this->downloadAttachment($id);
    }

    public function attachmentsAllowed(int $categoryId): bool
    {
        return parent::attachmentsAllowed($categoryId);
    }
}
