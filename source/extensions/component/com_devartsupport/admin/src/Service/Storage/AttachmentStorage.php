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

use Devart\Component\Devartsupport\Administrator\Helper\PrivateAttachmentPath;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Filesystem\File;

final class AttachmentStorage
{
    use BindsIntegerIds;

    public function __construct(private readonly DatabaseInterface $db)
    {
    }

    /**
     * @return list<object{
     *     id: int,
     *     ticket_id: int,
     *     filename: string,
     *     stored_name: string,
     *     mime: string,
     *     size: int,
     *     created: string,
     *     created_by: int
     * }>
     */
    public function listForTicket(int $ticketId): array
    {
        if ($ticketId < 1) {
            return [];
        }

        $query = $this->db->createQuery()
            ->select([
                $this->db->quoteName('id'),
                $this->db->quoteName('ticket_id'),
                $this->db->quoteName('filename'),
                $this->db->quoteName('stored_name'),
                $this->db->quoteName('mime'),
                $this->db->quoteName('size'),
                $this->db->quoteName('created'),
                $this->db->quoteName('created_by'),
            ])
            ->from($this->db->quoteName('#__devartsupport_attachments'))
            ->where($this->db->quoteName('ticket_id') . ' = :ticketId')
            ->order($this->db->quoteName('id') . ' ASC');
        $query->bind(':ticketId', $ticketId, ParameterType::INTEGER);
        $this->db->setQuery($query);

        return array_values($this->db->loadObjectList() ?: []);
    }

    /**
     * @param list<int> $ticketIds
     * @return list<object{
     *     id: int,
     *     ticket_id: int,
     *     filename: string,
     *     stored_name: string,
     *     mime: string,
     *     size: int,
     *     created: string,
     *     created_by: int
     * }>
     */
    public function listForPrivacy(int $userId, array $ticketIds): array
    {
        $userId = max(0, $userId);
        $ticketIds = array_values(array_filter(array_map('intval', $ticketIds), static fn(int $id): bool => $id > 0));

        if ($userId < 1 && $ticketIds === []) {
            return [];
        }

        $query = $this->db->createQuery()
            ->select([
                $this->db->quoteName('id'),
                $this->db->quoteName('ticket_id'),
                $this->db->quoteName('filename'),
                $this->db->quoteName('stored_name'),
                $this->db->quoteName('mime'),
                $this->db->quoteName('size'),
                $this->db->quoteName('created'),
                $this->db->quoteName('created_by'),
            ])
            ->from($this->db->quoteName('#__devartsupport_attachments'))
            ->order($this->db->quoteName('id') . ' ASC');

        $parts = [];

        if ($userId > 0) {
            $parts[] = $this->db->quoteName('created_by') . ' = :privacyAttachmentUser';
            $query->bind(':privacyAttachmentUser', $userId, ParameterType::INTEGER);
        }

        if ($ticketIds !== []) {
            $parts[] = $this->whereIntegerIdsIn($query, $this->db->quoteName('ticket_id'), $ticketIds);
        }

        $query->where('(' . implode(' OR ', $parts) . ')');
        $this->db->setQuery($query);

        return array_values($this->db->loadObjectList() ?: []);
    }

    /**
     * @param list<int> $excludeTicketIds
     * @return list<object{id: int, stored_name: string}>
     */
    public function listCreatedByExcludingTickets(int $userId, array $excludeTicketIds): array
    {
        $userId = max(0, $userId);

        if ($userId < 1) {
            return [];
        }

        $query = $this->db->createQuery()
            ->select([
                $this->db->quoteName('id'),
                $this->db->quoteName('stored_name'),
            ])
            ->from($this->db->quoteName('#__devartsupport_attachments'))
            ->where($this->db->quoteName('created_by') . ' = :privacyCreatedBy');
        $query->bind(':privacyCreatedBy', $userId, ParameterType::INTEGER);

        $excludeTicketIds = array_values(array_filter(array_map('intval', $excludeTicketIds), static fn(int $id): bool => $id > 0));

        if ($excludeTicketIds !== []) {
            $query->where('NOT (' . $this->whereIntegerIdsIn($query, $this->db->quoteName('ticket_id'), $excludeTicketIds) . ')');
        }

        $this->db->setQuery($query);

        return array_values($this->db->loadObjectList() ?: []);
    }

    public function find(int $id): ?object
    {
        if ($id < 1) {
            return null;
        }

        $query = $this->db->createQuery()
            ->select('*')
            ->from($this->db->quoteName('#__devartsupport_attachments'))
            ->where($this->db->quoteName('id') . ' = :attachmentId');
        $query->bind(':attachmentId', $id, ParameterType::INTEGER);
        $this->db->setQuery($query);
        $row = $this->db->loadObject();

        return $row ?: null;
    }

    /**
     * @param array{
     *     ticket_id: int,
     *     filename: string,
     *     stored_name: string,
     *     mime: string,
     *     size: int,
     *     created: string,
     *     created_by: int
     * } $data
     */
    public function insert(array $data): int
    {
        $query = $this->db->createQuery()
            ->insert($this->db->quoteName('#__devartsupport_attachments'))
            ->columns($this->db->quoteName([
                'ticket_id',
                'filename',
                'stored_name',
                'mime',
                'size',
                'created',
                'created_by',
            ]))
            ->values(':ticketId, :filename, :storedName, :mime, :size, :created, :createdBy');
        $query->bind(':ticketId', $data['ticket_id'], ParameterType::INTEGER);
        $query->bind(':filename', $data['filename']);
        $query->bind(':storedName', $data['stored_name']);
        $query->bind(':mime', $data['mime']);
        $query->bind(':size', $data['size'], ParameterType::INTEGER);
        $query->bind(':created', $data['created']);
        $query->bind(':createdBy', $data['created_by'], ParameterType::INTEGER);
        $this->db->setQuery($query)->execute();

        return (int) $this->db->insertid();
    }

    /**
     * @param list<int> $ids
     */
    public function delete(array $ids): int
    {
        if ($ids === []) {
            return 0;
        }

        $query = $this->db->createQuery()
            ->delete($this->db->quoteName('#__devartsupport_attachments'));
        $query->where($this->whereIntegerIdsIn($query, $this->db->quoteName('id'), $ids));
        $this->db->setQuery($query)->execute();

        return $this->db->getAffectedRows();
    }

    /**
     * @param list<int> $ticketIds
     * @return list<object{id: int, stored_name: string}>
     */
    public function listForTickets(array $ticketIds): array
    {
        if ($ticketIds === []) {
            return [];
        }

        $query = $this->db->createQuery()
            ->select([
                $this->db->quoteName('id'),
                $this->db->quoteName('stored_name'),
            ])
            ->from($this->db->quoteName('#__devartsupport_attachments'));
        $query->where($this->whereIntegerIdsIn($query, $this->db->quoteName('ticket_id'), $ticketIds));
        $this->db->setQuery($query);

        return array_values($this->db->loadObjectList() ?: []);
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
            ->delete($this->db->quoteName('#__devartsupport_attachments'));
        $query->where($this->whereIntegerIdsIn($query, $this->db->quoteName('ticket_id'), $ticketIds));
        $this->db->setQuery($query)->execute();

        return $this->db->getAffectedRows();
    }

    public function storeUploadedFile(string $tmpName, string $storedName): void
    {
        $this->ensureDirectory();
        $destination = $this->privatePath($storedName);
        // Allow unsafe here: callers already enforced extension/MIME/size allowlists.
        // Joomla isSafeFile scans ZIP/RAR contents for ".php" and rejects legitimate
        // support packages (e.g. extension install ZIPs) if left at the default.
        File::upload($tmpName, $destination, false, true);
    }

    public function deleteStoredFile(string $storedName): void
    {
        $this->tryDeleteStoredFile($storedName);
    }

    /**
     * Delete private file copies for a stored name.
     *
     * @return bool True when no candidate file remains on disk.
     */
    public function tryDeleteStoredFile(string $storedName): bool
    {
        try {
            foreach ($this->candidatePaths($storedName) as $path) {
                if (!is_file($path)) {
                    continue;
                }

                if (!File::delete($path) && is_file($path)) {
                    return false;
                }
            }
        } catch (\Throwable) {
            return $this->storedFileAbsent($storedName);
        }

        return $this->storedFileAbsent($storedName);
    }

    private function storedFileAbsent(string $storedName): bool
    {
        try {
            foreach ($this->candidatePaths($storedName) as $path) {
                if (is_file($path)) {
                    return false;
                }
            }
        } catch (\Throwable) {
            return false;
        }

        return true;
    }

    public function absolutePath(string $storedName): string
    {
        foreach ($this->candidatePaths($storedName) as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return $this->privatePath($storedName);
    }

    public function ensureDirectory(): void
    {
        PrivateAttachmentPath::ensureDirectory();
    }

    /**
     * Delete attachment rows and private files older than $days (based on created).
     * Processes in bounded batches so Scheduler runs stay within memory/time limits.
     */
    public function purgeOlderThanDays(int $days): int
    {
        $days = max(1, $days);
        $cutoff = gmdate('Y-m-d H:i:s', time() - ($days * 86400));
        $batchSize = 500;
        $maxBatches = 20;
        $removed = 0;
        $afterId = 0;

        for ($batch = 0; $batch < $maxBatches; $batch++) {
            $query = $this->db->createQuery()
                ->select([
                    $this->db->quoteName('id'),
                    $this->db->quoteName('stored_name'),
                ])
                ->from($this->db->quoteName('#__devartsupport_attachments'))
                ->where($this->db->quoteName('created') . ' < :cutoff')
                ->where($this->db->quoteName('id') . ' > :afterId')
                ->order($this->db->quoteName('id') . ' ASC')
                ->bind(':cutoff', $cutoff)
                ->bind(':afterId', $afterId, ParameterType::INTEGER)
                ->setLimit($batchSize);
            $this->db->setQuery($query);
            $rows = array_values($this->db->loadObjectList() ?: []);

            if ($rows === []) {
                break;
            }

            $ids = [];

            foreach ($rows as $row) {
                $id = (int) $row->id;
                $afterId = $id;
                $storedName = (string) $row->stored_name;

                // Remove the private file first. Keep the DB row when deletion
                // fails so the next Scheduler run can retry the same attachment.
                if ($this->tryDeleteStoredFile($storedName)) {
                    $ids[] = $id;
                }
            }

            if ($ids !== []) {
                $this->delete($ids);
                $removed += count($ids);
            }

            if (count($rows) < $batchSize) {
                break;
            }
        }

        return $removed;
    }

    /**
     * @return list<string>
     */
    private function candidatePaths(string $storedName): array
    {
        $this->requireValidStoredName($storedName);
        $paths = [];

        try {
            $paths[] = str_replace('\\', '/', PrivateAttachmentPath::directory() . '/' . $storedName);
        } catch (\Throwable) {
            // Private storage can be unavailable on some hosts; still try legacy files.
        }

        $legacy = str_replace('\\', '/', PrivateAttachmentPath::legacyDirectory() . '/' . $storedName);

        if (is_file($legacy)) {
            $paths[] = $legacy;
        }

        return $paths;
    }

    private function privatePath(string $storedName): string
    {
        $this->requireValidStoredName($storedName);

        return str_replace('\\', '/', PrivateAttachmentPath::directory() . '/' . $storedName);
    }


    private function requireValidStoredName(string $storedName): void
    {
        $parts = explode('.', $storedName, 2);

        if (
            count($parts) !== 2
            || strlen($parts[0]) !== 32
            || !ctype_xdigit($parts[0])
            || strlen($parts[1]) < 2
            || strlen($parts[1]) > 5
            || !ctype_alnum($parts[1])
            || $parts[1] !== strtolower($parts[1])
        ) {
            throw new \RuntimeException('Invalid attachment storage name.');
        }
    }
}
