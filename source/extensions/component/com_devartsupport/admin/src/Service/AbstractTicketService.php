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
use Devart\Component\Devartsupport\Administrator\Service\Storage\MailQueueStorage;
use Devart\Component\Devartsupport\Administrator\Service\Storage\MessageStorage;
use Devart\Component\Devartsupport\Administrator\Service\Storage\TicketStorage;
use Joomla\CMS\Date\Date;
use Joomla\CMS\Factory;
use Joomla\CMS\Mail\MailHelper;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\Filesystem\File;
use Joomla\Filesystem\Exception\FilesystemException;

abstract class AbstractTicketService
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

    protected const MAX_SUBJECT = 255;
    protected const MAX_NAME = 255;
    protected const MAX_EMAIL = 255;
    protected const MAX_BODY = 20000;
    protected const MAX_SEARCH = 200;
    protected const MAX_LIMIT = 100;


    public function __construct(
        protected readonly TicketStorage $tickets,
        protected readonly MessageStorage $messages,
        protected readonly CategoryStorage $categories,
        protected readonly AttachmentStorage $attachments,
        protected readonly TicketMailService $mail,
        protected readonly MailQueueStorage $mailQueue
    ) {
    }

    protected function hardDelete(array $ids): int
    {
        $ids = $this->normaliseIds($ids);

        if ($ids === []) {
            return 0;
        }

        $storedFiles = $this->attachments->listForTickets($ids);
        $tickets = $this->tickets->findMany($ids);

        $this->tickets->transactionStart();

        try {
            $this->mailQueue->deleteForTickets($ids);
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
     * @param array<string, mixed> $input
     * @return array{subject: string, status: string, category_id: int, priority: string, user_id: int, requester_name: string, requester_email: string}
     */
    protected function normaliseTicketInput(array $input, bool $requireMessageContext, int $existingCategoryId = 0): array
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

    protected function normaliseBody(string $body, bool $required): string
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

    protected function normaliseStatus(string $status): string
    {
        $status = strtolower(trim($status));

        if (!in_array($status, self::STATUSES, true)) {
            throw new TicketValidationException('COM_DEVARTSUPPORT_ERROR_STATUS');
        }

        return $status;
    }

    protected function normaliseFilterStatus(string $status): string
    {
        $status = strtolower(trim($status));

        if ($status === '') {
            return '';
        }

        return $this->normaliseStatus($status);
    }

    protected function normalisePriority(string $priority): string
    {
        $priority = strtolower(trim($priority));

        if (!in_array($priority, self::PRIORITIES, true)) {
            throw new TicketValidationException('COM_DEVARTSUPPORT_ERROR_PRIORITY');
        }

        return $priority;
    }

    protected function normaliseFilterPriority(string $priority): string
    {
        $priority = strtolower(trim($priority));

        if ($priority === '') {
            return '';
        }

        return $this->normalisePriority($priority);
    }

    protected function normaliseCategory(int $categoryId, int $existingCategoryId = 0): int
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

    protected function normaliseFilterCategory(int $categoryId): int
    {
        return max(0, $categoryId);
    }

    protected function normaliseSearch(string $search): string
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
    protected function normaliseIds(array $ids): array
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

    protected function now(): string
    {
        return (new Date('now'))->toSql();
    }

    /**
     * @param list<array<string, mixed>> $uploads
     * @return list<array{tmp_name: string, filename: string, mime: string, size: int, extension: string}>
     */
    protected function normaliseUploads(array $uploads, int $categoryId): array
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
    protected function storeUploads(int $ticketId, array $files, string $created, int $staffUserId): array
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
    protected function discardStoredFiles(array $storedNames): void
    {
        foreach ($storedNames as $storedName) {
            $this->attachments->deleteStoredFile($storedName);
        }
    }

    protected function detectMimeType(string $tmpName, string $extension): string
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

        if ($extension === 'zip') {
            $this->assertZipLocalHeader($tmpName);
        }

        return $canonical;
    }

    /**
     * Reject non-ZIP payloads that only match by extension / loose MIME.
     */
    private function assertZipLocalHeader(string $tmpName): void
    {
        $handle = @fopen($tmpName, 'rb');

        if ($handle === false) {
            throw new TicketValidationException('COM_DEVARTSUPPORT_ERROR_ATTACHMENT_TYPE');
        }

        try {
            $header = fread($handle, 4);

            if ($header !== "PK\x03\x04" && $header !== "PK\x05\x06") {
                throw new TicketValidationException('COM_DEVARTSUPPORT_ERROR_ATTACHMENT_TYPE');
            }
        } finally {
            fclose($handle);
        }
    }

    protected function sanitizeDisplayFilename(string $original): string
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

    /**
     * @param array<string, mixed> $input
     * @param list<array<string, mixed>> $uploads
     */
    protected function createTicket(array $input, int $staffUserId, array $uploads = []): int
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
     * @return array{filename: string, mime: string, size: int, path: string, disposition: string}
     */
    protected function downloadAttachment(int $id): array
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

    protected function attachmentsAllowed(int $categoryId): bool
    {
        if ($categoryId < 1) {
            return AttachmentSettings::allowedForCategory(null);
        }

        return AttachmentSettings::allowedForCategory($this->categories->find($categoryId));
    }

}
