<?php
/**
 * @package     DevArt Support
 * @subpackage  com_devartsupport
 *
 * @copyright   Copyright (C) 2026 Stathopoulos Kostas - DevArt. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Devart\Component\Devartsupport\Administrator\Controller;

defined('_JEXEC') or die;

use Devart\Component\Devartsupport\Administrator\Exception\TicketNotFoundException;
use Devart\Component\Devartsupport\Administrator\Exception\TicketValidationException;
use Devart\Component\Devartsupport\Administrator\Helper\TicketDesk;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;

class TicketController extends BaseController
{
    use RequiresComponentPermission;

    public function add(): void
    {
        $this->requirePermission('core.create');
        $this->setRedirect(Route::_('index.php?option=com_devartsupport&view=ticket&layout=edit', false));
    }

    public function edit(): void
    {
        $this->requirePermission('core.edit');
        $cid = (array) $this->input->get('cid', [], 'array');
        $id = isset($cid[0]) ? (int) $cid[0] : $this->input->getInt('id');
        $this->setRedirect(Route::_('index.php?option=com_devartsupport&view=ticket&layout=edit&id=' . $id, false));
    }

    public function cancel(): void
    {
        $this->setRedirect(Route::_('index.php?option=com_devartsupport&view=tickets', false));
    }

    public function apply(): void
    {
        $this->save(true);
    }

    public function save(bool $apply = false): void
    {
        $this->checkToken();

        $data = (array) $this->input->get('jform', [], 'array');
        $id = (int) ($data['id'] ?? $this->input->getInt('id'));
        $this->requirePermission($id > 0 ? 'core.edit' : 'core.create');
        $staffId = (int) $this->app->getIdentity()->id;
        $uploads = $this->uploadedFiles();

        try {
            if ($id > 0) {
                TicketDesk::service()->update($id, $data, $staffId, $uploads);
            } else {
                $id = TicketDesk::service()->create($data, $staffId, $uploads);
            }
        } catch (TicketValidationException $error) {
            $this->app->enqueueMessage(Text::_($error->getLanguageKey()), 'error');
            $this->app->setUserState('com_devartsupport.ticket.data', $data);
            $this->setRedirect(Route::_(
                'index.php?option=com_devartsupport&view=ticket&layout=edit' . ($id > 0 ? '&id=' . $id : ''),
                false
            ));

            return;
        } catch (TicketNotFoundException) {
            $this->app->enqueueMessage(Text::_('COM_DEVARTSUPPORT_ERROR_NOT_FOUND'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_devartsupport&view=tickets', false));

            return;
        }

        $this->app->setUserState('com_devartsupport.ticket.data', null);
        $this->app->enqueueMessage(Text::_('COM_DEVARTSUPPORT_SAVE_SUCCESS'));

        if ($apply) {
            $this->setRedirect(Route::_('index.php?option=com_devartsupport&view=ticket&layout=edit&id=' . $id, false));

            return;
        }

        $this->setRedirect(Route::_('index.php?option=com_devartsupport&view=tickets', false));
    }

    public function download(): void
    {
        $this->checkToken();

        $this->requirePermission('core.manage');
        $ticketId = $this->input->getInt('ticket_id');

        try {
            $file = TicketDesk::service()->download($this->attachmentId());
        } catch (TicketNotFoundException) {
            $this->app->enqueueMessage(Text::_('COM_DEVARTSUPPORT_ERROR_ATTACHMENT_NOT_FOUND'), 'error');
            $this->setRedirect($this->attachmentReturn($ticketId));

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

    public function deleteattachment(): void
    {
        $this->checkToken();

        $this->requirePermission('core.edit');
        $ticketId = $this->input->getInt('ticket_id');

        try {
            $ticketId = TicketDesk::service()->deleteAttachment($this->attachmentId());
            $this->app->enqueueMessage(Text::_('COM_DEVARTSUPPORT_ATTACHMENT_DELETED'));
            $this->setRedirect($this->attachmentReturn($ticketId));
        } catch (TicketNotFoundException) {
            $this->app->enqueueMessage(Text::_('COM_DEVARTSUPPORT_ERROR_ATTACHMENT_NOT_FOUND'), 'error');
            $this->setRedirect($this->attachmentReturn($ticketId));
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function uploadedFiles(): array
    {
        $raw = $this->input->files->get('attachments', [], 'array');

        if ($raw === [] || $raw === null) {
            return [];
        }

        if (isset($raw['name']) && !is_array($raw['name'])) {
            return [$raw];
        }

        if (isset($raw['name']) && is_array($raw['name'])) {
            $files = [];

            foreach (array_keys($raw['name']) as $index) {
                $files[] = [
                    'name'     => $raw['name'][$index] ?? '',
                    'type'     => $raw['type'][$index] ?? '',
                    'tmp_name' => $raw['tmp_name'][$index] ?? '',
                    'error'    => $raw['error'][$index] ?? UPLOAD_ERR_NO_FILE,
                    'size'     => $raw['size'][$index] ?? 0,
                ];
            }

            return $files;
        }

        return array_values(array_filter($raw, static fn(mixed $row): bool => is_array($row)));
    }

    private function attachmentId(): int
    {
        $id = $this->input->getInt('attachment_id');

        return $id > 0 ? $id : $this->input->getInt('id');
    }

    private function attachmentReturn(int $ticketId): string
    {
        if ($ticketId > 0) {
            return Route::_('index.php?option=com_devartsupport&view=ticket&layout=edit&id=' . $ticketId, false);
        }

        return Route::_('index.php?option=com_devartsupport&view=tickets', false);
    }

    private function contentDispositionHeader(string $disposition, string $filename): string
    {
        $filename = str_replace(["\0", "\r", "\n"], '', $filename);
        $fallback = preg_replace('/[^\x20-\x7E]+/', '_', str_replace(['"', '\\'], '', $filename)) ?? '';
        $fallback = trim($fallback, " .") !== '' ? trim($fallback, " .") : 'attachment';
        $encoded = rawurlencode($filename !== '' ? $filename : 'attachment');

        return $disposition . '; filename="' . $fallback . '"; filename*=UTF-8\'\'' . $encoded;
    }

    private function requirePermission(string $action): void
    {
        $user = $this->app->getIdentity();

        if ($user === null || !$user->authorise($action, 'com_devartsupport')) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }
    }
}
