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

use Devart\Component\Devartsupport\Administrator\Exception\CategoryNotFoundException;
use Devart\Component\Devartsupport\Administrator\Exception\CategoryValidationException;
use Devart\Component\Devartsupport\Administrator\Service\Storage\CategoryStorage;
use Devart\Component\Devartsupport\Administrator\Service\Storage\TicketStorage;

final class CategoryService
{
    private const MAX_TITLE = 255;
    private const MAX_SEARCH = 200;
    private const DEFAULT_LIMIT = 50;
    private const MAX_LIMIT = 100;

    public function __construct(
        private readonly CategoryStorage $categories,
        private readonly TicketStorage $tickets
    ) {
    }

    /**
     * @return array{items: list<object>, total: int, limit: int, offset: int}
     */
    public function list(string $search, int $limit, int $offset): array
    {
        $search = $this->normaliseSearch($search);
        $limit = max(1, min(self::MAX_LIMIT, $limit <= 0 ? self::MAX_LIMIT : $limit));
        $offset = max(0, $offset);

        return [
            'items'  => $this->categories->list($search, $limit, $offset),
            'total'  => $this->categories->count($search),
            'limit'  => $limit,
            'offset' => $offset,
        ];
    }

    /**
     * @return list<object{id: int, title: string, published: int, attachments_enabled: int}>
     */
    public function options(): array
    {
        return $this->categories->options();
    }

    public function get(int $id): object
    {
        $category = $this->categories->find($id);

        if ($category === null) {
            throw new CategoryNotFoundException('COM_DEVARTSUPPORT_ERROR_CATEGORY_NOT_FOUND');
        }

        return $category;
    }

    /**
     * @param array<string, mixed> $input
     */
    public function create(array $input): int
    {
        $data = $this->normaliseInput($input, null);
        $data['ordering'] = $this->categories->nextOrdering();

        return $this->categories->insert($data);
    }

    /**
     * @param array<string, mixed> $input
     */
    public function update(int $id, array $input): void
    {
        $existing = $this->categories->find($id);

        if ($existing === null) {
            throw new CategoryNotFoundException('COM_DEVARTSUPPORT_ERROR_CATEGORY_NOT_FOUND');
        }

        $data = $this->normaliseInput($input, $existing);
        $data['ordering'] = (int) $existing->ordering;
        $this->categories->update($id, $data);
    }

    /**
     * @param list<int> $ids
     */
    public function delete(array $ids): int
    {
        $ids = $this->normaliseIds($ids);

        if ($ids === []) {
            return 0;
        }

        $this->categories->transactionStart();

        try {
            $this->tickets->clearCategory($ids);
            $deleted = $this->categories->delete($ids);
            $this->categories->transactionCommit();
        } catch (\Throwable $error) {
            $this->categories->transactionRollback();
            throw $error;
        }

        return $deleted;
    }

    /**
     * @param array<string, mixed> $input
     * @return array{title: string, published: int, attachments_enabled: int, ordering: int}
     */
    private function normaliseInput(array $input, ?object $existing): array
    {
        $title = trim((string) ($input['title'] ?? ''));

        if ($title === '' || mb_strlen($title) > self::MAX_TITLE) {
            throw new CategoryValidationException('COM_DEVARTSUPPORT_ERROR_CATEGORY_TITLE');
        }

        if (array_key_exists('attachments_enabled', $input)) {
            $attachmentsEnabled = ((int) $input['attachments_enabled']) === 0 ? 0 : 1;
        } elseif ($existing !== null) {
            $attachmentsEnabled = (int) ($existing->attachments_enabled ?? 1) === 0 ? 0 : 1;
        } else {
            $attachmentsEnabled = 1;
        }

        return [
            'title'               => $title,
            'published'           => ((int) ($input['published'] ?? 1)) === 0 ? 0 : 1,
            'attachments_enabled' => $attachmentsEnabled,
            'ordering'            => 0,
        ];
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
}
