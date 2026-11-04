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
use Devart\Component\Devartsupport\Administrator\Model\SettingsModel;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\FormController;
use Joomla\CMS\Router\Route;

final class SettingsController extends FormController
{
    protected $view_list = 'tools';

    public function save($key = null, $urlVar = null): bool
    {
        $this->checkToken();

        if (!$this->app->getIdentity()->authorise('core.admin', 'com_devartsupport')) {
            $this->setRedirect(
                Route::_('index.php?option=com_devartsupport&view=tools', false),
                Text::_('JERROR_ALERTNOAUTHOR'),
                'error'
            );

            return false;
        }

        $data = $this->input->post->get('jform', [], 'array');
        /** @var SettingsModel $model */
        $model = $this->getModel('Settings');
        $form = $model->getForm($data, false);

        if (!$form) {
            $this->app->setUserState(SettingsModel::USER_STATE, $data);
            $this->setRedirect(
                Route::_('index.php?option=com_devartsupport&view=settings', false),
                Text::_('JERROR_AN_ERROR_HAS_OCCURRED'),
                'error'
            );

            return false;
        }

        $valid = $model->validate($form, $data);

        if ($valid === false) {
            $this->app->setUserState(SettingsModel::USER_STATE, $data);

            foreach ($model->getErrors() as $error) {
                $this->app->enqueueMessage((string) $error, 'warning');
            }

            $this->setRedirect(Route::_('index.php?option=com_devartsupport&view=settings', false));

            return false;
        }

        // Overlay raw posted switchers so Form filter/showon cannot drop them.
        foreach (
            [
                'email_enabled',
                'email_notify_new_ticket',
                'email_notify_reply',
                'email_notify_status_change',
                'email_notify_requester',
                'email_notify_author_copy',
                'email_smtp_auth',
            ] as $switch
        ) {
            if (array_key_exists($switch, $data)) {
                $valid[$switch] = (int) $data[$switch] === 1 ? 1 : 0;
            }
        }

        try {
            $model->save($valid);
        } catch (\Throwable $e) {
            $this->app->setUserState(SettingsModel::USER_STATE, $data);
            $this->setRedirect(
                Route::_('index.php?option=com_devartsupport&view=settings', false),
                $e->getMessage(),
                'error'
            );

            return false;
        }

        $this->app->setUserState(SettingsModel::USER_STATE, null);

        $target = $this->getTask() === 'apply'
            ? 'index.php?option=com_devartsupport&view=settings'
            : 'index.php?option=com_devartsupport&view=tools';

        $this->setRedirect(Route::_($target, false), Text::_('COM_DEVARTSUPPORT_SETTINGS_SAVED'));

        return true;
    }

    public function testmail(): void
    {
        $this->checkToken();

        if (!$this->app->getIdentity()->authorise('core.admin', 'com_devartsupport')) {
            $this->setRedirect(
                Route::_('index.php?option=com_devartsupport&view=tools', false),
                Text::_('JERROR_ALERTNOAUTHOR'),
                'error'
            );

            return;
        }

        $data = $this->input->post->get('jform', [], 'array');
        /** @var SettingsModel $model */
        $model = $this->getModel('Settings');
        $form = $model->getForm($data, false);

        if (!$form) {
            $this->app->setUserState(SettingsModel::USER_STATE, $data);
            $this->setRedirect(
                Route::_('index.php?option=com_devartsupport&view=settings', false),
                Text::_('JERROR_AN_ERROR_HAS_OCCURRED'),
                'error'
            );

            return;
        }

        $valid = $model->validate($form, $data);

        if ($valid === false) {
            $this->app->setUserState(SettingsModel::USER_STATE, $data);

            foreach ($model->getErrors() as $error) {
                $this->app->enqueueMessage((string) $error, 'error');
            }

            $this->setRedirect(Route::_('index.php?option=com_devartsupport&view=settings', false));

            return;
        }

        try {
            $model->save($valid);
            $this->app->setUserState(SettingsModel::USER_STATE, null);
            $to = trim((string) ($valid['email_test_recipient'] ?? $data['email_test_recipient'] ?? ''));
            TicketDesk::mail()->sendTest($to);
            $this->setRedirect(
                Route::_('index.php?option=com_devartsupport&view=settings', false),
                Text::_('COM_DEVARTSUPPORT_MAIL_TEST_SENT')
            );
        } catch (\Throwable $e) {
            $this->app->setUserState(SettingsModel::USER_STATE, $data);
            $this->setRedirect(
                Route::_('index.php?option=com_devartsupport&view=settings', false),
                $e->getMessage(),
                'error'
            );
        }
    }

    public function cancel($key = null): bool
    {
        $this->app->setUserState(SettingsModel::USER_STATE, null);
        $this->setRedirect(Route::_('index.php?option=com_devartsupport&view=tools', false));

        return true;
    }
}
