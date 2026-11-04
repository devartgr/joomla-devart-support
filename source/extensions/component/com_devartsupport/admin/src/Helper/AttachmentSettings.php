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

use Joomla\CMS\Component\ComponentHelper;

final class AttachmentSettings
{
    public const DEFAULT_MAX_FILES = 5;
    public const DEFAULT_MAX_MEGABYTES = 8;
    public const MIN_FILES = 1;
    public const MAX_FILES = 20;
    public const MIN_MEGABYTES = 1;
    public const MAX_MEGABYTES = 64;

    /** Hard-coded safe catalog; Options may only enable a subset. */
    public const CATALOG_EXTENSIONS = [
        'csv', 'doc', 'docx', 'gif', 'jpeg', 'jpg', 'pdf', 'png', 'rar', 'txt', 'webp', 'xls', 'xlsx', 'zip',
    ];

    /** @var array<string, string> */
    public const MIME_BY_EXTENSION = [
        'csv'  => 'text/csv',
        'doc'  => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'gif'  => 'image/gif',
        'jpeg' => 'image/jpeg',
        'jpg'  => 'image/jpeg',
        'pdf'  => 'application/pdf',
        'png'  => 'image/png',
        'rar'  => 'application/vnd.rar',
        'txt'  => 'text/plain',
        'webp' => 'image/webp',
        'xls'  => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'zip'  => 'application/zip',
    ];

    /** @var array<string, list<string>> */
    public const ALLOWED_DETECTED_MIMES = [
        'csv'  => ['text/csv', 'text/plain', 'application/csv', 'application/octet-stream'],
        'doc'  => ['application/msword', 'application/octet-stream'],
        'docx' => [
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/zip',
            'application/octet-stream',
        ],
        'gif'  => ['image/gif'],
        'jpeg' => ['image/jpeg'],
        'jpg'  => ['image/jpeg'],
        'pdf'  => ['application/pdf'],
        'png'  => ['image/png'],
        'rar'  => [
            'application/vnd.rar',
            'application/x-rar-compressed',
            'application/x-rar',
            'application/octet-stream',
        ],
        'txt'  => ['text/plain', 'text/csv'],
        'webp' => ['image/webp'],
        'xls'  => ['application/vnd.ms-excel', 'application/octet-stream'],
        'xlsx' => [
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/zip',
            'application/octet-stream',
        ],
        'zip'  => ['application/zip', 'application/x-zip-compressed'],
    ];

    public static function enabled(): bool
    {
        return ((int) ComponentHelper::getParams('com_devartsupport')->get('attachments_enabled', 1)) === 1;
    }

    public static function frontendEnabled(): bool
    {
        return self::enabled()
            && ((int) ComponentHelper::getParams('com_devartsupport')->get('attachments_frontend_enabled', 1)) === 1;
    }

    public static function maxFiles(): int
    {
        return max(
            self::MIN_FILES,
            min(self::MAX_FILES, (int) ComponentHelper::getParams('com_devartsupport')->get('attachments_max_files', self::DEFAULT_MAX_FILES))
        );
    }

    public static function maxMegabytes(): int
    {
        return max(
            self::MIN_MEGABYTES,
            min(self::MAX_MEGABYTES, (int) ComponentHelper::getParams('com_devartsupport')->get('attachments_max_filesize', self::DEFAULT_MAX_MEGABYTES))
        );
    }

    public static function maxBytes(): int
    {
        return self::maxMegabytes() * 1048576;
    }

    /**
     * Safe folder name under the private storage root (not a free filesystem path).
     */
    public static function storageFolderName(): string
    {
        $raw = strtolower(trim((string) ComponentHelper::getParams('com_devartsupport')->get(
            'attachments_storage_folder',
            'com_devartsupport'
        )));
        $raw = preg_replace('/[^a-z0-9_-]/', '', $raw) ?? '';

        if ($raw === '' || strlen($raw) > 64) {
            return 'com_devartsupport';
        }

        return $raw;
    }

    public static function retentionEnabled(): bool
    {
        return self::enabled()
            && ((int) ComponentHelper::getParams('com_devartsupport')->get('attachments_retention_enabled', 0)) === 1;
    }

    /**
     * Retention window in days when enabled (7–3650).
     */
    public static function retentionDays(): int
    {
        return max(
            7,
            min(3650, (int) ComponentHelper::getParams('com_devartsupport')->get('attachments_retention_days', 365))
        );
    }

    /**
     * @return list<string>
     */
    public static function allowedExtensions(): array
    {
        $raw = ComponentHelper::getParams('com_devartsupport')->get('attachments_allowed_extensions', self::CATALOG_EXTENSIONS);

        if (is_string($raw)) {
            $raw = preg_split('/[\s,]+/', $raw) ?: [];
        }

        if (!is_array($raw) || $raw === []) {
            $raw = self::CATALOG_EXTENSIONS;
        }

        $allowed = [];

        foreach ($raw as $ext) {
            $ext = strtolower(trim((string) $ext));

            if ($ext !== '' && isset(self::MIME_BY_EXTENSION[$ext])) {
                $allowed[$ext] = $ext;
            }
        }

        return $allowed !== [] ? array_values($allowed) : self::CATALOG_EXTENSIONS;
    }

    public static function isExtensionAllowed(string $extension): bool
    {
        $extension = strtolower(trim($extension));

        return $extension !== '' && in_array($extension, self::allowedExtensions(), true);
    }

    public static function acceptAttribute(): string
    {
        return '.' . implode(',.', self::allowedExtensions());
    }

    public static function allowedForCategory(?object $category): bool
    {
        if (!self::enabled()) {
            return false;
        }

        if ($category === null) {
            return true;
        }

        return (int) ($category->attachments_enabled ?? 1) === 1;
    }

    public static function allowedForPortalCategory(?object $category): bool
    {
        return self::frontendEnabled() && self::allowedForCategory($category);
    }
}
