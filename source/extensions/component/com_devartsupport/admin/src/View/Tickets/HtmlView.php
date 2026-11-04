<?php
/**
 * @package     DevArt Support
 * @subpackage  com_devartsupport
 *
 * @copyright   Copyright (C) 2026 Stathopoulos Kostas - DevArt. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Devart\Component\Devartsupport\Administrator\View\Tickets;

defined('_JEXEC') or die;

use Devart\Component\Devartsupport\Administrator\Helper\AdminAssets;
use Devart\Component\Devartsupport\Administrator\Helper\TicketDesk;
use Devart\Component\Devartsupport\Administrator\Service\TicketService;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Pagination\Pagination;
use Joomla\CMS\Toolbar\ToolbarHelper;

class HtmlView extends BaseHtmlView
{
    /** @var list<object> */
    public array $items = [];

    public int $total = 0;

    public string $search = '';

    public string $status = '';

    public string $priority = '';

    public int $categoryId = 0;

    public int $published = TicketService::PUBLISHED;

    public bool $isTrash = false;

    /** @var list<object{id: int, title: string, published: int, attachments_enabled: int}> */
    public array $categories = [];

    public Pagination $pagination;

    public function display($tpl = null): void
    {
        if (!$this->getCurrentUser()->authorise('core.manage', 'com_devartsupport')) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $app = Factory::getApplication();
        $previousSearch = (string) $app->getUserState('com_devartsupport.tickets.filter.search', '');
        $previousStatus = (string) $app->getUserState('com_devartsupport.tickets.filter.status', '');
        $previousPriority = (string) $app->getUserState('com_devartsupport.tickets.filter.priority', '');
        $previousCategoryId = (int) $app->getUserState('com_devartsupport.tickets.filter.category', 0);
        $previousPublished = (int) $app->getUserState('com_devartsupport.tickets.filter.published', TicketService::PUBLISHED);

        $this->search = trim((string) $app->getUserStateFromRequest('com_devartsupport.tickets.filter.search', 'filter_search', '', 'string'));
        $this->status = trim((string) $app->getUserStateFromRequest('com_devartsupport.tickets.filter.status', 'filter_status', '', 'cmd'));
        $this->priority = trim((string) $app->getUserStateFromRequest('com_devartsupport.tickets.filter.priority', 'filter_priority', '', 'cmd'));
        $this->categoryId = (int) $app->getUserStateFromRequest('com_devartsupport.tickets.filter.category', 'filter_category', 0, 'uint');
        $publishedRaw = (string) $app->getUserStateFromRequest(
            'com_devartsupport.tickets.filter.published',
            'filter_published',
            (string) TicketService::PUBLISHED,
            'cmd'
        );
        $this->published = ((int) $publishedRaw === TicketService::TRASHED)
            ? TicketService::TRASHED
            : TicketService::PUBLISHED;
        $this->isTrash = $this->published === TicketService::TRASHED;
        $limit = (int) $app->getUserStateFromRequest('global.list.limit', 'limit', $app->get('list_limit', 50), 'uint');
        $limitstart = (int) $app->getUserStateFromRequest('com_devartsupport.tickets.limitstart', 'limitstart', 0, 'uint');

        if (
            $this->search !== $previousSearch
            || $this->status !== $previousStatus
            || $this->priority !== $previousPriority
            || $this->categoryId !== $previousCategoryId
            || $this->published !== $previousPublished
        ) {
            $limitstart = 0;
            $app->setUserState('com_devartsupport.tickets.limitstart', 0);
        }

        ToolbarHelper::title(
            Text::_($this->isTrash ? 'COM_DEVARTSUPPORT_TICKETS_TRASH' : 'COM_DEVARTSUPPORT_TICKETS'),
            'comments-2'
        );

        if (!$this->isTrash && $this->getCurrentUser()->authorise('core.create', 'com_devartsupport')) {
            ToolbarHelper::addNew('ticket.add');
        }

        if (!$this->isTrash && $this->getCurrentUser()->authorise('core.edit', 'com_devartsupport')) {
            ToolbarHelper::custom('tickets.open', 'unlock', 'unlock', 'COM_DEVARTSUPPORT_STATUS_OPEN', true);
            ToolbarHelper::custom('tickets.close', 'lock', 'lock', 'COM_DEVARTSUPPORT_STATUS_CLOSED', true);
        }

        if ($this->isTrash) {
            if ($this->getCurrentUser()->authorise('core.edit', 'com_devartsupport')) {
                ToolbarHelper::publishList('tickets.publish', 'COM_DEVARTSUPPORT_TOOLBAR_RESTORE');
            }

            if ($this->getCurrentUser()->authorise('core.delete', 'com_devartsupport')) {
                ToolbarHelper::deleteList(
                    'COM_DEVARTSUPPORT_CONFIRM_EMPTY_TRASH',
                    'tickets.delete',
                    'COM_DEVARTSUPPORT_TOOLBAR_EMPTY_TRASH'
                );
            }
        } elseif ($this->getCurrentUser()->authorise('core.delete', 'com_devartsupport')) {
            ToolbarHelper::trash('tickets.trash');
        }

        if ($this->getCurrentUser()->authorise('core.admin', 'com_devartsupport')) {
            ToolbarHelper::preferences('com_devartsupport');
        }

        $this->categories = TicketDesk::categories()->options();

        $result = TicketDesk::service()->list(
            $this->search,
            $this->status,
            $this->priority,
            $this->categoryId,
            $limit,
            $limitstart,
            $this->published
        );
        $this->items = $result['items'];
        $this->total = $result['total'];
        $this->pagination = new Pagination($result['total'], $result['offset'], $result['limit']);

        AdminAssets::load();
        parent::display($tpl);
    }
}
