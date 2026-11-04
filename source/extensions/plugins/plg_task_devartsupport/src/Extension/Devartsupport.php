<?php
/**
 * @package     DevArt Support
 * @subpackage  plg_task_devartsupport
 *
 * @copyright   Copyright (C) 2026 Stathopoulos Kostas - DevArt. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Devart\Plugin\Task\Devartsupport\Extension;

defined('_JEXEC') or die;

use Devart\Component\Devartsupport\Administrator\Helper\AttachmentSettings;
use Devart\Component\Devartsupport\Administrator\Service\Storage\AttachmentStorage;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Component\Scheduler\Administrator\Event\ExecuteTaskEvent;
use Joomla\Component\Scheduler\Administrator\Task\Status;
use Joomla\Component\Scheduler\Administrator\Traits\TaskPluginTrait;
use Joomla\Database\DatabaseInterface;
use Joomla\Event\SubscriberInterface;

/**
 * Scheduled purge of expired private ticket attachments.
 */
final class Devartsupport extends CMSPlugin implements SubscriberInterface
{
    use TaskPluginTrait;

    /** @var array<string, array{langConstPrefix: string, method: string}> */
    private const TASKS_MAP = [
        'devartsupport.purge_attachments' => [
            'langConstPrefix' => 'PLG_TASK_DEVARTSUPPORT_PURGE',
            'method'          => 'purgeExpiredAttachments',
        ],
    ];

    protected $autoloadLanguage = true;

    public static function getSubscribedEvents(): array
    {
        return [
            'onTaskOptionsList'    => 'advertiseRoutines',
            'onExecuteTask'        => 'standardRoutineHandler',
            'onContentPrepareForm' => 'enhanceTaskItemForm',
        ];
    }

    private function purgeExpiredAttachments(ExecuteTaskEvent $event): int
    {
        unset($event);

        if (!class_exists(AttachmentSettings::class) || !class_exists(AttachmentStorage::class)) {
            $this->logTask('DevArt Support attachment purge skipped: component unavailable.');

            return Status::KO;
        }

        if (!AttachmentSettings::retentionEnabled()) {
            $this->logTask('DevArt Support attachment purge skipped: retention disabled.');

            return Status::OK;
        }

        try {
            $days = AttachmentSettings::retentionDays();
            $db = Factory::getContainer()->get(DatabaseInterface::class);
            $removed = (new AttachmentStorage($db))->purgeOlderThanDays($days);
            $this->logTask(sprintf(
                'DevArt Support attachment purge: removed %d attachment(s) older than %d day(s).',
                $removed,
                $days
            ));

            return Status::OK;
        } catch (\Throwable $error) {
            $this->logTask('DevArt Support attachment purge failed: ' . $error->getMessage());

            return Status::KO;
        }
    }
}
