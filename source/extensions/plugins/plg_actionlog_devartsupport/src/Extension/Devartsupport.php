<?php
/**
 * @package     DevArt Support
 * @subpackage  plg_actionlog_devartsupport
 *
 * @copyright   Copyright (C) 2026 Stathopoulos Kostas - DevArt. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Devart\Plugin\Actionlog\Devartsupport\Extension;

defined('_JEXEC') or die;

use Joomla\Event\Event;
use Joomla\Event\SubscriberInterface;

/**
 * Writes DevArt Support ticket mutations into Joomla Action Logs.
 */
final class Devartsupport extends \Joomla\Component\Actionlogs\Administrator\Plugin\ActionLogPlugin implements SubscriberInterface
{
    private const CONTEXT = 'com_devartsupport.ticket';

    private const ACTION_KEYS = [
        'created'              => 'PLG_ACTIONLOG_DEVARTSUPPORT_TICKET_CREATED',
        'updated'              => 'PLG_ACTIONLOG_DEVARTSUPPORT_TICKET_UPDATED',
        'replied'              => 'PLG_ACTIONLOG_DEVARTSUPPORT_TICKET_REPLIED',
        'status_changed'       => 'PLG_ACTIONLOG_DEVARTSUPPORT_TICKET_STATUS',
        'closed'               => 'PLG_ACTIONLOG_DEVARTSUPPORT_TICKET_CLOSED',
        'reopened'             => 'PLG_ACTIONLOG_DEVARTSUPPORT_TICKET_REOPENED',
        'priority_changed'     => 'PLG_ACTIONLOG_DEVARTSUPPORT_TICKET_PRIORITY',
        'trashed'              => 'PLG_ACTIONLOG_DEVARTSUPPORT_TICKET_TRASHED',
        'restored'             => 'PLG_ACTIONLOG_DEVARTSUPPORT_TICKET_RESTORED',
        'deleted'              => 'PLG_ACTIONLOG_DEVARTSUPPORT_TICKET_DELETED',
        'attachment_uploaded'  => 'PLG_ACTIONLOG_DEVARTSUPPORT_ATTACHMENT_UPLOADED',
        'attachment_deleted'   => 'PLG_ACTIONLOG_DEVARTSUPPORT_ATTACHMENT_DELETED',
    ];

    public static function getSubscribedEvents(): array
    {
        return [
            'onDevartSupportActionLog' => 'onDevartSupportActionLog',
        ];
    }

    public function onDevartSupportActionLog(Event $event): void
    {
        $action = strtolower(trim((string) $event->getArgument('action', '')));
        $ticketId = (int) $event->getArgument('id', 0);
        $title = trim((string) $event->getArgument('title', ''));
        /** @var array<string, mixed> $extra */
        $extra = $event->getArgument('extra', []);

        if ($action === '' || !isset(self::ACTION_KEYS[$action]) || $ticketId < 1) {
            return;
        }

        if (!is_array($extra)) {
            $extra = [];
        }

        $message = [
            'action'      => $action,
            'type'        => 'PLG_ACTIONLOG_DEVARTSUPPORT_TYPE_TICKET',
            'id'          => $ticketId,
            'title'       => $title !== '' ? $title : ('#' . $ticketId),
            'itemlink'    => 'index.php?option=com_devartsupport&task=ticket.edit&id=' . $ticketId,
            'extension'   => 'COM_DEVARTSUPPORT',
            'status'      => (string) ($extra['status'] ?? ''),
            'priority'    => (string) ($extra['priority'] ?? ''),
            'filename'    => (string) ($extra['filename'] ?? ''),
            'channel'     => (string) ($extra['channel'] ?? ''),
        ];

        $this->addLog([$message], self::ACTION_KEYS[$action], self::CONTEXT);
    }
}
