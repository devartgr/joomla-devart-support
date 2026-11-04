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

use Joomla\CMS\Language\Text;

trait RequiresComponentPermission
{
    private function requirePermission(string $action): void
    {
        $user = $this->app->getIdentity();

        if ($user === null || !$user->authorise($action, 'com_devartsupport')) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }
    }
}
