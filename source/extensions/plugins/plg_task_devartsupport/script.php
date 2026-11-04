<?php
/**
 * @package     DevArt Support
 * @subpackage  plg_task_devartsupport
 *
 * @copyright   Copyright (C) 2026 Stathopoulos Kostas - DevArt. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

declare(strict_types=1);

defined('_JEXEC') or die;

use Joomla\CMS\Date\Date;
use Joomla\CMS\Factory;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\Database\DatabaseInterface;

/**
 * Enable the task plugin on install and seed a daily Scheduled Task once.
 */
final class PlgTaskDevartsupportInstallerScript
{
    private const ROUTINE_ID = 'devartsupport.purge_attachments';

    private const TASK_TITLE = 'DevArt Support – Purge expired attachments';

    private const AUTO_NOTE = 'Created automatically by DevArt Support install/update.';

    private const SEED_MARKER = 'scheduler_seeded_attachment_purge';

    private const SEED_VERSION = '1.0.46';

    public function install(InstallerAdapter $parent): bool
    {
        unset($parent);
        $this->publishPlugin();
        $this->ensureScheduledTask();

        return true;
    }

    public function update(InstallerAdapter $parent): bool
    {
        unset($parent);
        $this->ensureScheduledTask();

        return true;
    }

    public function postflight(string $type, InstallerAdapter $parent): bool
    {
        unset($parent);

        if (in_array($type, ['install', 'discover_install'], true)) {
            $this->publishPlugin();
        }

        $this->ensureScheduledTask();

        return true;
    }

    private function publishPlugin(): void
    {
        try {
            $db = Factory::getContainer()->get(DatabaseInterface::class);
            $db->setQuery(
                $db->getQuery(true)
                    ->update($db->quoteName('#__extensions'))
                    ->set($db->quoteName('enabled') . ' = 1')
                    ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
                    ->where($db->quoteName('folder') . ' = ' . $db->quote('task'))
                    ->where($db->quoteName('element') . ' = ' . $db->quote('devartsupport'))
            )->execute();
        } catch (\Throwable) {
            // Never block installation.
        }
    }

    private function ensureScheduledTask(): void
    {
        try {
            $db = Factory::getContainer()->get(DatabaseInterface::class);

            if (!$this->tableExists($db, '#__scheduler_tasks')) {
                return;
            }

            $routineId = self::ROUTINE_ID;
            $existingId = (int) $db->setQuery(
                $db->getQuery(true)
                    ->select($db->quoteName('id'))
                    ->from($db->quoteName('#__scheduler_tasks'))
                    ->where($db->quoteName('type') . ' = :routineType')
                    ->bind(':routineType', $routineId)
                    ->setLimit(1)
            )->loadResult();

            if ($existingId > 0) {
                $this->setSeedMarker($db);

                return;
            }

            if ($this->hasSeedMarker($db)) {
                return;
            }

            $intervalHours = 24;
            $executionRules = json_encode([
                'rule-type'       => 'interval-hours',
                'interval-hours'  => (string) $intervalHours,
                'exec-day'        => gmdate('d'),
                'exec-time'       => '03:30',
            ], JSON_UNESCAPED_SLASHES);
            $cronRules = json_encode([
                'type' => 'interval',
                'exp'  => 'PT' . $intervalHours . 'H',
            ], JSON_UNESCAPED_SLASHES);
            $params = json_encode([
                'individual_log' => false,
                'log_file'       => '',
                'notifications'  => [
                    'success_mail'        => 0,
                    'failure_mail'        => 0,
                    'fatal_failure_mail'  => 1,
                    'orphan_mail'         => 1,
                ],
            ], JSON_UNESCAPED_SLASHES);

            if ($executionRules === false || $cronRules === false || $params === false) {
                return;
            }

            $now = (new Date('now'))->toSql();
            $insert = $db->getQuery(true)
                ->insert($db->quoteName('#__scheduler_tasks'))
                ->columns($db->quoteName([
                    'asset_id',
                    'title',
                    'type',
                    'execution_rules',
                    'cron_rules',
                    'state',
                    'last_exit_code',
                    'next_execution',
                    'times_executed',
                    'times_failed',
                    'priority',
                    'ordering',
                    'cli_exclusive',
                    'params',
                    'note',
                    'created',
                    'created_by',
                ]))
                ->values(implode(', ', [
                    '0',
                    $db->quote(self::TASK_TITLE),
                    $db->quote($routineId),
                    $db->quote($executionRules),
                    $db->quote($cronRules),
                    '1',
                    '0',
                    $db->quote($now),
                    '0',
                    '0',
                    '0',
                    '0',
                    '0',
                    $db->quote($params),
                    $db->quote(self::AUTO_NOTE),
                    $db->quote($now),
                    '0',
                ]));
            $db->setQuery($insert)->execute();
            $this->setSeedMarker($db);
        } catch (\Throwable) {
            // Never block installation.
        }
    }

    private function hasSeedMarker(DatabaseInterface $db): bool
    {
        $params = $this->readComponentParams($db);

        return trim((string) ($params[self::SEED_MARKER] ?? '')) !== '';
    }

    private function setSeedMarker(DatabaseInterface $db): void
    {
        $params = $this->readComponentParams($db);

        if (trim((string) ($params[self::SEED_MARKER] ?? '')) === self::SEED_VERSION) {
            return;
        }

        $params[self::SEED_MARKER] = self::SEED_VERSION;
        $encoded = json_encode($params, JSON_UNESCAPED_SLASHES);

        if ($encoded === false) {
            return;
        }

        $db->setQuery(
            $db->getQuery(true)
                ->update($db->quoteName('#__extensions'))
                ->set($db->quoteName('params') . ' = ' . $db->quote($encoded))
                ->where($db->quoteName('type') . ' = ' . $db->quote('component'))
                ->where($db->quoteName('element') . ' = ' . $db->quote('com_devartsupport'))
        )->execute();
    }

    /**
     * @return array<string, mixed>
     */
    private function readComponentParams(DatabaseInterface $db): array
    {
        $raw = (string) $db->setQuery(
            $db->getQuery(true)
                ->select($db->quoteName('params'))
                ->from($db->quoteName('#__extensions'))
                ->where($db->quoteName('type') . ' = ' . $db->quote('component'))
                ->where($db->quoteName('element') . ' = ' . $db->quote('com_devartsupport'))
        )->loadResult();

        if ($raw === '' || $raw === '{}') {
            return [];
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }

    private function tableExists(DatabaseInterface $db, string $table): bool
    {
        $table = str_replace('#__', $db->getPrefix(), $table);
        $tables = $db->getTableList();

        return in_array($table, $tables, true);
    }
}
