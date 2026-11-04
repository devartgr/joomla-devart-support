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

use Devart\Component\Devartsupport\Administrator\Helper\PortalFrontSettings;
use Joomla\CMS\Factory;

final class SiteAssets
{
    public static function load(): void
    {
        $app = Factory::getApplication();
        $app->allowCache(false);

        $document = $app->getDocument();
        $document->setMetaData('robots', 'noindex, nofollow');

        $wa = $document->getWebAssetManager();
        $wa->getRegistry()->addExtensionRegistryFile('com_devartsupport');
        $wa->useStyle('com_devartsupport.site');
        $wa->useScript('com_devartsupport.site');
    }

    public static function themeClass(): string
    {
        return 'devart-support-theme-' . PortalFrontSettings::theme();
    }
}
