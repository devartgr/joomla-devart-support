<?php
/**
 * @package     DevArt Support
 * @subpackage  com_devartsupport
 *
 * @copyright   Copyright (C) 2026 Stathopoulos Kostas - DevArt. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Devart\Component\Devartsupport\Administrator\View\Settings;

defined('_JEXEC') or die;

use Devart\Component\Devartsupport\Administrator\Helper\AdminAssets;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

class HtmlView extends BaseHtmlView
{
    public Form $form;

    public function display($tpl = null): void
    {
        if (!$this->getCurrentUser()->authorise('core.admin', 'com_devartsupport')) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        /** @var Form|false $form */
        $form = $this->get('Form');

        if ($form === false) {
            throw new \RuntimeException(Text::_('JERROR_AN_ERROR_HAS_OCCURRED'), 500);
        }

        $this->form = $form;

        ToolbarHelper::title(Text::_('COM_DEVARTSUPPORT_SETTINGS'), 'cog');
        ToolbarHelper::apply('settings.apply');
        ToolbarHelper::save('settings.save');
        ToolbarHelper::custom('settings.testmail', 'envelope', 'envelope', 'COM_DEVARTSUPPORT_SETTINGS_TEST_MAIL', false);
        ToolbarHelper::cancel('settings.cancel', 'JTOOLBAR_CLOSE');

        AdminAssets::load();
        parent::display($tpl);
    }
}
