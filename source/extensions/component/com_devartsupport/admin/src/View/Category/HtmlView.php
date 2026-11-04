<?php
/**
 * @package     DevArt Support
 * @subpackage  com_devartsupport
 *
 * @copyright   Copyright (C) 2026 Stathopoulos Kostas - DevArt. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Devart\Component\Devartsupport\Administrator\View\Category;

defined('_JEXEC') or die;

use Devart\Component\Devartsupport\Administrator\Exception\CategoryNotFoundException;
use Devart\Component\Devartsupport\Administrator\Helper\AdminAssets;
use Devart\Component\Devartsupport\Administrator\Helper\AttachmentSettings;
use Devart\Component\Devartsupport\Administrator\Helper\TicketDesk;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Toolbar\ToolbarHelper;

class HtmlView extends BaseHtmlView
{
    public Form $form;

    public bool $isNew = true;

    public bool $attachmentsGloballyEnabled = true;

    public function display($tpl = null): void
    {
        $app = Factory::getApplication();
        $id = $app->getInput()->getInt('id');
        $this->isNew = $id < 1;
        $canCreate = $this->getCurrentUser()->authorise('core.create', 'com_devartsupport');
        $canEdit = $this->getCurrentUser()->authorise('core.edit', 'com_devartsupport');

        if ($this->isNew && !$canCreate) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        if (!$this->isNew && !$canEdit && !$this->getCurrentUser()->authorise('core.manage', 'com_devartsupport')) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $data = [
            'id'                  => 0,
            'title'               => '',
            'published'           => 1,
            'attachments_enabled' => 1,
        ];

        if (!$this->isNew) {
            try {
                $category = TicketDesk::categories()->get($id);
            } catch (CategoryNotFoundException) {
                $app->enqueueMessage(Text::_('COM_DEVARTSUPPORT_ERROR_CATEGORY_NOT_FOUND'), 'error');
                $app->redirect(Route::_('index.php?option=com_devartsupport&view=categories', false));

                return;
            }

            $data['id'] = (int) $category->id;
            $data['title'] = (string) $category->title;
            $data['published'] = (int) $category->published;
            $data['attachments_enabled'] = (int) ($category->attachments_enabled ?? 1);
        }

        $saved = $app->getUserState('com_devartsupport.category.data');

        if (is_array($saved)) {
            $data = array_merge($data, $saved);
        }

        if ($this->getLayout() === 'default') {
            $this->setLayout('edit');
        }

        Form::addFormPath(JPATH_ADMINISTRATOR . '/components/com_devartsupport/forms');
        $this->form = Form::getInstance('com_devartsupport.category', 'category', ['control' => 'jform']);
        $this->attachmentsGloballyEnabled = AttachmentSettings::enabled();

        if (!$this->attachmentsGloballyEnabled) {
            $this->form->removeField('attachments_enabled');
        }

        $this->form->bind($data);

        ToolbarHelper::title(
            Text::_($this->isNew ? 'COM_DEVARTSUPPORT_CATEGORY_NEW' : 'COM_DEVARTSUPPORT_CATEGORY_EDIT'),
            'folder'
        );

        if (($this->isNew && $canCreate) || (!$this->isNew && $canEdit)) {
            ToolbarHelper::apply('category.apply');
            ToolbarHelper::save('category.save');
        }

        ToolbarHelper::cancel('category.cancel');
        AdminAssets::load();
        parent::display($tpl);
    }
}
