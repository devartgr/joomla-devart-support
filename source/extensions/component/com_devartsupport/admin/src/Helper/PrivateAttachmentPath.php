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
 * Canonical private attachment directory outside the Joomla web root.
 */
final class PrivateAttachmentPath
{
    public static function directory(): string
    {
        $directory = self::clean(dirname(JPATH_ROOT) . '/private/com_devartsupport');
        self::ensureOutsideWebRoot($directory);

        return $directory;
    }

    public static function legacyDirectory(): string
    {
        return self::clean(JPATH_ADMINISTRATOR . '/components/com_devartsupport/files');
    }

    /**
     * @return array{ok: bool, writable: bool, outside_webroot: bool}
     */
    public static function health(): array
    {
        try {
            self::ensureDirectory();
            $directory = self::directory();
            $writable = is_dir($directory) && is_writable($directory);
            $outside = true;

            try {
                self::ensureOutsideWebRoot($directory);
            } catch (\RuntimeException) {
                $outside = false;
            }

            return [
                'ok'              => $writable && $outside,
                'writable'        => $writable,
                'outside_webroot' => $outside,
            ];
        } catch (\Throwable) {
            return [
                'ok'              => false,
                'writable'        => false,
                'outside_webroot' => false,
            ];
        }
    }

    public static function ensureDirectory(): void
    {
        $directory = self::directory();

        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new \RuntimeException('Unable to create private attachment storage.');
        }

        self::ensureOutsideWebRoot($directory);
        self::writeDenyFiles($directory);
    }

    public static function migrateLegacyFiles(): int
    {
        $legacy = self::legacyDirectory();
        $target = self::directory();

        if (!is_dir($legacy)) {
            self::ensureDirectory();

            return 0;
        }

        self::ensureDirectory();
        $moved = 0;

        foreach (scandir($legacy) ?: [] as $file) {
            if ($file === '.' || $file === '..' || $file === '.htaccess' || $file === 'index.html') {
                continue;
            }

            if (!preg_match('/^[a-f0-9]{32}\.[a-z0-9]{2,5}$/', $file)) {
                continue;
            }

            $from = self::clean($legacy . '/' . $file);
            $to = self::clean($target . '/' . $file);

            if (!is_file($from)) {
                continue;
            }

            if (is_file($to)) {
                @unlink($from);
                $moved++;

                continue;
            }

            if (@rename($from, $to)) {
                $moved++;

                continue;
            }

            if (@copy($from, $to)) {
                @unlink($from);
                $moved++;
            }
        }

        if (self::legacyHasNoAttachments($legacy)) {
            self::deleteDirectoryTree($legacy);
        } else {
            self::writeDenyFiles($legacy);
        }

        return $moved;
    }

    public static function deleteDirectoryTree(string $directory): void
    {
        $directory = self::clean($directory);

        if (!is_dir($directory)) {
            return;
        }

        foreach (scandir($directory) ?: [] as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $path = $directory . '/' . $file;

            if (is_file($path)) {
                @unlink($path);
            }
        }

        @rmdir($directory);
    }

    private static function legacyHasNoAttachments(string $directory): bool
    {
        foreach (scandir($directory) ?: [] as $file) {
            if ($file === '.' || $file === '..' || $file === '.htaccess' || $file === 'index.html') {
                continue;
            }

            if (preg_match('/^[a-f0-9]{32}\.[a-z0-9]{2,5}$/', $file) && is_file($directory . '/' . $file)) {
                return false;
            }
        }

        return true;
    }

    private static function writeDenyFiles(string $directory): void
    {
        $deny = $directory . '/.htaccess';

        if (!is_file($deny)) {
            file_put_contents(
                $deny,
                "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n"
                . "<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n"
            );
        }

        $index = $directory . '/index.html';

        if (!is_file($index)) {
            file_put_contents($index, '');
        }
    }

    private static function ensureOutsideWebRoot(string $directory): void
    {
        $candidates = array_filter([
            realpath(JPATH_ROOT) ?: self::clean(JPATH_ROOT),
            defined('JPATH_PUBLIC') ? (realpath(JPATH_PUBLIC) ?: self::clean((string) JPATH_PUBLIC)) : null,
            isset($_SERVER['DOCUMENT_ROOT']) && is_string($_SERVER['DOCUMENT_ROOT']) && $_SERVER['DOCUMENT_ROOT'] !== ''
                ? (realpath($_SERVER['DOCUMENT_ROOT']) ?: self::clean($_SERVER['DOCUMENT_ROOT']))
                : null,
        ]);

        $resolved = realpath($directory) ?: $directory;
        $resolved = rtrim(self::clean($resolved), '/');

        foreach ($candidates as $root) {
            $root = rtrim(self::clean((string) $root), '/');

            if ($root === '') {
                continue;
            }

            if ($resolved === $root || str_starts_with($resolved . '/', $root . '/')) {
                throw new \RuntimeException('Attachment storage must remain outside the Joomla web root.');
            }
        }

        $legacy = rtrim(self::clean(self::legacyDirectory()), '/');

        if ($resolved === $legacy || str_starts_with($resolved . '/', $legacy . '/')) {
            throw new \RuntimeException('Attachment storage must remain outside the Joomla web root.');
        }
    }

    private static function clean(string $path): string
    {
        return str_replace('\\', '/', $path);
    }
}
