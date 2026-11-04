<?php
/**
 * @package     DevArt Support
 * @subpackage  com_devartsupport
 *
 * @copyright   Copyright (C) 2026 Stathopoulos Kostas - DevArt. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Devart\Component\Devartsupport\Administrator\View\Dashboard;

defined('_JEXEC') or die;

use Devart\Component\Devartsupport\Administrator\Helper\AdminAssets;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

class HtmlView extends BaseHtmlView
{
    public function display($tpl = null): void
    {
        if (!$this->getCurrentUser()->authorise('core.manage', 'com_devartsupport')) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        ToolbarHelper::title(Text::_('COM_DEVARTSUPPORT_DASHBOARD'), 'comments-2');

        if ($this->getCurrentUser()->authorise('core.admin', 'com_devartsupport')) {
            ToolbarHelper::preferences('com_devartsupport');
        }

        AdminAssets::load();
        parent::display($tpl);
    }
}
