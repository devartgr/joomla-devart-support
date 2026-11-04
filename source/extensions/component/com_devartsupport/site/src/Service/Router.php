<?php
/**
 * @package     DevArt Support
 * @subpackage  com_devartsupport
 *
 * @copyright   Copyright (C) 2026 Stathopoulos Kostas - DevArt. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Devart\Component\Devartsupport\Site\Service;

defined('_JEXEC') or die;

use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Component\Router\RouterView;
use Joomla\CMS\Component\Router\RouterViewConfiguration;
use Joomla\CMS\Component\Router\Rules\MenuRules;
use Joomla\CMS\Component\Router\Rules\NomenuRules;
use Joomla\CMS\Component\Router\Rules\StandardRules;
use Joomla\CMS\Menu\AbstractMenu;

/**
 * Site SEF router for the support portal (tickets list + ticket view/create).
 */
final class Router extends RouterView
{
    public function __construct(SiteApplication $app, AbstractMenu $menu)
    {
        $tickets = new RouterViewConfiguration('tickets');
        $this->registerView($tickets);

        $ticket = new RouterViewConfiguration('ticket');
        $ticket->setKey('id')->setParent($tickets);
        $this->registerView($ticket);

        parent::__construct($app, $menu);

        $this->attachRule(new MenuRules($this));
        $this->attachRule(new StandardRules($this));
        $this->attachRule(new NomenuRules($this));
    }

    /**
     * @return array<int, string>
     */
    public function getTicketSegment($id, $query): array
    {
        $ticketId = (int) $id;

        if ($ticketId < 1) {
            return [];
        }

        return [$ticketId => (string) $ticketId];
    }

    public function getTicketId($segment, $query): int
    {
        return max(0, (int) $segment);
    }
}
