<?php
/**
 * @package     DevArt Support
 * @subpackage  com_devartsupport
 *
 * @copyright   Copyright (C) 2026 Stathopoulos Kostas - DevArt. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Devart\Component\Devartsupport\Administrator\View\Ticket;

defined('_JEXEC') or die;

use Devart\Component\Devartsupport\Administrator\Exception\TicketNotFoundException;
use Devart\Component\Devartsupport\Administrator\Helper\AdminAssets;
use Devart\Component\Devartsupport\Administrator\Helper\AttachmentSettings;
use Devart\Component\Devartsupport\Administrator\Helper\TicketDesk;
use Devart\Component\Devartsupport\Administrator\Service\Storage\MessageStorage;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Toolbar\ToolbarHelper;

class HtmlView extends BaseHtmlView
{
    public Form $form;

    public ?object $ticket = null;

    /** @var list<object> */
    public array $messages = [];

    public int $messagesTotal = 0;

    public bool $messagesTruncated = false;

    public int $messagesLimit = MessageStorage::THREAD_PAGE;

    public int $messagesNextLimit = MessageStorage::THREAD_PAGE;

    /** @var list<object> */
    public array $attachments = [];

    /** @var array<int, int> */
    public array $attachmentCategoryFlags = [];

    public bool $attachmentsGloballyEnabled = true;

    public bool $attachmentsUploadAllowed = true;

    public int $attachmentMaxFiles = 5;

    public int $attachmentMaxMegabytes = 8;

    public bool $attachmentsRetentionEnabled = false;

    public int $attachmentsRetentionDays = 365;

    public bool $isNew = true;

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
            'id'              => 0,
            'subject'         => '',
            'status'          => 'open',
            'category_id'     => '',
            'priority'        => 'normal',
            'user_id'         => 0,
            'requester_name'  => '',
            'requester_email' => '',
            'message'         => '',
            'reply'           => '',
        ];

        if (!$this->isNew) {
            $messagesLimit = MessageStorage::normaliseLimit($app->getInput()->getInt('msg_limit', MessageStorage::THREAD_PAGE));

            try {
                $record = TicketDesk::service()->get($id, $messagesLimit);
            } catch (TicketNotFoundException) {
                $app->enqueueMessage(Text::_('COM_DEVARTSUPPORT_ERROR_NOT_FOUND'), 'error');
                $app->redirect(Route::_('index.php?option=com_devartsupport&view=tickets', false));

                return;
            }

            $this->ticket = $record['ticket'];
            $this->messages = $record['messages'];
            $this->attachments = $record['attachments'];
            $this->messagesTotal = (int) $record['messages_total'];
            $this->messagesTruncated = (bool) $record['messages_truncated'];
            $this->messagesLimit = (int) $record['messages_limit'];
            $this->messagesNextLimit = MessageStorage::nextLimit($this->messagesLimit);
            $data['id'] = (int) $this->ticket->id;
            $data['subject'] = (string) $this->ticket->subject;
            $data['status'] = (string) $this->ticket->status;
            $data['category_id'] = (int) $this->ticket->category_id;
            $data['priority'] = (string) ($this->ticket->priority ?? 'normal');
            $data['user_id'] = (int) $this->ticket->user_id;
            $data['requester_name'] = (string) $this->ticket->requester_name;
            $data['requester_email'] = (string) $this->ticket->requester_email;
        }

        $saved = $app->getUserState('com_devartsupport.ticket.data');

        if (is_array($saved)) {
            $data = array_merge($data, $saved);
        }

        // Always bind the live modified stamp so a stale-form retry can succeed
        // after the administrator reloads the current ticket state.
        if (!$this->isNew && $this->ticket !== null) {
            $data['expected_modified'] = (string) ($this->ticket->modified ?? '');
        }

        if ($this->getLayout() === 'default') {
            $this->setLayout('edit');
        }

        Form::addFormPath(JPATH_ADMINISTRATOR . '/components/com_devartsupport/forms');
        $this->form = Form::getInstance('com_devartsupport.ticket', 'ticket', ['control' => 'jform']);
        $this->addCategoryOptions((int) ($data['category_id'] ?? 0));

        if ($this->isNew) {
            $this->form->removeField('reply');
        } else {
            $this->form->removeField('message');
        }

        $this->form->bind($data);
        $this->attachmentsGloballyEnabled = AttachmentSettings::enabled();
        $this->attachmentsUploadAllowed = TicketDesk::service()->attachmentsAllowed((int) ($data['category_id'] ?? 0));
        $this->attachmentCategoryFlags = TicketDesk::service()->categoryAttachmentFlags();
        $this->attachmentMaxFiles = AttachmentSettings::maxFiles();
        $this->attachmentMaxMegabytes = AttachmentSettings::maxMegabytes();
        $this->attachmentsRetentionEnabled = AttachmentSettings::retentionEnabled();
        $this->attachmentsRetentionDays = AttachmentSettings::retentionDays();

        ToolbarHelper::title(
            Text::_($this->isNew ? 'COM_DEVARTSUPPORT_TICKET_NEW' : 'COM_DEVARTSUPPORT_TICKET_EDIT'),
            'comments-2'
        );

        if (($this->isNew && $canCreate) || (!$this->isNew && $canEdit)) {
            ToolbarHelper::apply('ticket.apply');
            ToolbarHelper::save('ticket.save');
        }

        ToolbarHelper::cancel('ticket.cancel');
        AdminAssets::load();
        parent::display($tpl);
    }

    private function addCategoryOptions(int $currentId): void
    {
        $nodes = $this->form->getXml()->xpath('//field[@name="category_id"]');

        if ($nodes === false || $nodes === []) {
            return;
        }

        $field = $nodes[0];

        foreach (TicketDesk::categories()->options() as $category) {
            $id = (int) $category->id;
            $published = (int) $category->published === 1;

            if (!$published && $id !== $currentId) {
                continue;
            }

            $option = $field->addChild('option', htmlspecialchars((string) $category->title, ENT_XML1, 'UTF-8'));
            $option->addAttribute('value', (string) $id);
        }
    }
}
