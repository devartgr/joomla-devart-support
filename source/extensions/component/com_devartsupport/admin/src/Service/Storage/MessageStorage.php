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

use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

final class MessageStorage
{
    use BindsIntegerIds;

    /** Default messages loaded for a ticket thread. */
    public const THREAD_PAGE = 100;

    /** Hard maximum messages loaded in one request. */
    public const THREAD_HARD_CAP = 1000;

    /** @deprecated Use THREAD_PAGE; kept as alias for callers. */
    public const THREAD_CAP = self::THREAD_PAGE;

    public function __construct(private readonly DatabaseInterface $db)
    {
    }

    /**
     * Latest $limit messages for a ticket (oldest→newest for display).
     *
     * @return list<object{id: int, ticket_id: int, user_id: int, body: string, created: string}>
     */
    public function listForTicket(int $ticketId, int $limit = self::THREAD_PAGE): array
    {
        $limit = max(self::THREAD_PAGE, min(self::THREAD_HARD_CAP, $limit));
        $query = $this->db->createQuery()
            ->select([
                $this->db->quoteName('id'),
                $this->db->quoteName('ticket_id'),
                $this->db->quoteName('user_id'),
                $this->db->quoteName('body'),
                $this->db->quoteName('created'),
            ])
            ->from($this->db->quoteName('#__devartsupport_messages'))
            ->where($this->db->quoteName('ticket_id') . ' = :ticketId')
            ->order($this->db->quoteName('created') . ' DESC')
            ->order($this->db->quoteName('id') . ' DESC');
        $query->bind(':ticketId', $ticketId, ParameterType::INTEGER);
        $this->db->setQuery($query, 0, $limit);
        $rows = array_values($this->db->loadObjectList() ?: []);

        return array_reverse($rows);
    }

    public static function normaliseLimit(int $limit): int
    {
        if ($limit < self::THREAD_PAGE) {
            return self::THREAD_PAGE;
        }

        return min(self::THREAD_HARD_CAP, $limit);
    }

    public static function nextLimit(int $currentLimit): int
    {
        return self::normaliseLimit($currentLimit + self::THREAD_PAGE);
    }

    /**
     * @param list<int> $ticketIds
     * @return list<object{id: int, ticket_id: int, user_id: int, body: string, created: string}>
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
                $this->db->quoteName('user_id'),
                $this->db->quoteName('body'),
                $this->db->quoteName('created'),
            ])
            ->from($this->db->quoteName('#__devartsupport_messages'))
            ->order($this->db->quoteName('id') . ' ASC');

        $parts = [];

        if ($userId > 0) {
            $parts[] = $this->db->quoteName('user_id') . ' = :privacyMessageUser';
            $query->bind(':privacyMessageUser', $userId, ParameterType::INTEGER);
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
     */
    public function anonymiseForUserExcludingTickets(int $userId, array $excludeTicketIds): int
    {
        $userId = max(0, $userId);

        if ($userId < 1) {
            return 0;
        }

        $body = '[removed]';
        $zero = 0;
        $query = $this->db->createQuery()
            ->update($this->db->quoteName('#__devartsupport_messages'))
            ->set($this->db->quoteName('body') . ' = :anonBody')
            ->set($this->db->quoteName('user_id') . ' = :anonUser')
            ->where($this->db->quoteName('user_id') . ' = :sourceUser');
        $query->bind(':anonBody', $body);
        $query->bind(':anonUser', $zero, ParameterType::INTEGER);
        $query->bind(':sourceUser', $userId, ParameterType::INTEGER);

        $excludeTicketIds = array_values(array_filter(array_map('intval', $excludeTicketIds), static fn(int $id): bool => $id > 0));

        if ($excludeTicketIds !== []) {
            $query->where('NOT (' . $this->whereIntegerIdsIn($query, $this->db->quoteName('ticket_id'), $excludeTicketIds) . ')');
        }

        $this->db->setQuery($query)->execute();

        return $this->db->getAffectedRows();
    }

    public function countForTicket(int $ticketId): int
    {
        $query = $this->db->createQuery()
            ->select('COUNT(*)')
            ->from($this->db->quoteName('#__devartsupport_messages'))
            ->where($this->db->quoteName('ticket_id') . ' = :ticketId');
        $query->bind(':ticketId', $ticketId, ParameterType::INTEGER);
        $this->db->setQuery($query);

        return (int) $this->db->loadResult();
    }

    public function countByUserSince(int $userId, string $sinceSql): int
    {
        if ($userId < 1) {
            return 0;
        }

        $query = $this->db->createQuery()
            ->select('COUNT(*)')
            ->from($this->db->quoteName('#__devartsupport_messages'))
            ->where($this->db->quoteName('user_id') . ' = :authorId')
            ->where($this->db->quoteName('created') . ' >= :sinceCreated');
        $query->bind(':authorId', $userId, ParameterType::INTEGER);
        $query->bind(':sinceCreated', $sinceSql);
        $this->db->setQuery($query);

        return (int) $this->db->loadResult();
    }

    public function insert(int $ticketId, int $userId, string $body, string $created): int
    {
        $query = $this->db->createQuery()
            ->insert($this->db->quoteName('#__devartsupport_messages'))
            ->columns($this->db->quoteName(['ticket_id', 'user_id', 'body', 'created']))
            ->values(':ticketId, :userId, :body, :created');
        $query->bind(':ticketId', $ticketId, ParameterType::INTEGER);
        $query->bind(':userId', $userId, ParameterType::INTEGER);
        $query->bind(':body', $body);
        $query->bind(':created', $created);
        $this->db->setQuery($query)->execute();

        return (int) $this->db->insertid();
    }

    /**
     * @param list<int> $ticketIds
     */
    public function deleteForTickets(array $ticketIds): void
    {
        if ($ticketIds === []) {
            return;
        }

        $query = $this->db->createQuery()
            ->delete($this->db->quoteName('#__devartsupport_messages'));
        $query->where($this->whereIntegerIdsIn($query, $this->db->quoteName('ticket_id'), $ticketIds));
        $this->db->setQuery($query)->execute();
    }
}
