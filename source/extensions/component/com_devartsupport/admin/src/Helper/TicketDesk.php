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

use Devart\Component\Devartsupport\Administrator\Service\CategoryService;
use Devart\Component\Devartsupport\Administrator\Service\PortalTicketService;
use Devart\Component\Devartsupport\Administrator\Service\Storage\AttachmentStorage;
use Devart\Component\Devartsupport\Administrator\Service\Storage\CategoryStorage;
use Devart\Component\Devartsupport\Administrator\Service\Storage\MailQueueStorage;
use Devart\Component\Devartsupport\Administrator\Service\Storage\MessageStorage;
use Devart\Component\Devartsupport\Administrator\Service\Storage\TicketStorage;
use Devart\Component\Devartsupport\Administrator\Service\TicketMailService;
use Devart\Component\Devartsupport\Administrator\Service\TicketService;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;

final class TicketDesk
{
    public static function service(): TicketService
    {
        $component = self::component();

        if (!method_exists($component, 'getTicketService')) {
            throw new \RuntimeException(Text::_('JERROR_AN_ERROR_HAS_OCCURRED'), 500);
        }

        return $component->getTicketService();
    }

    public static function portal(): PortalTicketService
    {
        $component = self::component();

        if (method_exists($component, 'getPortalTicketService')) {
            try {
                return $component->getPortalTicketService();
            } catch (\Error) {
                // Typed property not initialised (partial upgrade / stale DI wiring).
            }
        }

        return self::buildPortalTicketService();
    }

    public static function categories(): CategoryService
    {
        $component = self::component();

        return $component->getCategoryService();
    }

    public static function mail(): TicketMailService
    {
        $component = self::component();

        return $component->getTicketMailService();
    }

    private static function component(): object
    {
        $component = Factory::getApplication()->bootComponent('com_devartsupport');

        if (
            !is_object($component)
            || !method_exists($component, 'getCategoryService')
            || !method_exists($component, 'getTicketMailService')
        ) {
            throw new \RuntimeException(Text::_('JERROR_AN_ERROR_HAS_OCCURRED'), 500);
        }

        return $component;
    }

    /**
     * Fallback when the booted site Extension lacks portal DI (stale OPcache /
     * partial update). Uses the same wiring as site/services/provider.php.
     *
     * Keep constructor arguments in sync with site/services/provider.php.
     */
    private static function buildPortalTicketService(): PortalTicketService
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $mailQueue = new MailQueueStorage($db);

        return new PortalTicketService(
            new TicketStorage($db),
            new MessageStorage($db),
            new CategoryStorage($db),
            new AttachmentStorage($db),
            new TicketMailService($mailQueue),
            $mailQueue
        );
    }
}
