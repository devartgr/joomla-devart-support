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
use Devart\Component\Devartsupport\Administrator\Service\TicketMailService;
use Devart\Component\Devartsupport\Administrator\Service\TicketService;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

final class TicketDesk
{
    public static function service(): TicketService
    {
        $component = self::component();

        return $component->getTicketService();
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
            || !method_exists($component, 'getTicketService')
            || !method_exists($component, 'getCategoryService')
            || !method_exists($component, 'getTicketMailService')
        ) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 500);
        }

        return $component;
    }
}
