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

final class AdminAssets
{
    public static function load(): void
    {
        $wa = Factory::getApplication()->getDocument()->getWebAssetManager();
        $wa->getRegistry()->addExtensionRegistryFile('com_devartsupport');
        $wa->useStyle('com_devartsupport.admin');
        $wa->useStyle('com_devartsupport.admin-submenu');
        $wa->useScript('com_devartsupport.admin');
    }
}
