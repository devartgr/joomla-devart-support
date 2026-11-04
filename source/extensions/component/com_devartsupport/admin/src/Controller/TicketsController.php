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

use Devart\Component\Devartsupport\Administrator\Exception\TicketValidationException;
use Devart\Component\Devartsupport\Administrator\Helper\TicketDesk;
use Devart\Component\Devartsupport\Administrator\Service\TicketService;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;

class TicketsController extends BaseController
{
    use RequiresComponentPermission;

    public function trash(): void
    {
        $this->checkToken();

        $this->requirePermission('core.delete');
        $count = TicketDesk::service()->trash($this->selectedIds());
        $this->app->enqueueMessage(Text::sprintf('COM_DEVARTSUPPORT_N_TRASHED', $count));
        $this->setRedirect(Route::_('index.php?option=com_devartsupport&view=tickets', false));
    }

    public function publish(): void
    {
        $this->checkToken();

        $this->requirePermission('core.edit');
        $count = TicketDesk::service()->publish($this->selectedIds());
        $this->app->enqueueMessage(Text::sprintf('COM_DEVARTSUPPORT_N_RESTORED', $count));
        $this->setRedirect(Route::_('index.php?option=com_devartsupport&view=tickets&filter_published=-2', false));
    }

    public function delete(): void
    {
        $this->checkToken();

        $this->requirePermission('core.delete');
        $deleted = TicketDesk::service()->delete($this->selectedIds());
        $this->app->enqueueMessage(Text::sprintf('COM_DEVARTSUPPORT_N_DELETED_TRASH', $deleted));
        $this->setRedirect(Route::_('index.php?option=com_devartsupport&view=tickets&filter_published=-2', false));
    }

    public function open(): void
    {
        $this->changeStatus(TicketService::STATUS_OPEN);
    }

    public function close(): void
    {
        $this->changeStatus(TicketService::STATUS_CLOSED);
    }

    private function changeStatus(string $status): void
    {
        $this->checkToken();

        $this->requirePermission('core.edit');
        $staffId = (int) $this->app->getIdentity()->id;

        try {
            TicketDesk::service()->changeStatus($this->selectedIds(), $status, $staffId);
            $this->app->enqueueMessage(Text::_('COM_DEVARTSUPPORT_STATUS_UPDATED'));
        } catch (TicketValidationException $error) {
            $this->app->enqueueMessage(Text::_($error->getLanguageKey()), 'error');
        }

        $this->setRedirect(Route::_('index.php?option=com_devartsupport&view=tickets', false));
    }

    /**
     * @return list<int>
     */
    private function selectedIds(): array
    {
        return array_values(array_filter(array_map('intval', (array) $this->input->get('cid', [], 'array'))));
    }

    private function requirePermission(string $action): void
    {
        $user = $this->app->getIdentity();

        if ($user === null || !$user->authorise($action, 'com_devartsupport')) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }
    }
}
