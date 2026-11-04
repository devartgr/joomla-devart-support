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

final class TicketStorage
{
    use BindsIntegerIds;

    private string $filterStatus = '';

    private string $filterPriority = '';

    private int $filterCategoryId = 0;

    private int $filterOwnerUserId = 0;

    private int $filterPublished = 1;

    private string $filterLike = '';

    public function __construct(private readonly DatabaseInterface $db)
    {
    }

    /**
     * @return list<object{
     *     id: int,
     *     subject: string,
     *     status: string,
     *     user_id: int,
     *     requester_name: string,
     *     requester_email: string,
     *     created: string,
     *     created_by: int,
     *     modified: string,
     *     modified_by: int,
     *     last_message_at: string,
     *     message_count: int
     * }>
     */
    public function list(
        string $search,
        string $status,
        string $priority,
        int $categoryId,
        int $limit,
        int $offset,
        int $ownerUserId = 0,
        int $published = 1
    ): array {
        $query = $this->db->getQuery(true)
            ->select([
                $this->db->quoteName('t.id'),
                $this->db->quoteName('t.subject'),
                $this->db->quoteName('t.status'),
                $this->db->quoteName('t.published'),
                $this->db->quoteName('t.category_id'),
                $this->db->quoteName('c.title', 'category_title'),
                $this->db->quoteName('t.priority'),
                $this->db->quoteName('t.user_id'),
                $this->db->quoteName('t.requester_name'),
                $this->db->quoteName('t.requester_email'),
                $this->db->quoteName('t.created'),
                $this->db->quoteName('t.created_by'),
                $this->db->quoteName('t.modified'),
                $this->db->quoteName('t.modified_by'),
                $this->db->quoteName('t.last_message_at'),
                '(SELECT COUNT(*) FROM ' . $this->db->quoteName('#__devartsupport_messages', 'mc')
                    . ' WHERE ' . $this->db->quoteName('mc.ticket_id') . ' = ' . $this->db->quoteName('t.id') . ') AS '
                    . $this->db->quoteName('message_count'),
                '(SELECT ' . $this->db->quoteName('lm.user_id')
                    . ' FROM ' . $this->db->quoteName('#__devartsupport_messages', 'lm')
                    . ' WHERE ' . $this->db->quoteName('lm.ticket_id') . ' = ' . $this->db->quoteName('t.id')
                    . ' ORDER BY ' . $this->db->quoteName('lm.created') . ' DESC, '
                    . $this->db->quoteName('lm.id') . ' DESC'
                    . ' LIMIT 1) AS ' . $this->db->quoteName('last_message_user_id'),
            ])
            ->from($this->db->quoteName('#__devartsupport_tickets', 't'))
            ->join(
                'LEFT',
                $this->db->quoteName('#__devartsupport_categories', 'c'),
                $this->db->quoteName('c.id') . ' = ' . $this->db->quoteName('t.category_id')
            );

        $this->applyFilters($query, $search, $status, $priority, $categoryId, $ownerUserId, $published);
        $query->order([
            $this->db->quoteName('t.last_message_at') . ' DESC',
            $this->db->quoteName('t.id') . ' DESC',
        ]);
        $this->db->setQuery($query, $offset, $limit);

        return array_values($this->db->loadObjectList() ?: []);
    }

    public function count(
        string $search,
        string $status,
        string $priority,
        int $categoryId,
        int $ownerUserId = 0,
        int $published = 1
    ): int {
        $query = $this->db->getQuery(true)
            ->select('COUNT(*)')
            ->from($this->db->quoteName('#__devartsupport_tickets', 't'));
        $this->applyFilters($query, $search, $status, $priority, $categoryId, $ownerUserId, $published);
        $this->db->setQuery($query);

        return (int) $this->db->loadResult();
    }

    /**
     * @return array{open: int, closed: int}
     */
    public function countsByStatus(): array
    {
        $counts = ['open' => 0, 'closed' => 0];
        $published = 1;
        $query = $this->db->getQuery(true)
            ->select([
                $this->db->quoteName('status'),
                'COUNT(*) AS ' . $this->db->quoteName('total'),
            ])
            ->from($this->db->quoteName('#__devartsupport_tickets'))
            ->where($this->db->quoteName('published') . ' = :publishedCount')
            ->group($this->db->quoteName('status'));
        $query->bind(':publishedCount', $published, ParameterType::INTEGER);
        $this->db->setQuery($query);

        foreach ($this->db->loadObjectList() ?: [] as $row) {
            $status = (string) ($row->status ?? '');
            $total = (int) $row->total;

            if ($status === 'closed') {
                $counts['closed'] += $total;
            } else {
                // Treat open and any legacy values (e.g. pending) as open.
                $counts['open'] += $total;
            }
        }

        return $counts;
    }

    public function find(int $id): ?object
    {
        if ($id < 1) {
            return null;
        }

        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__devartsupport_tickets'))
            ->where($this->db->quoteName('id') . ' = :ticketId');
        $query->bind(':ticketId', $id, ParameterType::INTEGER);
        $this->db->setQuery($query);
        $row = $this->db->loadObject();

        return $row ?: null;
    }

    /**
     * @param list<int> $ids
     * @return array<int, object>
     */
    public function findMany(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__devartsupport_tickets'));
        $query->where($this->whereIntegerIdsIn($query, $this->db->quoteName('id'), $ids));
        $this->db->setQuery($query);
        $rows = $this->db->loadObjectList() ?: [];
        $byId = [];

        foreach ($rows as $row) {
            $byId[(int) $row->id] = $row;
        }

        return $byId;
    }

    /**
     * @return list<int>
     */
    public function findIdsForPrivacySubject(string $email, int $userId): array
    {
        $email = trim($email);
        $userId = max(0, $userId);

        if ($email === '' && $userId < 1) {
            return [];
        }

        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName('id'))
            ->from($this->db->quoteName('#__devartsupport_tickets'));

        $parts = [];

        if ($userId > 0) {
            $parts[] = $this->db->quoteName('user_id') . ' = :privacyUserId';
            $query->bind(':privacyUserId', $userId, ParameterType::INTEGER);
        }

        if ($email !== '') {
            $parts[] = $this->db->quoteName('requester_email') . ' = :privacyEmail';
            $query->bind(':privacyEmail', $email);
        }

        $query->where('(' . implode(' OR ', $parts) . ')');
        $this->db->setQuery($query);

        return array_values(array_map('intval', $this->db->loadColumn() ?: []));
    }

    /**
     * @param array{
     *     subject: string,
     *     status: string,
     *     category_id: int,
     *     priority: string,
     *     user_id: int,
     *     requester_name: string,
     *     requester_email: string,
     *     created: string,
     *     created_by: int,
     *     modified: string,
     *     modified_by: int,
     *     last_message_at: string
     * } $data
     */
    public function insert(array $data): int
    {
        $query = $this->db->getQuery(true)
            ->insert($this->db->quoteName('#__devartsupport_tickets'))
            ->columns($this->db->quoteName([
                'subject',
                'status',
                'published',
                'category_id',
                'priority',
                'user_id',
                'requester_name',
                'requester_email',
                'created',
                'created_by',
                'modified',
                'modified_by',
                'last_message_at',
            ]))
            ->values(':subject, :status, :published, :categoryId, :priority, :userId, :requesterName, :requesterEmail, :created, :createdBy, :modified, :modifiedBy, :lastMessageAt');

        $published = 1;
        $query->bind(':subject', $data['subject']);
        $query->bind(':status', $data['status']);
        $query->bind(':published', $published, ParameterType::INTEGER);
        $query->bind(':categoryId', $data['category_id'], ParameterType::INTEGER);
        $query->bind(':priority', $data['priority']);
        $query->bind(':userId', $data['user_id'], ParameterType::INTEGER);
        $query->bind(':requesterName', $data['requester_name']);
        $query->bind(':requesterEmail', $data['requester_email']);
        $query->bind(':created', $data['created']);
        $query->bind(':createdBy', $data['created_by'], ParameterType::INTEGER);
        $query->bind(':modified', $data['modified']);
        $query->bind(':modifiedBy', $data['modified_by'], ParameterType::INTEGER);
        $query->bind(':lastMessageAt', $data['last_message_at']);
        $this->db->setQuery($query)->execute();

        return (int) $this->db->insertid();
    }

    /**
     * @param list<int> $ids
     */
    public function setPublished(array $ids, int $published): int
    {
        if ($ids === []) {
            return 0;
        }

        $query = $this->db->getQuery(true)
            ->update($this->db->quoteName('#__devartsupport_tickets'))
            ->set($this->db->quoteName('published') . ' = :publishedState');
        $query->bind(':publishedState', $published, ParameterType::INTEGER);
        $query->where($this->whereIntegerIdsIn($query, $this->db->quoteName('id'), $ids));
        $this->db->setQuery($query)->execute();

        return $this->db->getAffectedRows();
    }

    /**
     * @param list<int> $ids
     * @return list<int>
     */
    public function filterIdsByPublished(array $ids, int $published): array
    {
        if ($ids === []) {
            return [];
        }

        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName('id'))
            ->from($this->db->quoteName('#__devartsupport_tickets'))
            ->where($this->db->quoteName('published') . ' = :filterPublishedIds');
        $query->bind(':filterPublishedIds', $published, ParameterType::INTEGER);
        $query->where($this->whereIntegerIdsIn($query, $this->db->quoteName('id'), $ids));
        $this->db->setQuery($query);

        return array_values(array_map('intval', $this->db->loadColumn() ?: []));
    }

    /**
     * @param array{
     *     subject: string,
     *     status: string,
     *     category_id: int,
     *     priority: string,
     *     user_id: int,
     *     requester_name: string,
     *     requester_email: string,
     *     modified: string,
     *     modified_by: int,
     *     last_message_at?: string
     * } $data
     */
    public function update(int $id, array $data): void
    {
        $query = $this->db->getQuery(true)
            ->update($this->db->quoteName('#__devartsupport_tickets'))
            ->set($this->db->quoteName('subject') . ' = :subject')
            ->set($this->db->quoteName('status') . ' = :status')
            ->set($this->db->quoteName('category_id') . ' = :categoryId')
            ->set($this->db->quoteName('priority') . ' = :priority')
            ->set($this->db->quoteName('user_id') . ' = :userId')
            ->set($this->db->quoteName('requester_name') . ' = :requesterName')
            ->set($this->db->quoteName('requester_email') . ' = :requesterEmail')
            ->set($this->db->quoteName('modified') . ' = :modified')
            ->set($this->db->quoteName('modified_by') . ' = :modifiedBy')
            ->where($this->db->quoteName('id') . ' = :ticketId');

        $query->bind(':subject', $data['subject']);
        $query->bind(':status', $data['status']);
        $query->bind(':categoryId', $data['category_id'], ParameterType::INTEGER);
        $query->bind(':priority', $data['priority']);
        $query->bind(':userId', $data['user_id'], ParameterType::INTEGER);
        $query->bind(':requesterName', $data['requester_name']);
        $query->bind(':requesterEmail', $data['requester_email']);
        $query->bind(':modified', $data['modified']);
        $query->bind(':modifiedBy', $data['modified_by'], ParameterType::INTEGER);
        $query->bind(':ticketId', $id, ParameterType::INTEGER);

        if (isset($data['last_message_at'])) {
            $query->set($this->db->quoteName('last_message_at') . ' = :lastMessageAt');
            $query->bind(':lastMessageAt', $data['last_message_at']);
        }

        $this->db->setQuery($query)->execute();
    }

    /**
     * @param list<int> $ids
     */
    public function delete(array $ids): int
    {
        if ($ids === []) {
            return 0;
        }

        $query = $this->db->getQuery(true)
            ->delete($this->db->quoteName('#__devartsupport_tickets'));
        $query->where($this->whereIntegerIdsIn($query, $this->db->quoteName('id'), $ids));
        $this->db->setQuery($query)->execute();

        return $this->db->getAffectedRows();
    }

    /**
     * @param list<int> $ids
     */
    public function updateStatus(array $ids, string $status, string $modified, int $modifiedBy): int
    {
        if ($ids === []) {
            return 0;
        }

        $query = $this->db->getQuery(true)
            ->update($this->db->quoteName('#__devartsupport_tickets'))
            ->set($this->db->quoteName('status') . ' = :status')
            ->set($this->db->quoteName('modified') . ' = :modified')
            ->set($this->db->quoteName('modified_by') . ' = :modifiedBy');
        $query->bind(':status', $status);
        $query->bind(':modified', $modified);
        $query->bind(':modifiedBy', $modifiedBy, ParameterType::INTEGER);
        $query->where($this->whereIntegerIdsIn($query, $this->db->quoteName('id'), $ids));
        $this->db->setQuery($query)->execute();

        return $this->db->getAffectedRows();
    }

    /**
     * @param list<int> $categoryIds
     */
    public function clearCategory(array $categoryIds): void
    {
        if ($categoryIds === []) {
            return;
        }

        $zero = 0;
        $query = $this->db->getQuery(true)
            ->update($this->db->quoteName('#__devartsupport_tickets'))
            ->set($this->db->quoteName('category_id') . ' = :clearedCategory');
        $query->bind(':clearedCategory', $zero, ParameterType::INTEGER);
        $query->where($this->whereIntegerIdsIn($query, $this->db->quoteName('category_id'), $categoryIds));
        $this->db->setQuery($query)->execute();
    }

    public function transactionStart(): void
    {
        $this->db->transactionStart();
    }

    public function transactionCommit(): void
    {
        $this->db->transactionCommit();
    }

    public function transactionRollback(): void
    {
        $this->db->transactionRollback();
    }


    private function applyFilters(
        object $query,
        string $search,
        string $status,
        string $priority,
        int $categoryId,
        int $ownerUserId = 0,
        int $published = 1
    ): void {
        $this->filterPublished = $published === -2 ? -2 : 1;
        $query->where($this->db->quoteName('t.published') . ' = :filterPublished');
        $query->bind(':filterPublished', $this->filterPublished, ParameterType::INTEGER);

        if ($ownerUserId > 0) {
            $this->filterOwnerUserId = $ownerUserId;
            $query->where($this->db->quoteName('t.user_id') . ' = :filterOwnerUser');
            $query->bind(':filterOwnerUser', $this->filterOwnerUserId, ParameterType::INTEGER);
        }

        if ($status !== '') {
            $this->filterStatus = $status;
            $query->where($this->db->quoteName('t.status') . ' = :filterStatus');
            $query->bind(':filterStatus', $this->filterStatus);
        }

        if ($priority !== '') {
            $this->filterPriority = $priority;
            $query->where($this->db->quoteName('t.priority') . ' = :filterPriority');
            $query->bind(':filterPriority', $this->filterPriority);
        }

        if ($categoryId > 0) {
            $this->filterCategoryId = $categoryId;
            $query->where($this->db->quoteName('t.category_id') . ' = :filterCategory');
            $query->bind(':filterCategory', $this->filterCategoryId, ParameterType::INTEGER);
        }

        if ($search === '') {
            return;
        }

        $this->filterLike = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search) . '%';
        $query->where(
            '(' . $this->db->quoteName('t.subject') . ' LIKE :searchSubject'
            . ' OR ' . $this->db->quoteName('t.requester_name') . ' LIKE :searchName'
            . ' OR ' . $this->db->quoteName('t.requester_email') . ' LIKE :searchEmail)'
        );
        $query->bind(':searchSubject', $this->filterLike);
        $query->bind(':searchName', $this->filterLike);
        $query->bind(':searchEmail', $this->filterLike);
    }
}
