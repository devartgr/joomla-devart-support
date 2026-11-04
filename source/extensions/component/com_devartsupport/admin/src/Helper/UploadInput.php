<?php
/**
 * @package     DevArt Support
 * @subpackage  com_devartsupport
 *
 * @copyright   Copyright (C) 2026 Stathopoulos Kostas - DevArt. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Devart\Component\Devartsupport\Administrator\Helper;

defined('_JEXEC') or die;

/**
 * Normalises PHP multi-file upload arrays into a list of file rows.
 */
final class UploadInput
{
    /**
     * @param mixed $raw Value from InputFiles::get('attachments', [], 'array')
     * @return list<array<string, mixed>>
     */
    public static function attachments(mixed $raw): array
    {
        if ($raw === [] || $raw === null || !is_array($raw)) {
            return [];
        }

        if (isset($raw['name']) && !is_array($raw['name'])) {
            return [$raw];
        }

        if (isset($raw['name']) && is_array($raw['name'])) {
            $files = [];

            foreach (array_keys($raw['name']) as $index) {
                $files[] = [
                    'name'     => $raw['name'][$index] ?? '',
                    'type'     => $raw['type'][$index] ?? '',
                    'tmp_name' => $raw['tmp_name'][$index] ?? '',
                    'error'    => $raw['error'][$index] ?? \UPLOAD_ERR_NO_FILE,
                    'size'     => $raw['size'][$index] ?? 0,
                ];
            }

            return $files;
        }

        return array_values(array_filter($raw, static fn(mixed $row): bool => is_array($row)));
    }
}
