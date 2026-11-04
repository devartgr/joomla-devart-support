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

use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;

final class AdminChrome
{
    public const IMAGE_DASHBOARD = 'devart-dashboard.jpg';
    public const IMAGE_TICKETS = 'devartsupport-tickets.webp';
    public const IMAGE_CATEGORIES = 'devartsupport-categories.webp';
    public const IMAGE_TOOLS = 'devartsupport-tools.webp';

    public static function header(string $file, string $alt): string
    {
        $src = htmlspecialchars(Uri::root(true) . '/media/com_devartsupport/images/' . $file, ENT_QUOTES, 'UTF-8');
        $label = htmlspecialchars($alt, ENT_QUOTES, 'UTF-8');
        $dashboard = $file === self::IMAGE_DASHBOARD;
        $width = $dashboard ? 1200 : 886;
        $height = $dashboard ? 324 : 200;
        $class = $dashboard
            ? 'devart-support-admin-header devart-support-admin-header--dashboard'
            : 'devart-support-admin-header';

        return '<div class="' . $class . '">'
            . '<img src="' . $src . '" alt="' . $label . '" width="' . $width . '" height="' . $height . '" loading="eager">'
            . '</div>';
    }

    public static function footer(): string
    {
        $label = htmlspecialchars(Text::_('COM_DEVARTSUPPORT_BRAND_FOOTER'), ENT_QUOTES, 'UTF-8');

        return '<div class="devart-support-admin-footer text-center mt-5 mb-3">'
            . '<a href="https://devart.gr" target="_blank" rel="noopener noreferrer">' . $label . '</a>'
            . '</div>';
    }
}
