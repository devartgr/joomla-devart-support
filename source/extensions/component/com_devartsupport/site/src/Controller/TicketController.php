<?php
/**
 * @package     DevArt Support
 * @subpackage  com_devartsupport
 *
 * @copyright   Copyright (C) 2026 Stathopoulos Kostas - DevArt. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Devart\Component\Devartsupport\Site\Controller;

defined('_JEXEC') or die;

use Devart\Component\Devartsupport\Administrator\Exception\TicketNotFoundException;
use Devart\Component\Devartsupport\Administrator\Exception\TicketValidationException;
use Devart\Component\Devartsupport\Administrator\Helper\TicketDesk;
use Devart\Component\Devartsupport\Administrator\Helper\UploadInput;
use Devart\Component\Devartsupport\Administrator\Service\TicketService;
use Devart\Component\Devartsupport\Site\Helper\PortalAccess;
use Devart\Component\Devartsupport\Site\Helper\PortalRoute;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;

class TicketController extends BaseController
{
    public function add(): void
    {
        PortalAccess::requirePermission('core.create');
        $this->setRedirect(PortalRoute::_('index.php?option=com_devartsupport&view=ticket&layout=edit'));
    }

    public function cancel(): void
    {
        $this->setRedirect(PortalRoute::_('index.php?option=com_devartsupport&view=tickets'));
    }

    public function save(): void
    {
        $this->checkToken();

        $user = PortalAccess::requirePermission('core.create');
        $data = (array) $this->input->get('jform', [], 'array');

        try {
            $id = TicketDesk::service()->createFromPortal($data, (int) $user->id, $this->uploadedFiles());
        } catch (TicketValidationException $error) {
            $this->app->enqueueMessage(Text::_($error->getLanguageKey()), 'error');
            $this->app->setUserState('com_devartsupport.portal.ticket.data', $data);
            $this->setRedirect(PortalRoute::_('index.php?option=com_devartsupport&view=ticket&layout=edit'));

            return;
        }

        $this->app->setUserState('com_devartsupport.portal.ticket.data', null);
        $this->app->enqueueMessage(Text::_('COM_DEVARTSUPPORT_PORTAL_TICKET_CREATED'));
        $this->setRedirect(PortalRoute::_('index.php?option=com_devartsupport&view=ticket&id=' . $id));
    }

    public function reply(): void
    {
        $this->checkToken();

        $user = PortalAccess::requirePermission('core.edit');
        $data = (array) $this->input->get('jform', [], 'array');
        $id = (int) ($data['id'] ?? $this->input->getInt('id'));
        $reply = (string) ($data['reply'] ?? '');

        try {
            TicketDesk::service()->replyFromPortal($id, $reply, (int) $user->id, $this->uploadedFiles());
        } catch (TicketValidationException $error) {
            $this->app->enqueueMessage(Text::_($error->getLanguageKey()), 'error');
            $this->app->setUserState('com_devartsupport.portal.reply.data', $data);
            $this->setRedirect(PortalRoute::_('index.php?option=com_devartsupport&view=ticket&id=' . max(0, $id)));

            return;
        } catch (TicketNotFoundException) {
            $this->app->enqueueMessage(Text::_('COM_DEVARTSUPPORT_ERROR_NOT_FOUND'), 'error');
            $this->setRedirect(PortalRoute::_('index.php?option=com_devartsupport&view=tickets'));

            return;
        }

        $this->app->setUserState('com_devartsupport.portal.reply.data', null);
        $this->app->enqueueMessage(Text::_('COM_DEVARTSUPPORT_PORTAL_REPLY_SAVED'));
        $this->setRedirect(PortalRoute::_('index.php?option=com_devartsupport&view=ticket&id=' . $id));
    }

    public function download(): void
    {
        $this->checkToken();

        $user = PortalAccess::requirePermission('core.edit');
        $attachmentId = $this->input->getInt('attachment_id');
        $ticketId = $this->input->getInt('id', $this->input->getInt('ticket_id'));

        try {
            $file = TicketDesk::service()->downloadForOwner($attachmentId, (int) $user->id);
        } catch (TicketValidationException $error) {
            $this->app->enqueueMessage(Text::_($error->getLanguageKey()), 'error');
            $this->setRedirect(PortalRoute::_('index.php?option=com_devartsupport&view=ticket&id=' . max(0, $ticketId)));

            return;
        } catch (TicketNotFoundException) {
            $this->app->enqueueMessage(Text::_('COM_DEVARTSUPPORT_ERROR_ATTACHMENT_NOT_FOUND'), 'error');
            $this->setRedirect(PortalRoute::_('index.php?option=com_devartsupport&view=ticket&id=' . max(0, $ticketId)));

            return;
        }

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $this->app->setHeader('Content-Type', $file['mime'], true);
        $this->app->setHeader(
            'Content-Disposition',
            $this->contentDispositionHeader($file['disposition'], $file['filename']),
            true
        );
        $this->app->setHeader('Content-Length', (string) $file['size'], true);
        $this->app->setHeader('Cache-Control', 'private, no-store, no-cache, must-revalidate', true);
        $this->app->setHeader('Pragma', 'no-cache', true);
        $this->app->setHeader('X-Content-Type-Options', 'nosniff', true);
        $this->app->sendHeaders();
        readfile($file['path']);
        $this->app->close();
    }

    public function close(): void
    {
        $this->changePortalStatus(TicketService::STATUS_CLOSED, 'COM_DEVARTSUPPORT_PORTAL_TICKET_CLOSED');
    }

    public function reopen(): void
    {
        $this->changePortalStatus(TicketService::STATUS_OPEN, 'COM_DEVARTSUPPORT_PORTAL_TICKET_REOPENED');
    }

    public function savePriority(): void
    {
        $this->checkToken();

        $user = PortalAccess::requirePermission('core.edit');
        $id = $this->input->getInt('id');
        $priority = (string) $this->input->get('priority', '', 'cmd');

        try {
            TicketDesk::service()->updatePriorityFromPortal($id, $priority, (int) $user->id);
        } catch (TicketValidationException $error) {
            $this->app->enqueueMessage(Text::_($error->getLanguageKey()), 'error');
            $this->setRedirect(PortalRoute::_('index.php?option=com_devartsupport&view=ticket&id=' . max(0, $id)));

            return;
        } catch (TicketNotFoundException) {
            $this->app->enqueueMessage(Text::_('COM_DEVARTSUPPORT_ERROR_NOT_FOUND'), 'error');
            $this->setRedirect(PortalRoute::_('index.php?option=com_devartsupport&view=tickets'));

            return;
        }

        $this->app->enqueueMessage(Text::_('COM_DEVARTSUPPORT_PORTAL_PRIORITY_UPDATED'));
        $this->setRedirect(PortalRoute::_('index.php?option=com_devartsupport&view=ticket&id=' . $id));
    }

    private function changePortalStatus(string $status, string $successKey): void
    {
        $this->checkToken();

        $user = PortalAccess::requirePermission('core.edit');
        $id = $this->input->getInt('id');

        try {
            TicketDesk::service()->setStatusFromPortal($id, $status, (int) $user->id);
        } catch (TicketValidationException $error) {
            $this->app->enqueueMessage(Text::_($error->getLanguageKey()), 'error');
            $this->setRedirect(PortalRoute::_('index.php?option=com_devartsupport&view=ticket&id=' . max(0, $id)));

            return;
        } catch (TicketNotFoundException) {
            $this->app->enqueueMessage(Text::_('COM_DEVARTSUPPORT_ERROR_NOT_FOUND'), 'error');
            $this->setRedirect(PortalRoute::_('index.php?option=com_devartsupport&view=tickets'));

            return;
        }

        $this->app->enqueueMessage(Text::_($successKey));
        $this->setRedirect(PortalRoute::_('index.php?option=com_devartsupport&view=ticket&id=' . $id));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function uploadedFiles(): array
    {
        return UploadInput::attachments($this->input->files->get('attachments', [], 'array'));
    }

    private function contentDispositionHeader(string $disposition, string $filename): string
    {
        $filename = str_replace(["\0", "\r", "\n"], '', $filename);
        $fallback = preg_replace('/[^\x20-\x7E]+/', '_', str_replace(['"', '\\'], '', $filename)) ?? '';
        $fallback = trim($fallback, " .") !== '' ? trim($fallback, " .") : 'attachment';
        $encoded = rawurlencode($filename !== '' ? $filename : 'attachment');

        return $disposition . '; filename="' . $fallback . '"; filename*=UTF-8\'\'' . $encoded;
    }
}
