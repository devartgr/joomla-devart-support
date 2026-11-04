<?php
/**
 * @package     DevArt Support
 * @subpackage  com_devartsupport
 *
 * @copyright   Copyright (C) 2026 Stathopoulos Kostas - DevArt. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

declare(strict_types=1);

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Table\Menu;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

class Com_DevartsupportInstallerScript
{
    /**
     * Joomla reads schema updates from the already-installed path after file
     * copy. Leftover internal 1.0.x scripts must be removed in preflight so
     * parseSchemaUpdates cannot re-run ALTER ADD against a numbering-reset site.
     */
    public function preflight(string $type, InstallerAdapter $parent): bool
    {
        unset($parent);

        if ($type === 'uninstall') {
            return true;
        }

        $this->purgeObsoleteUpdateSqlFiles();

        return true;
    }

    public function postflight(string $type, InstallerAdapter $parent): bool
    {
        if ($type === 'uninstall') {
            return true;
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);
        // Do not purge update SQL here: packaged migrations must remain after
        // parseSchemaUpdates. Leftovers are cleared in preflight before copy.
        $this->ensureCategoriesTable($db);
        $this->ensureCategoryColumns($db);
        $this->ensureTicketColumns($db);
        $this->ensureAttachmentsTable($db);
        $this->ensureSupportIndexes($db);
        $this->normaliseLegacyTicketStatuses($db);
        $this->ensurePrivateAttachmentStorage();
        $this->ensureRegisteredPortalAcl($db);
        $this->ensurePortalMenuItem($db);
        $this->synchroniseSchemaVersion($db, $this->manifestVersion($parent));

        return true;
    }

    /**
     * Remove leftover update scripts from the installed component tree before
     * the new package files are copied. Idempotent schema work runs in postflight.
     */
    private function purgeObsoleteUpdateSqlFiles(): void
    {
        $dir = JPATH_ADMINISTRATOR . '/components/com_devartsupport/sql/updates/mysql';

        if (!is_dir($dir)) {
            return;
        }

        foreach (glob($dir . '/*.sql') ?: [] as $file) {
            @unlink($file);
        }
    }

    /**
     * Create one Registered-access site menu item for the portal when none exists.
     * Never overwrites an existing Support site menu item or blocks install/update.
     */
    private function ensurePortalMenuItem(object $db): void
    {
        try {
            $existingId = (int) $db->setQuery(
                'SELECT ' . $db->quoteName('id')
                . ' FROM ' . $db->quoteName('#__menu')
                . ' WHERE ' . $db->quoteName('client_id') . ' = 0'
                . ' AND ' . $db->quoteName('link') . ' LIKE ' . $db->quote('%option=com_devartsupport%')
                . ' LIMIT 1'
            )->loadResult();

            if ($existingId > 0) {
                return;
            }

            $extensionId = (int) $db->setQuery(
                'SELECT ' . $db->quoteName('extension_id')
                . ' FROM ' . $db->quoteName('#__extensions')
                . ' WHERE ' . $db->quoteName('type') . ' = ' . $db->quote('component')
                . ' AND ' . $db->quoteName('element') . ' = ' . $db->quote('com_devartsupport')
                . ' LIMIT 1'
            )->loadResult();

            if ($extensionId <= 0) {
                return;
            }

            $menuType = (string) $db->setQuery(
                'SELECT ' . $db->quoteName('menutype')
                . ' FROM ' . $db->quoteName('#__menu')
                . ' WHERE ' . $db->quoteName('client_id') . ' = 0'
                . ' AND ' . $db->quoteName('home') . ' = 1'
                . ' ORDER BY ' . $db->quoteName('id') . ' ASC'
                . ' LIMIT 1'
            )->loadResult();

            if ($menuType === '') {
                $menuType = (string) $db->setQuery(
                    'SELECT ' . $db->quoteName('menutype')
                    . ' FROM ' . $db->quoteName('#__menu_types')
                    . ' WHERE ' . $db->quoteName('client_id') . ' = 0'
                    . ' ORDER BY ' . $db->quoteName('id') . ' ASC'
                    . ' LIMIT 1'
                )->loadResult();
            }

            if ($menuType === '') {
                return;
            }

            $title = 'My support tickets';

            try {
                $language = Factory::getApplication()->getLanguage();
                $language->load('com_devartsupport', JPATH_ADMINISTRATOR . '/components/com_devartsupport', null, false, true);
                $translated = Text::_('COM_DEVARTSUPPORT_MENU_TICKETS_TITLE');

                if ($translated !== '' && $translated !== 'COM_DEVARTSUPPORT_MENU_TICKETS_TITLE') {
                    $title = $translated;
                }
            } catch (\Throwable) {
                // Keep English fallback title.
            }

            $alias = 'devart-support-tickets';
            $baseAlias = $alias;
            $n = 2;

            while (
                (int) $db->setQuery(
                    'SELECT ' . $db->quoteName('id')
                    . ' FROM ' . $db->quoteName('#__menu')
                    . ' WHERE ' . $db->quoteName('client_id') . ' = 0'
                    . ' AND ' . $db->quoteName('parent_id') . ' = 1'
                    . ' AND ' . $db->quoteName('alias') . ' = ' . $db->quote($alias)
                    . ' LIMIT 1'
                )->loadResult() > 0
            ) {
                $alias = $baseAlias . '-' . $n;
                $n++;

                if ($n > 50) {
                    return;
                }
            }

            $parentId = 1;
            $table = new Menu($db);
            $table->setLocation($parentId, 'last-child');

            $bound = $table->bind([
                'menutype'     => $menuType,
                'title'        => $title,
                'alias'        => $alias,
                'path'         => $alias,
                'link'         => 'index.php?option=com_devartsupport&view=tickets',
                'type'         => 'component',
                'published'    => 1,
                'parent_id'    => $parentId,
                'level'        => 1,
                'component_id' => $extensionId,
                'access'       => 2,
                'client_id'    => 0,
                'language'     => '*',
                'home'         => 0,
                'params'       => '{}',
                'browserNav'   => 0,
                'template_style_id' => 0,
            ]);

            if (!$bound || !$table->check() || !$table->store()) {
                return;
            }
        } catch (\Throwable) {
            // Menu can still be created manually; never block install/update.
        }
    }

    /**
     * Allow Registered (group 2) to create and reply via the site portal unless
     * an administrator has already configured those actions explicitly.
     */
    private function ensureRegisteredPortalAcl(object $db): void
    {
        try {
            $assetId = (int) $db->setQuery(
                'SELECT ' . $db->quoteName('id')
                . ' FROM ' . $db->quoteName('#__assets')
                . ' WHERE ' . $db->quoteName('name') . ' = ' . $db->quote('com_devartsupport')
            )->loadResult();

            if ($assetId <= 0) {
                return;
            }

            $rulesJson = (string) $db->setQuery(
                'SELECT ' . $db->quoteName('rules')
                . ' FROM ' . $db->quoteName('#__assets')
                . ' WHERE ' . $db->quoteName('id') . ' = ' . $assetId
            )->loadResult();

            $rules = json_decode($rulesJson !== '' ? $rulesJson : '{}', true);

            if (!\is_array($rules)) {
                $rules = [];
            }

            $changed = false;

            if (!\array_key_exists('core.create', $rules)) {
                $rules['core.create'] = ['2' => 1];
                $changed = true;
            }

            if (!\array_key_exists('core.edit', $rules)) {
                $rules['core.edit'] = ['2' => 1];
                $changed = true;
            }

            if (!$changed) {
                return;
            }

            $encoded = json_encode($rules, JSON_UNESCAPED_SLASHES);

            if ($encoded === false) {
                return;
            }

            $db->setQuery(
                'UPDATE ' . $db->quoteName('#__assets')
                . ' SET ' . $db->quoteName('rules') . ' = ' . $db->quote($encoded)
                . ' WHERE ' . $db->quoteName('id') . ' = ' . $assetId
            )->execute();
        } catch (\Throwable) {
            // Permissions can still be set manually in the component Options.
        }
    }

    public function uninstall(InstallerAdapter $parent): bool
    {
        // Intentional: ticket tables and private attachment files are preserved
        // so support history is not destroyed by a mistaken uninstall. Manual
        // database/file cleanup remains an administrator responsibility.
        unset($parent);

        return true;
    }

    private function ensureCategoriesTable(object $db): void
    {
        $db->setQuery(
            'CREATE TABLE IF NOT EXISTS ' . $db->quoteName('#__devartsupport_categories') . ' ('
            . $db->quoteName('id') . ' int unsigned NOT NULL AUTO_INCREMENT,'
            . $db->quoteName('title') . ' varchar(255) NOT NULL DEFAULT \'\','
            . $db->quoteName('published') . ' tinyint unsigned NOT NULL DEFAULT 1,'
            . $db->quoteName('attachments_enabled') . ' tinyint unsigned NOT NULL DEFAULT 1,'
            . $db->quoteName('ordering') . ' int NOT NULL DEFAULT 0,'
            . 'PRIMARY KEY (' . $db->quoteName('id') . '),'
            . 'KEY ' . $db->quoteName('idx_published_ordering') . ' ('
            . $db->quoteName('published') . ',' . $db->quoteName('ordering') . ')'
            . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci'
        )->execute();
    }

    private function ensureCategoryColumns(object $db): void
    {
        try {
            $columns = $db->getTableColumns('#__devartsupport_categories') ?: [];

            if (isset($columns['attachments_enabled'])) {
                return;
            }

            $db->setQuery(
                'ALTER TABLE ' . $db->quoteName('#__devartsupport_categories')
                . ' ADD COLUMN ' . $db->quoteName('attachments_enabled')
                . ' tinyint unsigned NOT NULL DEFAULT 1 AFTER ' . $db->quoteName('published')
            )->execute();
        } catch (\Throwable) {
            // Column may already exist on numbering-reset upgrades.
        }
    }

    private function ensureTicketColumns(object $db): void
    {
        try {
            $columns = $db->getTableColumns('#__devartsupport_tickets') ?: [];

            if (!isset($columns['category_id'])) {
                $db->setQuery(
                    'ALTER TABLE ' . $db->quoteName('#__devartsupport_tickets')
                    . ' ADD COLUMN ' . $db->quoteName('category_id')
                    . ' int unsigned NOT NULL DEFAULT 0 AFTER ' . $db->quoteName('status')
                )->execute();
            }

            $columns = $db->getTableColumns('#__devartsupport_tickets') ?: [];

            if (!isset($columns['priority'])) {
                $db->setQuery(
                    'ALTER TABLE ' . $db->quoteName('#__devartsupport_tickets')
                    . ' ADD COLUMN ' . $db->quoteName('priority')
                    . ' varchar(16) NOT NULL DEFAULT \'normal\' AFTER ' . $db->quoteName('category_id')
                )->execute();
            }

            $columns = $db->getTableColumns('#__devartsupport_tickets') ?: [];

            if (!isset($columns['published'])) {
                $after = isset($columns['status']) ? 'status' : 'subject';
                $db->setQuery(
                    'ALTER TABLE ' . $db->quoteName('#__devartsupport_tickets')
                    . ' ADD COLUMN ' . $db->quoteName('published')
                    . ' tinyint NOT NULL DEFAULT 1 AFTER ' . $db->quoteName($after)
                )->execute();
            }

            $this->ensureTicketIndex($db, 'idx_category_id', 'category_id');
            $this->ensureTicketIndex($db, 'idx_priority', 'priority');
            $this->ensureTicketIndex($db, 'idx_published', 'published');
            $this->migrateLegacyPendingStatus($db);
        } catch (\Throwable) {
            // Column/index may already exist on numbering-reset upgrades.
        }
    }

    /**
     * Pending was removed; map leftover rows to open.
     */
    private function migrateLegacyPendingStatus(object $db): void
    {
        $this->normaliseLegacyTicketStatuses($db);
    }

    /**
     * Only open/closed remain; map any other legacy status values to open.
     */
    private function normaliseLegacyTicketStatuses(object $db): void
    {
        try {
            $columns = $db->getTableColumns('#__devartsupport_tickets') ?: [];

            if (!isset($columns['status'])) {
                return;
            }

            $open = 'open';
            $closed = 'closed';
            $query = $db->createQuery()
                ->update($db->quoteName('#__devartsupport_tickets'))
                ->set($db->quoteName('status') . ' = :openStatus')
                ->where($db->quoteName('status') . ' NOT IN (' . $db->quote($open) . ', ' . $db->quote($closed) . ')');
            $query->bind(':openStatus', $open, ParameterType::STRING);
            $db->setQuery($query)->execute();
        } catch (\Throwable) {
            // Safe to continue; rows can be corrected manually if needed.
        }
    }

    private function ensureTicketIndex(object $db, string $index, string $column): void
    {
        $this->ensureTableIndex($db, '#__devartsupport_tickets', $index, $column);
    }

    private function ensureSupportIndexes(object $db): void
    {
        try {
            $this->ensureTableIndex($db, '#__devartsupport_messages', 'idx_user_id', 'user_id');
            $this->ensureTableIndex($db, '#__devartsupport_attachments', 'idx_created', 'created');
            $this->ensureTableIndex($db, '#__devartsupport_attachments', 'idx_created_by', 'created_by');
        } catch (\Throwable) {
            // Index creation must never block install/update.
        }
    }

    private function ensureTableIndex(object $db, string $tableKey, string $index, string $column): void
    {
        $table = $db->replacePrefix($tableKey);
        $db->setQuery('SHOW INDEX FROM ' . $db->quoteName($table));

        foreach ($db->loadObjectList() ?: [] as $row) {
            if ((string) ($row->Key_name ?? '') === $index) {
                return;
            }
        }

        $db->setQuery(
            'ALTER TABLE ' . $db->quoteName($tableKey)
            . ' ADD KEY ' . $db->quoteName($index) . ' (' . $db->quoteName($column) . ')'
        )->execute();
    }

    private function ensureAttachmentsTable(object $db): void
    {
        $db->setQuery(
            'CREATE TABLE IF NOT EXISTS ' . $db->quoteName('#__devartsupport_attachments') . ' ('
            . $db->quoteName('id') . ' int unsigned NOT NULL AUTO_INCREMENT,'
            . $db->quoteName('ticket_id') . ' int unsigned NOT NULL,'
            . $db->quoteName('filename') . ' varchar(255) NOT NULL DEFAULT \'\','
            . $db->quoteName('stored_name') . ' varchar(80) NOT NULL DEFAULT \'\','
            . $db->quoteName('mime') . ' varchar(128) NOT NULL DEFAULT \'application/octet-stream\','
            . $db->quoteName('size') . ' int unsigned NOT NULL DEFAULT 0,'
            . $db->quoteName('created') . ' datetime NOT NULL,'
            . $db->quoteName('created_by') . ' int unsigned NOT NULL DEFAULT 0,'
            . 'PRIMARY KEY (' . $db->quoteName('id') . '),'
            . 'UNIQUE KEY ' . $db->quoteName('idx_stored_name') . ' (' . $db->quoteName('stored_name') . '),'
            . 'KEY ' . $db->quoteName('idx_ticket_id') . ' (' . $db->quoteName('ticket_id') . '),'
            . 'KEY ' . $db->quoteName('idx_created') . ' (' . $db->quoteName('created') . '),'
            . 'KEY ' . $db->quoteName('idx_created_by') . ' (' . $db->quoteName('created_by') . ')'
            . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci'
        )->execute();
    }

    private function ensurePrivateAttachmentStorage(): void
    {
        $helper = JPATH_ADMINISTRATOR . '/components/com_devartsupport/src/Helper/PrivateAttachmentPath.php';

        if (is_file($helper)) {
            require_once $helper;

            try {
                \Devart\Component\Devartsupport\Administrator\Helper\PrivateAttachmentPath::ensureDirectory();
                \Devart\Component\Devartsupport\Administrator\Helper\PrivateAttachmentPath::migrateLegacyFiles();

                return;
            } catch (\Throwable $e) {
                // Fall back to native PHP below.
            }
        }

        $directory = str_replace('\\', '/', dirname(JPATH_ROOT) . '/private/com_devartsupport');
        $root = rtrim(str_replace('\\', '/', (string) JPATH_ROOT), '/');

        if ($directory === $root || str_starts_with($directory . '/', $root . '/')) {
            return;
        }

        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            return;
        }

        if (!is_file($directory . '/.htaccess')) {
            file_put_contents(
                $directory . '/.htaccess',
                "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n"
                . "<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n"
            );
        }

        if (!is_file($directory . '/index.html')) {
            file_put_contents($directory . '/index.html', '');
        }

        $legacy = str_replace('\\', '/', JPATH_ADMINISTRATOR . '/components/com_devartsupport/files');

        if (!is_dir($legacy)) {
            return;
        }

        foreach (scandir($legacy) ?: [] as $file) {
            if ($file === '.' || $file === '..' || $file === '.htaccess' || $file === 'index.html') {
                continue;
            }

            if (!preg_match('/^[a-f0-9]{32}\.[a-z0-9]{2,5}$/', $file)) {
                continue;
            }

            $from = $legacy . '/' . $file;
            $to = $directory . '/' . $file;

            if (!is_file($from)) {
                continue;
            }

            if (is_file($to)) {
                @unlink($from);
                continue;
            }

            if (!@rename($from, $to) && @copy($from, $to)) {
                @unlink($from);
            }
        }
    }

    private function manifestVersion(InstallerAdapter $parent): string
    {
        $manifest = $parent->getManifest();
        $version = trim((string) ($manifest->version ?? ''));

        return $version !== '' ? $version : '0.0.0';
    }

    private function synchroniseSchemaVersion(object $db, string $version): void
    {
        $query = $db->createQuery()
            ->select($db->quoteName('extension_id'))
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('type') . ' = ' . $db->quote('component'))
            ->where($db->quoteName('element') . ' = ' . $db->quote('com_devartsupport'));
        $db->setQuery($query);
        $extensionId = (int) $db->loadResult();

        if ($extensionId < 1) {
            return;
        }

        $currentQuery = $db->createQuery()
            ->select($db->quoteName('version_id'))
            ->from($db->quoteName('#__schemas'))
            ->where($db->quoteName('extension_id') . ' = :schemaExtension');
        $currentQuery->bind(':schemaExtension', $extensionId, ParameterType::INTEGER);
        $db->setQuery($currentQuery);
        $current = trim((string) $db->loadResult());

        if ($current === $version) {
            return;
        }

        // Always align #__schemas to the installed manifest version (supports
        // numbering reset from internal 1.0.x builds back to 1.0.0).

        $delete = $db->createQuery()
            ->delete($db->quoteName('#__schemas'))
            ->where($db->quoteName('extension_id') . ' = :deleteSchema');
        $delete->bind(':deleteSchema', $extensionId, ParameterType::INTEGER);
        $db->setQuery($delete)->execute();

        $insert = $db->createQuery()
            ->insert($db->quoteName('#__schemas'))
            ->columns($db->quoteName(['extension_id', 'version_id']))
            ->values(':insertExtension, :insertVersion');
        $insert->bind(':insertExtension', $extensionId, ParameterType::INTEGER);
        $insert->bind(':insertVersion', $version);
        $db->setQuery($insert)->execute();
    }
}
