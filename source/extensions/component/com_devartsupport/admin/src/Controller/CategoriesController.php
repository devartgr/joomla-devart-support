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

use Devart\Component\Devartsupport\Administrator\Helper\TicketDesk;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;

class CategoriesController extends BaseController
{
    use RequiresComponentPermission;

    public function delete(): void
    {
        $this->checkToken();

        $this->requirePermission('core.delete');
        $deleted = TicketDesk::categories()->delete($this->selectedIds());
        $this->app->enqueueMessage(Text::sprintf('COM_DEVARTSUPPORT_N_CATEGORIES_DELETED', $deleted));
        $this->setRedirect(Route::_('index.php?option=com_devartsupport&view=categories', false));
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
