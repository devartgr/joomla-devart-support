<?php
/**
 * @package     DevArt Support
 * @subpackage  com_devartsupport
 *
 * @copyright   Copyright (C) 2026 Stathopoulos Kostas - DevArt. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Devart\Component\Devartsupport\Site\View\Tickets;

defined('_JEXEC') or die;

use Devart\Component\Devartsupport\Administrator\Helper\TicketDesk;
use Devart\Component\Devartsupport\Site\Helper\PortalAccess;
use Devart\Component\Devartsupport\Site\Helper\SiteAssets;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Pagination\Pagination;

class HtmlView extends BaseHtmlView
{
    /** @var list<object> */
    public array $items = [];

    public int $total = 0;

    public string $search = '';

    public string $status = '';

    public bool $canCreate = false;

    public Pagination $pagination;

    public function display($tpl = null): void
    {
        $user = PortalAccess::requirePortal();
        $this->canCreate = $user->authorise('core.create', 'com_devartsupport');

        $app = Factory::getApplication();
        $previousSearch = (string) $app->getUserState('com_devartsupport.portal.tickets.filter.search', '');
        $previousStatus = (string) $app->getUserState('com_devartsupport.portal.tickets.filter.status', '');

        $this->search = trim((string) $app->getUserStateFromRequest(
            'com_devartsupport.portal.tickets.filter.search',
            'filter_search',
            '',
            'string'
        ));
        $this->status = trim((string) $app->getUserStateFromRequest(
            'com_devartsupport.portal.tickets.filter.status',
            'filter_status',
            '',
            'cmd'
        ));
        $limit = (int) $app->getUserStateFromRequest('global.list.limit', 'limit', $app->get('list_limit', 20), 'uint');
        $limitstart = (int) $app->getUserStateFromRequest(
            'com_devartsupport.portal.tickets.limitstart',
            'limitstart',
            0,
            'uint'
        );

        if ($this->search !== $previousSearch || $this->status !== $previousStatus) {
            $limitstart = 0;
            $app->setUserState('com_devartsupport.portal.tickets.limitstart', 0);
        }

        $result = TicketDesk::portal()->listForOwner(
            (int) $user->id,
            $this->search,
            $this->status,
            $limit,
            $limitstart
        );
        $this->items = $result['items'];
        $this->total = $result['total'];
        $this->pagination = new Pagination($result['total'], $result['offset'], $result['limit']);

        $this->document->setTitle(Text::_('COM_DEVARTSUPPORT_PORTAL_TICKETS_TITLE'));
        SiteAssets::load();
        parent::display($tpl);
    }
}
