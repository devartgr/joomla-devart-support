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

final class MailQueueSettings
{
    public const MODE_IMMEDIATE = 'immediate';
    public const MODE_QUEUED = 'queued';

    public const DEFAULT_BATCH = 20;
    public const MIN_BATCH = 1;
    public const MAX_BATCH = 100;
    public const DEFAULT_MAX_ATTEMPTS = 5;
    public const MIN_MAX_ATTEMPTS = 1;
    public const MAX_MAX_ATTEMPTS = 10;
    public const SENT_RETENTION_DAYS = 7;

    public static function deliveryMode(): string
    {
        $mode = strtolower(trim((string) ComponentHelper::getParams('com_devartsupport')->get(
            'mail_delivery',
            self::MODE_IMMEDIATE
        )));

        return $mode === self::MODE_QUEUED ? self::MODE_QUEUED : self::MODE_IMMEDIATE;
    }

    public static function useQueue(): bool
    {
        return self::deliveryMode() === self::MODE_QUEUED;
    }

    public static function batchSize(): int
    {
        return self::clamp(
            (int) ComponentHelper::getParams('com_devartsupport')->get('mail_queue_batch', self::DEFAULT_BATCH),
            self::MIN_BATCH,
            self::MAX_BATCH,
            self::DEFAULT_BATCH
        );
    }

    public static function maxAttempts(): int
    {
        return self::clamp(
            (int) ComponentHelper::getParams('com_devartsupport')->get(
                'mail_queue_max_attempts',
                self::DEFAULT_MAX_ATTEMPTS
            ),
            self::MIN_MAX_ATTEMPTS,
            self::MAX_MAX_ATTEMPTS,
            self::DEFAULT_MAX_ATTEMPTS
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
