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

use Joomla\Database\ParameterType;

trait BindsIntegerIds
{
    /**
     * @param list<int> $ids
     */
    private function whereIntegerIdsIn(object $query, string $column, array $ids): string
    {
        $placeholders = $query->bindArray(array_values($ids), ParameterType::INTEGER);

        return $column . ' IN (' . implode(',', $placeholders) . ')';
    }
}
