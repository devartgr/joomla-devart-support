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
 * Canonical private attachment directory outside every public web root.
 *
 * Preferred location is dirname(JPATH_ROOT)/private/com_devartsupport.
 * When Joomla lives in a subdirectory of DOCUMENT_ROOT (multi-demo hosts),
 * that preferred path can still be web-reachable; we then store beside the
 * outermost conflicting web root instead.
 */
final class PrivateAttachmentPath
{
    public static function directory(): string
    {
        $directory = self::resolveDirectory();
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
        self::migrateFromRelocatedPaths($directory);
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
        $moved = self::moveAttachmentFiles($legacy, $target);

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

    private static function resolveDirectory(): string
    {
        $preferred = self::clean(dirname(JPATH_ROOT) . '/private/com_devartsupport');

        if (!self::isInsideAnyWebRoot($preferred)) {
            return $preferred;
        }

        foreach (self::webRoots() as $root) {
            if (!self::isInside($preferred, $root)) {
                continue;
            }

            $candidate = self::clean(dirname($root) . '/private/com_devartsupport');

            if ($candidate !== '' && $candidate !== '/' && !self::isInsideAnyWebRoot($candidate)) {
                return $candidate;
            }
        }

        throw new \RuntimeException('Attachment storage must remain outside the Joomla web root.');
    }

    /**
     * Older builds always used dirname(JPATH_ROOT)/private/... even when that
     * folder was still inside DOCUMENT_ROOT. Move those files to the safe path.
     */
    private static function migrateFromRelocatedPaths(string $target): void
    {
        $target = rtrim(self::clean($target), '/');
        $previous = rtrim(self::clean(dirname(JPATH_ROOT) . '/private/com_devartsupport'), '/');

        if ($previous === $target || !is_dir($previous)) {
            return;
        }

        self::moveAttachmentFiles($previous, $target);

        if (self::legacyHasNoAttachments($previous)) {
            self::deleteDirectoryTree($previous);
        } else {
            self::writeDenyFiles($previous);
        }
    }

    private static function moveAttachmentFiles(string $fromDirectory, string $toDirectory): int
    {
        $fromDirectory = self::clean($fromDirectory);
        $toDirectory = self::clean($toDirectory);
        $moved = 0;

        if (!is_dir($fromDirectory) || $fromDirectory === $toDirectory) {
            return 0;
        }

        if (!is_dir($toDirectory) && !mkdir($toDirectory, 0755, true) && !is_dir($toDirectory)) {
            return 0;
        }

        foreach (scandir($fromDirectory) ?: [] as $file) {
            if ($file === '.' || $file === '..' || $file === '.htaccess' || $file === 'index.html') {
                continue;
            }

            if (!preg_match('/^[a-f0-9]{32}\.[a-z0-9]{2,5}$/', $file)) {
                continue;
            }

            $from = self::clean($fromDirectory . '/' . $file);
            $to = self::clean($toDirectory . '/' . $file);

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

        return $moved;
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
        if (self::isInsideAnyWebRoot($directory)) {
            throw new \RuntimeException('Attachment storage must remain outside the Joomla web root.');
        }

        $legacy = rtrim(self::clean(self::legacyDirectory()), '/');
        $resolved = rtrim(self::clean(realpath($directory) ?: $directory), '/');

        if ($resolved === $legacy || str_starts_with($resolved . '/', $legacy . '/')) {
            throw new \RuntimeException('Attachment storage must remain outside the Joomla web root.');
        }
    }

    private static function isInsideAnyWebRoot(string $directory): bool
    {
        foreach (self::webRoots() as $root) {
            if (self::isInside($directory, $root)) {
                return true;
            }
        }

        return false;
    }

    private static function isInside(string $directory, string $root): bool
    {
        $directory = rtrim(self::clean(realpath($directory) ?: $directory), '/');
        $root = rtrim(self::clean(realpath($root) ?: $root), '/');

        if ($directory === '' || $root === '') {
            return false;
        }

        return $directory === $root || str_starts_with($directory . '/', $root . '/');
    }

    /**
     * @return list<string>
     */
    private static function webRoots(): array
    {
        $candidates = [
            JPATH_ROOT,
            defined('JPATH_PUBLIC') ? (string) JPATH_PUBLIC : null,
        ];

        if (isset($_SERVER['DOCUMENT_ROOT']) && is_string($_SERVER['DOCUMENT_ROOT']) && $_SERVER['DOCUMENT_ROOT'] !== '') {
            $documentRoot = self::clean($_SERVER['DOCUMENT_ROOT']);
            // Herd/Valet may set DOCUMENT_ROOT to "/"; ignore useless roots.
            if ($documentRoot !== '/' && strlen($documentRoot) > 1) {
                $resolvedDocumentRoot = realpath($documentRoot) ?: $documentRoot;
                $siteRoot = realpath(JPATH_ROOT) ?: self::clean(JPATH_ROOT);

                // Only treat DOCUMENT_ROOT as a web root when it contains this site
                // (subdirectory installs / multi-demo hosts). Unrelated values are ignored.
                if (self::isInside($siteRoot, $resolvedDocumentRoot) || self::samePath($siteRoot, $resolvedDocumentRoot)) {
                    $candidates[] = $resolvedDocumentRoot;
                }
            }
        }

        $roots = [];

        foreach ($candidates as $candidate) {
            if (!is_string($candidate) || $candidate === '') {
                continue;
            }

            $root = rtrim(self::clean(realpath($candidate) ?: $candidate), '/');

            if ($root === '') {
                continue;
            }

            $roots[$root] = $root;
        }

        // Widest roots first so relocation climbs out of the outermost public tree.
        $list = array_values($roots);
        usort($list, static fn (string $a, string $b): int => strlen($a) <=> strlen($b));

        return $list;
    }

    private static function samePath(string $left, string $right): bool
    {
        return rtrim(self::clean($left), '/') === rtrim(self::clean($right), '/');
    }

    private static function clean(string $path): string
    {
        return str_replace('\\', '/', $path);
    }
}
