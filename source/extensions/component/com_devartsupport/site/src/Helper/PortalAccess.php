<?php
/**
 * @package     DevArt Support
 * @subpackage  com_devartsupport
 *
 * @copyright   Copyright (C) 2026 Stathopoulos Kostas - DevArt. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Devart\Component\Devartsupport\Site\Helper;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\User\User;

final class PortalAccess
{
    public static function requireLogin(): User
    {
        $app = Factory::getApplication();
        $user = $app->getIdentity();

        if ($user === null || (int) $user->id < 1 || $user->guest) {
            // Seed com_users login form data; core encodes the hidden return field on the login view.
            $return = Uri::getInstance()->toString(['path', 'query', 'fragment']);

            if ($return === '' || !Uri::isInternal($return)) {
                $return = 'index.php?option=com_devartsupport';
            }

            $loginData           = $app->getUserState('users.login.form.data', []);
            $loginData['return'] = $return;
            $app->setUserState('users.login.form.data', $loginData);

            $app->enqueueMessage(Text::_('COM_DEVARTSUPPORT_ERROR_PORTAL_LOGIN'), 'notice');
            $app->redirect(Route::_('index.php?option=com_users&view=login', false));
        }

        return $user;
    }

    /**
     * Any portal screen: login plus create or edit ACL (narrow groups via Joomla ACL).
     */
    public static function requirePortal(): User
    {
        $user = self::requireLogin();

        if (
            !$user->authorise('core.create', 'com_devartsupport')
            && !$user->authorise('core.edit', 'com_devartsupport')
        ) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        return $user;
    }

    public static function requirePermission(string $action): User
    {
        $user = self::requireLogin();

        if (!$user->authorise($action, 'com_devartsupport')) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        return $user;
    }
}
