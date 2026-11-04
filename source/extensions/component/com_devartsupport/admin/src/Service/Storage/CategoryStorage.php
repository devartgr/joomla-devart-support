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

final class CategoryStorage
{
    use BindsIntegerIds;

    private string $filterLike = '';

    public function __construct(private readonly DatabaseInterface $db)
    {
    }

    /**
     * @return list<object{id: int, title: string, published: int, attachments_enabled: int, ordering: int}>
     */
    public function list(string $search, int $limit, int $offset): array
    {
        $query = $this->db->createQuery()
            ->select([
                $this->db->quoteName('id'),
                $this->db->quoteName('title'),
                $this->db->quoteName('published'),
                $this->db->quoteName('attachments_enabled'),
                $this->db->quoteName('ordering'),
            ])
            ->from($this->db->quoteName('#__devartsupport_categories'));
        $this->applySearch($query, $search);
        $query->order([
            $this->db->quoteName('ordering') . ' ASC',
            $this->db->quoteName('title') . ' ASC',
        ]);
        $this->db->setQuery($query, $offset, $limit);

        return array_values($this->db->loadObjectList() ?: []);
    }

    public function count(string $search): int
    {
        $query = $this->db->createQuery()
            ->select('COUNT(*)')
            ->from($this->db->quoteName('#__devartsupport_categories'));
        $this->applySearch($query, $search);
        $this->db->setQuery($query);

        return (int) $this->db->loadResult();
    }

    /**
     * @return list<object{id: int, title: string, published: int, attachments_enabled: int}>
     */
    public function options(): array
    {
        $query = $this->db->createQuery()
            ->select([
                $this->db->quoteName('id'),
                $this->db->quoteName('title'),
                $this->db->quoteName('published'),
                $this->db->quoteName('attachments_enabled'),
            ])
            ->from($this->db->quoteName('#__devartsupport_categories'))
            ->order([
                $this->db->quoteName('ordering') . ' ASC',
                $this->db->quoteName('title') . ' ASC',
            ]);
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
            ->from($this->db->quoteName('#__devartsupport_categories'))
            ->where($this->db->quoteName('id') . ' = :categoryId');
        $query->bind(':categoryId', $id, ParameterType::INTEGER);
        $this->db->setQuery($query);
        $row = $this->db->loadObject();

        return $row ?: null;
    }

    /**
     * @param array{title: string, published: int, attachments_enabled: int, ordering: int} $data
     */
    public function insert(array $data): int
    {
        $query = $this->db->createQuery()
            ->insert($this->db->quoteName('#__devartsupport_categories'))
            ->columns($this->db->quoteName(['title', 'published', 'attachments_enabled', 'ordering']))
            ->values(':title, :published, :attachmentsEnabled, :ordering');
        $query->bind(':title', $data['title']);
        $query->bind(':published', $data['published'], ParameterType::INTEGER);
        $query->bind(':attachmentsEnabled', $data['attachments_enabled'], ParameterType::INTEGER);
        $query->bind(':ordering', $data['ordering'], ParameterType::INTEGER);
        $this->db->setQuery($query)->execute();

        return (int) $this->db->insertid();
    }

    /**
     * @param array{title: string, published: int, attachments_enabled: int, ordering: int} $data
     */
    public function update(int $id, array $data): void
    {
        $query = $this->db->createQuery()
            ->update($this->db->quoteName('#__devartsupport_categories'))
            ->set($this->db->quoteName('title') . ' = :title')
            ->set($this->db->quoteName('published') . ' = :published')
            ->set($this->db->quoteName('attachments_enabled') . ' = :attachmentsEnabled')
            ->set($this->db->quoteName('ordering') . ' = :ordering')
            ->where($this->db->quoteName('id') . ' = :categoryId');
        $query->bind(':title', $data['title']);
        $query->bind(':published', $data['published'], ParameterType::INTEGER);
        $query->bind(':attachmentsEnabled', $data['attachments_enabled'], ParameterType::INTEGER);
        $query->bind(':ordering', $data['ordering'], ParameterType::INTEGER);
        $query->bind(':categoryId', $id, ParameterType::INTEGER);
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

        $query = $this->db->createQuery()
            ->delete($this->db->quoteName('#__devartsupport_categories'));
        $query->where($this->whereIntegerIdsIn($query, $this->db->quoteName('id'), $ids));
        $this->db->setQuery($query)->execute();

        return $this->db->getAffectedRows();
    }

    public function nextOrdering(): int
    {
        $query = $this->db->createQuery()
            ->select('MAX(' . $this->db->quoteName('ordering') . ')')
            ->from($this->db->quoteName('#__devartsupport_categories'));
        $this->db->setQuery($query);

        return (int) $this->db->loadResult() + 1;
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


    private function applySearch(object $query, string $search): void
    {
        if ($search === '') {
            return;
        }

        $this->filterLike = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search) . '%';
        $query->where($this->db->quoteName('title') . ' LIKE :searchTitle');
        $query->bind(':searchTitle', $this->filterLike);
    }
}
