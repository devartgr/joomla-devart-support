<?php
/**
 * @package     DevArt Support
 * @subpackage  com_devartsupport
 *
 * @copyright   Copyright (C) 2026 Stathopoulos Kostas - DevArt. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Devart\Component\Devartsupport\Administrator\View\Categories;

defined('_JEXEC') or die;

use Devart\Component\Devartsupport\Administrator\Helper\AdminAssets;
use Devart\Component\Devartsupport\Administrator\Helper\TicketDesk;
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

    public Pagination $pagination;

    public function display($tpl = null): void
    {
        if (!$this->getCurrentUser()->authorise('core.manage', 'com_devartsupport')) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        ToolbarHelper::title(Text::_('COM_DEVARTSUPPORT_CATEGORIES'), 'folder');

        if ($this->getCurrentUser()->authorise('core.create', 'com_devartsupport')) {
            ToolbarHelper::addNew('category.add');
        }

        if ($this->getCurrentUser()->authorise('core.delete', 'com_devartsupport')) {
            ToolbarHelper::deleteList('COM_DEVARTSUPPORT_CONFIRM_DELETE_CATEGORIES', 'categories.delete');
        }

        if ($this->getCurrentUser()->authorise('core.admin', 'com_devartsupport')) {
            ToolbarHelper::preferences('com_devartsupport');
        }

        $app = Factory::getApplication();
        $previousSearch = (string) $app->getUserState('com_devartsupport.categories.filter.search', '');
        $this->search = trim((string) $app->getUserStateFromRequest('com_devartsupport.categories.filter.search', 'filter_search', '', 'string'));
        $limit = (int) $app->getUserStateFromRequest('global.list.limit', 'limit', $app->get('list_limit', 50), 'uint');
        $limitstart = (int) $app->getUserStateFromRequest('com_devartsupport.categories.limitstart', 'limitstart', 0, 'uint');

        if ($this->search !== $previousSearch) {
            $limitstart = 0;
            $app->setUserState('com_devartsupport.categories.limitstart', 0);
        }

        $result = TicketDesk::categories()->list($this->search, $limit, $limitstart);
        $this->items = $result['items'];
        $this->total = $result['total'];
        $this->pagination = new Pagination($result['total'], $result['offset'], $result['limit']);

        AdminAssets::load();
        parent::display($tpl);
    }
}
