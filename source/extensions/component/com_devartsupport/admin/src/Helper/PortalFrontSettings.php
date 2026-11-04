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
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

final class PortalFrontSettings
{
    private const THEMES = ['red', 'orange', 'blue', 'green', 'yellow', 'gray', 'dark'];

    private const DATE_FORMATS = [
        'DATE_FORMAT_LC2',
        'DATE_FORMAT_LC4',
        'd/m/Y H:i',
        'Y-m-d H:i',
        'd.m.Y H:i',
    ];

    public static function theme(): string
    {
        $theme = strtolower(trim((string) ComponentHelper::getParams('com_devartsupport')->get('front_theme', 'green')));

        return in_array($theme, self::THEMES, true) ? $theme : 'green';
    }

    public static function dateFormat(): string
    {
        $format = trim((string) ComponentHelper::getParams('com_devartsupport')->get('front_date_format', 'DATE_FORMAT_LC2'));

        return in_array($format, self::DATE_FORMATS, true) ? $format : 'DATE_FORMAT_LC2';
    }

    public static function formatDate(string $datetime): string
    {
        $format = self::dateFormat();

        if (str_starts_with($format, 'DATE_FORMAT_')) {
            $format = Text::_($format);
        }

        return HTMLHelper::_('date', $datetime, $format);
    }
}
