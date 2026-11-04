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
use Joomla\CMS\Language\Text;
use Joomla\CMS\User\UserFactoryInterface;

/**
 * Safe display names when a referenced Joomla user no longer exists.
 */
final class UserDisplay
{
    /** @var array<int, string> */
    private static array $names = [];

    public static function messageAuthor(int $userId): string
    {
        if ($userId < 1) {
            return Text::_('COM_DEVARTSUPPORT_STAFF');
        }

        if (isset(self::$names[$userId])) {
            return self::$names[$userId];
        }

        $name = self::loadName($userId);
        self::$names[$userId] = $name !== '' ? $name : Text::_('COM_DEVARTSUPPORT_DELETED_USER');

        return self::$names[$userId];
    }

    private static function loadName(int $userId): string
    {
        try {
            $user = Factory::getContainer()->get(UserFactoryInterface::class)->loadUserById($userId);

            if ((int) $user->id !== $userId || $user->guest) {
                return '';
            }

            return trim((string) $user->name);
        } catch (\Throwable) {
            return '';
        }
    }
}
