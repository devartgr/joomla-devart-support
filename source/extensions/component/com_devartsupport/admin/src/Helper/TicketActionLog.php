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

use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Event\Event;

/**
 * Dispatches ticket mutation events for plg_actionlog_devartsupport.
 */
final class TicketActionLog
{
    /**
     * @param array<string, scalar|null> $extra
     */
    public static function log(string $action, int $ticketId, string $title = '', array $extra = []): void
    {
        $action = strtolower(trim($action));
        $ticketId = max(0, $ticketId);

        if ($action === '' || $ticketId < 1) {
            return;
        }

        try {
            if (!PluginHelper::isEnabled('actionlog', 'devartsupport')) {
                return;
            }

            PluginHelper::importPlugin('actionlog');
            Factory::getApplication()->getDispatcher()->dispatch(
                'onDevartSupportActionLog',
                new Event('onDevartSupportActionLog', [
                    'action' => $action,
                    'id'     => $ticketId,
                    'title'  => $title,
                    'extra'  => $extra,
                ])
            );
        } catch (\Throwable) {
            // Action logging must never break ticket operations.
        }
    }
}
