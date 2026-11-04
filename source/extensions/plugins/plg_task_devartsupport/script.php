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
 * Enable the task plugin on install and seed Scheduled Tasks once.
 */
final class PlgTaskDevartsupportInstallerScript
{
    private const SEED_VERSION = '1.0.0';

    private const AUTO_NOTE = 'Created automatically by DevArt Support install/update.';

    /**
     * @var list<array{
     *     routine: string,
     *     title: string,
     *     marker: string,
     *     ruleType: string,
     *     intervalKey: string,
     *     intervalValue: string,
     *     cronExp: string
     * }>
     */
    private const TASKS = [
        [
            'routine'       => 'devartsupport.purge_attachments',
            'title'         => 'DevArt Support – Purge expired attachments',
            'marker'        => 'scheduler_seeded_attachment_purge',
            'ruleType'      => 'interval-hours',
            'intervalKey'   => 'interval-hours',
            'intervalValue' => '24',
            'cronExp'       => 'PT24H',
        ],
        [
            'routine'       => 'devartsupport.send_mail',
            'title'         => 'DevArt Support – Send queued mail',
            'marker'        => 'scheduler_seeded_mail_queue',
            'ruleType'      => 'interval-minutes',
            'intervalKey'   => 'interval-minutes',
            'intervalValue' => '5',
            'cronExp'       => 'PT5M',
        ],
    ];

    public function install(InstallerAdapter $parent): bool
    {
        unset($parent);
        $this->publishPlugin();
        $this->ensureScheduledTasks();

        return true;
    }

    public function update(InstallerAdapter $parent): bool
    {
        unset($parent);
        $this->ensureScheduledTasks();

        return true;
    }

    public function postflight(string $type, InstallerAdapter $parent): bool
    {
        unset($parent);

        if (in_array($type, ['install', 'discover_install'], true)) {
            $this->publishPlugin();
        }

        $this->ensureScheduledTasks();

        return true;
    }

    private function publishPlugin(): void
    {
        try {
            $db = Factory::getContainer()->get(DatabaseInterface::class);
            $db->setQuery(
                $db->createQuery()
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

    private function ensureScheduledTasks(): void
    {
        try {
            $db = Factory::getContainer()->get(DatabaseInterface::class);

            if (!$this->tableExists($db, '#__scheduler_tasks')) {
                return;
            }

            foreach (self::TASKS as $task) {
                $this->ensureOneTask($db, $task);
            }
        } catch (\Throwable) {
            // Never block installation.
        }
    }

    /**
     * @param array{
     *     routine: string,
     *     title: string,
     *     marker: string,
     *     ruleType: string,
     *     intervalKey: string,
     *     intervalValue: string,
     *     cronExp: string
     * } $task
     */
    private function ensureOneTask(DatabaseInterface $db, array $task): void
    {
        $routineId = $task['routine'];
        $existingId = (int) $db->setQuery(
            $db->createQuery()
                ->select($db->quoteName('id'))
                ->from($db->quoteName('#__scheduler_tasks'))
                ->where($db->quoteName('type') . ' = :routineType')
                ->bind(':routineType', $routineId)
                ->setLimit(1)
        )->loadResult();

        if ($existingId > 0) {
            $this->setSeedMarker($db, $task['marker']);

            return;
        }

        if ($this->hasSeedMarker($db, $task['marker'])) {
            return;
        }

        $executionRules = json_encode([
            'rule-type'         => $task['ruleType'],
            $task['intervalKey'] => $task['intervalValue'],
            'exec-day'          => gmdate('d'),
            'exec-time'         => '03:30',
        ], JSON_UNESCAPED_SLASHES);
        $cronRules = json_encode([
            'type' => 'interval',
            'exp'  => $task['cronExp'],
        ], JSON_UNESCAPED_SLASHES);
        $params = json_encode([
            'individual_log' => false,
            'log_file'       => '',
            'notifications'  => [
                'success_mail'       => 0,
                'failure_mail'       => 0,
                'fatal_failure_mail' => 1,
                'orphan_mail'        => 1,
            ],
        ], JSON_UNESCAPED_SLASHES);

        if ($executionRules === false || $cronRules === false || $params === false) {
            return;
        }

        $now = (new Date('now'))->toSql();
        $insert = $db->createQuery()
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
                $db->quote($task['title']),
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
        $this->setSeedMarker($db, $task['marker']);
    }

    private function hasSeedMarker(DatabaseInterface $db, string $marker): bool
    {
        $params = $this->readComponentParams($db);

        return trim((string) ($params[$marker] ?? '')) !== '';
    }

    private function setSeedMarker(DatabaseInterface $db, string $marker): void
    {
        $params = $this->readComponentParams($db);

        if (trim((string) ($params[$marker] ?? '')) === self::SEED_VERSION) {
            return;
        }

        $params[$marker] = self::SEED_VERSION;
        $encoded = json_encode($params, JSON_UNESCAPED_SLASHES);

        if ($encoded === false) {
            return;
        }

        $db->setQuery(
            $db->createQuery()
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
            $db->createQuery()
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
