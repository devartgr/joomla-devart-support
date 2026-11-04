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

use Devart\Component\Devartsupport\Administrator\Exception\CategoryNotFoundException;
use Devart\Component\Devartsupport\Administrator\Exception\CategoryValidationException;
use Devart\Component\Devartsupport\Administrator\Helper\TicketDesk;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;

class CategoryController extends BaseController
{
    use RequiresComponentPermission;

    public function add(): void
    {
        $this->requirePermission('core.create');
        $this->setRedirect(Route::_('index.php?option=com_devartsupport&view=category&layout=edit', false));
    }

    public function edit(): void
    {
        $this->requirePermission('core.edit');
        $cid = (array) $this->input->get('cid', [], 'array');
        $id = isset($cid[0]) ? (int) $cid[0] : $this->input->getInt('id');
        $this->setRedirect(Route::_('index.php?option=com_devartsupport&view=category&layout=edit&id=' . $id, false));
    }

    public function cancel(): void
    {
        $this->setRedirect(Route::_('index.php?option=com_devartsupport&view=categories', false));
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

        try {
            if ($id > 0) {
                TicketDesk::categories()->update($id, $data);
            } else {
                $id = TicketDesk::categories()->create($data);
            }
        } catch (CategoryValidationException $error) {
            $this->app->enqueueMessage(Text::_($error->getLanguageKey()), 'error');
            $this->app->setUserState('com_devartsupport.category.data', $data);
            $this->setRedirect(Route::_(
                'index.php?option=com_devartsupport&view=category&layout=edit' . ($id > 0 ? '&id=' . $id : ''),
                false
            ));

            return;
        } catch (CategoryNotFoundException) {
            $this->app->enqueueMessage(Text::_('COM_DEVARTSUPPORT_ERROR_CATEGORY_NOT_FOUND'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_devartsupport&view=categories', false));

            return;
        }

        $this->app->setUserState('com_devartsupport.category.data', null);
        $this->app->enqueueMessage(Text::_('COM_DEVARTSUPPORT_CATEGORY_SAVE_SUCCESS'));

        if ($apply) {
            $this->setRedirect(Route::_('index.php?option=com_devartsupport&view=category&layout=edit&id=' . $id, false));

            return;
        }

        $this->setRedirect(Route::_('index.php?option=com_devartsupport&view=categories', false));
    }
}
