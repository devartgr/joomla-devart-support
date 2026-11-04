<?php
/**
 * @package     DevArt Support
 * @subpackage  com_devartsupport
 *
 * @copyright   Copyright (C) 2026 Stathopoulos Kostas - DevArt. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Devart\Component\Devartsupport\Site\Helper;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;

/**
 * Builds site portal URLs that keep the component menu Itemid so SEF links
 * do not fall back to the home page.
 */
final class PortalRoute
{
    private static ?int $cachedItemId = null;

    public static function _(string $url): string
    {
        $itemId = self::itemId();

        if ($itemId > 0 && !preg_match('/[?&]Itemid=/', $url)) {
            $url .= (str_contains($url, '?') ? '&' : '?') . 'Itemid=' . $itemId;
        }

        return Route::_($url, false);
    }

    /**
     * Absolute site URL for a ticket (usable from administrator mail dispatch).
     */
    public static function absoluteTicket(int $ticketId): string
    {
        $ticketId = max(0, $ticketId);
        $url = 'index.php?option=com_devartsupport&view=ticket&id=' . $ticketId;
        $itemId = self::itemId();

        if ($itemId > 0) {
            $url .= '&Itemid=' . $itemId;
        }

        try {
            return Route::link('site', $url, false, Route::TLS_IGNORE, true);
        } catch (\Throwable) {
            return $url;
        }
    }

    public static function itemId(): int
    {
        if (self::$cachedItemId !== null) {
            return self::$cachedItemId;
        }

        $app = Factory::getApplication();

        try {
            if ($app->isClient('site')) {
                $menu = $app->getMenu();
            } else {
                $menu = $app->getMenu('site');
                $menu->load();
            }
        } catch (\Throwable) {
            self::$cachedItemId = 0;

            return 0;
        }

        $active = $menu->getActive();

        if ($active !== null && ($active->component ?? '') === 'com_devartsupport') {
            self::$cachedItemId = (int) $active->id;

            return self::$cachedItemId;
        }

        $items = $menu->getItems('component', 'com_devartsupport') ?: [];
        $fallback = 0;

        foreach ($items as $item) {
            $view = (string) ($item->query['view'] ?? '');

            if ($view === 'tickets') {
                self::$cachedItemId = (int) $item->id;

                return self::$cachedItemId;
            }

            if ($fallback < 1) {
                $fallback = (int) $item->id;
            }
        }

        self::$cachedItemId = $fallback;

        return self::$cachedItemId;
    }
}
