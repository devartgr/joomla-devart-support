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

use Joomla\CMS\Component\ComponentHelper;

/**
 * Portal create/reply hourly caps from component Options.
 */
final class PortalRateLimits
{
    public const DEFAULT_CREATE_HOURLY = 10;
    public const DEFAULT_REPLY_HOURLY = 30;
    public const MIN_CREATE_HOURLY = 1;
    public const MAX_CREATE_HOURLY = 100;
    public const MIN_REPLY_HOURLY = 1;
    public const MAX_REPLY_HOURLY = 300;

    public static function createHourlyCap(): int
    {
        return self::clamp(
            (int) ComponentHelper::getParams('com_devartsupport')->get(
                'portal_create_hourly_cap',
                self::DEFAULT_CREATE_HOURLY
            ),
            self::MIN_CREATE_HOURLY,
            self::MAX_CREATE_HOURLY,
            self::DEFAULT_CREATE_HOURLY
        );
    }

    public static function replyHourlyCap(): int
    {
        return self::clamp(
            (int) ComponentHelper::getParams('com_devartsupport')->get(
                'portal_reply_hourly_cap',
                self::DEFAULT_REPLY_HOURLY
            ),
            self::MIN_REPLY_HOURLY,
            self::MAX_REPLY_HOURLY,
            self::DEFAULT_REPLY_HOURLY
        );
    }

    private static function clamp(int $value, int $min, int $max, int $fallback): int
    {
        if ($value < $min || $value > $max) {
            return $fallback;
        }

        return $value;
    }
}
