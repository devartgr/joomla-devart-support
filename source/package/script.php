<?php
/**
 * @package     DevArt.Support
 * @subpackage  Package
 *
 * @copyright   Copyright (C) 2026 Stathopoulos Kostas - DevArt. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

declare(strict_types=1);

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

/**
 * Package installer script.
 */
final class Pkg_DevartsupportInstallerScript
{
    /**
     * Keep Joomla's Extensions list in sync after package updates.
     *
     * @param   string  $type    Install/update method.
     * @param   object  $parent  Installer adapter.
     */
    public function postflight(string $type, object $parent): void
    {
        $this->enablePrivacyPlugin();
        $this->enableActionLogPlugin();
        $this->enableTaskPlugin();
        $this->registerActionLogExtension();
        $this->synchroniseInstalledVersions($parent);
    }

    public function uninstall(object $parent): void
    {
        // Preserve ticket tables and private attachment files on package uninstall.
        unset($parent);
    }

    private function enablePrivacyPlugin(): void
    {
        $this->enablePlugin('privacy', 'devartsupport');
    }

    private function enableActionLogPlugin(): void
    {
        $this->enablePlugin('actionlog', 'devartsupport');
    }

    private function enableTaskPlugin(): void
    {
        $this->enablePlugin('task', 'devartsupport');
    }

    private function enablePlugin(string $folder, string $element): void
    {
        try {
            /** @var DatabaseInterface $db */
            $db = Factory::getContainer()->get(DatabaseInterface::class);
            $query = $db->createQuery()
                ->update($db->quoteName('#__extensions'))
                ->set($db->quoteName('enabled') . ' = 1')
                ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
                ->where($db->quoteName('element') . ' = ' . $db->quote($element))
                ->where($db->quoteName('folder') . ' = ' . $db->quote($folder));
            $db->setQuery($query)->execute();
        } catch (\Throwable $e) {
            // Enabling plugins must never block installation or update.
            unset($e);
        }
    }

    private function registerActionLogExtension(): void
    {
        try {
            /** @var DatabaseInterface $db */
            $db = Factory::getContainer()->get(DatabaseInterface::class);
            $extension = 'com_devartsupport';
            $exists = (int) $db->setQuery(
                $db->createQuery()
                    ->select('COUNT(*)')
                    ->from($db->quoteName('#__action_logs_extensions'))
                    ->where($db->quoteName('extension') . ' = :extName')
                    ->bind(':extName', $extension)
            )->loadResult();

            if ($exists > 0) {
                return;
            }

            $row = (object) ['extension' => $extension];
            $db->insertObject('#__action_logs_extensions', $row);
        } catch (\Throwable $e) {
            unset($e);
        }
    }

    /**
     * Update only version / creationDate in manifest_cache.
     *
     * Version must come from the installed package manifest.
     */
    private function synchroniseInstalledVersions(object $parent): void
    {
        $manifest = null;

        if (method_exists($parent, 'getManifest')) {
            $manifest = $parent->getManifest();
        } elseif (isset($parent->manifest)) {
            $manifest = $parent->manifest;
        }

        $version = $manifest !== null ? trim((string) $manifest->version) : '';
        $creationDate = $manifest !== null ? trim((string) $manifest->creationDate) : '';

        if ($version === '') {
            return;
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);

        foreach ([
            ['type' => 'component', 'element' => 'com_devartsupport', 'folder' => '', 'client_id' => 1],
            ['type' => 'package', 'element' => 'pkg_devartsupport', 'folder' => '', 'client_id' => 0],
            ['type' => 'plugin', 'element' => 'devartsupport', 'folder' => 'privacy', 'client_id' => 0],
            ['type' => 'plugin', 'element' => 'devartsupport', 'folder' => 'actionlog', 'client_id' => 0],
            ['type' => 'plugin', 'element' => 'devartsupport', 'folder' => 'task', 'client_id' => 0],
        ] as $extension) {
            try {
                $query = $db->createQuery()
                    ->select([$db->quoteName('extension_id'), $db->quoteName('manifest_cache')])
                    ->from($db->quoteName('#__extensions'))
                    ->where($db->quoteName('type') . ' = :syncType')
                    ->where($db->quoteName('element') . ' = :syncElement')
                    ->where($db->quoteName('client_id') . ' = :syncClientId');
                $query->bind(':syncType', $extension['type']);
                $query->bind(':syncElement', $extension['element']);
                $query->bind(':syncClientId', $extension['client_id'], ParameterType::INTEGER);

                if ($extension['folder'] !== '') {
                    $query->where($db->quoteName('folder') . ' = :syncFolder');
                    $query->bind(':syncFolder', $extension['folder']);
                }

                $db->setQuery($query);
                $row = $db->loadObject();

                if (!$row) {
                    continue;
                }

                $cache = json_decode((string) $row->manifest_cache, true);

                if (!is_array($cache)) {
                    $cache = [];
                }

                $cache['version'] = $version;

                if ($creationDate !== '') {
                    $cache['creationDate'] = $creationDate;
                }

                $update = $db->createQuery()
                    ->update($db->quoteName('#__extensions'))
                    ->set($db->quoteName('manifest_cache') . ' = :manifestCache')
                    ->where($db->quoteName('extension_id') . ' = :extensionId');
                $manifestCache = json_encode($cache, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                $extensionId = (int) $row->extension_id;
                $update->bind(':manifestCache', $manifestCache);
                $update->bind(':extensionId', $extensionId, ParameterType::INTEGER);
                $db->setQuery($update)->execute();
            } catch (\Throwable $e) {
                // Version display must never block installation or update.
                unset($e);
            }
        }
    }
}
