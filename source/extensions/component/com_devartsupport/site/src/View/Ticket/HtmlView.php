<?php
/**
 * @package     DevArt Support
 * @subpackage  com_devartsupport
 *
 * @copyright   Copyright (C) 2026 Stathopoulos Kostas - DevArt. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Devart\Component\Devartsupport\Site\View\Ticket;

defined('_JEXEC') or die;

use Devart\Component\Devartsupport\Administrator\Exception\TicketNotFoundException;
use Devart\Component\Devartsupport\Administrator\Helper\AttachmentSettings;
use Devart\Component\Devartsupport\Administrator\Helper\TicketDesk;
use Devart\Component\Devartsupport\Administrator\Service\Storage\MessageStorage;
use Devart\Component\Devartsupport\Site\Helper\PortalAccess;
use Devart\Component\Devartsupport\Site\Helper\PortalRoute;
use Devart\Component\Devartsupport\Site\Helper\SiteAssets;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;

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

    public bool $isNew = true;

    public bool $canReply = false;

    public bool $canManage = false;

    public string $categoryTitle = '';

    /** @var list<object> */
    public array $attachments = [];

    public bool $attachmentsUploadAllowed = false;

    public int $attachmentMaxFiles = AttachmentSettings::DEFAULT_MAX_FILES;

    public int $attachmentMaxMegabytes = AttachmentSettings::DEFAULT_MAX_MEGABYTES;

    public string $attachmentAccept = '';

    public function display($tpl = null): void
    {
        $app = Factory::getApplication();
        $id = $app->getInput()->getInt('id');
        $layout = $this->getLayout();
        $this->isNew = $id < 1 || $layout === 'edit';

        $this->attachmentMaxFiles = AttachmentSettings::maxFiles();
        $this->attachmentMaxMegabytes = AttachmentSettings::maxMegabytes();
        $this->attachmentAccept = AttachmentSettings::acceptAttribute();

        if ($this->isNew) {
            $user = PortalAccess::requirePermission('core.create');
            $this->isNew = true;
            $this->setLayout('edit');
            $this->prepareCreateForm($user->id);
            $this->attachmentsUploadAllowed = AttachmentSettings::frontendEnabled();
            $this->document->setTitle(Text::_('COM_DEVARTSUPPORT_PORTAL_TICKET_NEW_TITLE'));
            SiteAssets::load();
            parent::display($tpl);

            return;
        }

        $user = PortalAccess::requirePortal();
        $this->canManage = $user->authorise('core.edit', 'com_devartsupport');
        $messagesLimit = MessageStorage::normaliseLimit($app->getInput()->getInt('msg_limit', MessageStorage::THREAD_PAGE));

        try {
            $record = TicketDesk::service()->getForOwner($id, (int) $user->id, $messagesLimit);
        } catch (TicketNotFoundException) {
            $app->enqueueMessage(Text::_('COM_DEVARTSUPPORT_ERROR_NOT_FOUND'), 'error');
            $app->redirect(PortalRoute::_('index.php?option=com_devartsupport&view=tickets'));

            return;
        }

        $this->ticket = $record['ticket'];
        $this->messages = $record['messages'];
        $this->messagesTotal = (int) $record['messages_total'];
        $this->messagesTruncated = (bool) $record['messages_truncated'];
        $this->messagesLimit = (int) $record['messages_limit'];
        $this->messagesNextLimit = MessageStorage::nextLimit($this->messagesLimit);
        $this->categoryTitle = $this->resolveCategoryTitle((int) ($this->ticket->category_id ?? 0));
        $status = (string) ($this->ticket->status ?? '');
        $this->canReply = $this->canManage && $status !== 'closed';
        $category = null;

        try {
            $categoryId = (int) ($this->ticket->category_id ?? 0);

            if ($categoryId > 0) {
                $category = TicketDesk::categories()->get($categoryId);
            }
        } catch (\Throwable) {
            $category = null;
        }

        $this->attachmentsUploadAllowed = AttachmentSettings::allowedForPortalCategory($category);
        $this->attachments = AttachmentSettings::frontendEnabled()
            ? ($record['attachments'] ?? [])
            : [];
        $this->prepareReplyForm((int) $this->ticket->id);
        $this->document->setTitle(
            Text::sprintf('COM_DEVARTSUPPORT_PORTAL_TICKET_VIEW_TITLE', (int) $this->ticket->id)
        );
        SiteAssets::load();
        parent::display($tpl);
    }

    private function prepareCreateForm(int $userId): void
    {
        unset($userId);

        $data = [
            'subject'     => '',
            'category_id' => '',
            'priority'    => 'normal',
            'message'     => '',
        ];
        $saved = Factory::getApplication()->getUserState('com_devartsupport.portal.ticket.data');

        if (is_array($saved)) {
            $data = array_merge($data, $saved);
        }

        Form::addFormPath(JPATH_SITE . '/components/com_devartsupport/forms');
        $this->form = Form::getInstance('com_devartsupport.portal.ticket', 'ticket', ['control' => 'jform']);
        $this->addCategoryOptions();
        $this->form->bind($data);
    }

    private function prepareReplyForm(int $ticketId): void
    {
        $data = [
            'id'    => $ticketId,
            'reply' => '',
        ];
        $saved = Factory::getApplication()->getUserState('com_devartsupport.portal.reply.data');

        if (is_array($saved)) {
            $data = array_merge($data, $saved);
        }

        Form::addFormPath(JPATH_SITE . '/components/com_devartsupport/forms');
        $this->form = Form::getInstance('com_devartsupport.portal.reply', 'reply', ['control' => 'jform']);
        $this->form->bind($data);
    }

    private function addCategoryOptions(): void
    {
        $nodes = $this->form->getXml()->xpath('//field[@name="category_id"]');

        if ($nodes === false || $nodes === []) {
            return;
        }

        $field = $nodes[0];

        foreach (TicketDesk::categories()->options() as $category) {
            if ((int) $category->published !== 1) {
                continue;
            }

            $option = $field->addChild('option', htmlspecialchars((string) $category->title, ENT_XML1, 'UTF-8'));
            $option->addAttribute('value', (string) (int) $category->id);
        }
    }

    private function resolveCategoryTitle(int $categoryId): string
    {
        if ($categoryId < 1) {
            return '';
        }

        try {
            return trim((string) TicketDesk::categories()->get($categoryId)->title);
        } catch (\Throwable) {
            return '';
        }
    }
}
