<?php
/**
 * @package     DevArt Support
 * @subpackage  com_devartsupport
 *
 * @copyright   Copyright (C) 2026 Stathopoulos Kostas - DevArt. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Devart\Component\Devartsupport\Site\Extension;

defined('_JEXEC') or die;

use Devart\Component\Devartsupport\Administrator\Service\CategoryService;
use Devart\Component\Devartsupport\Administrator\Service\PortalTicketService;
use Devart\Component\Devartsupport\Administrator\Service\TicketMailService;
use Joomla\CMS\Component\Router\RouterServiceInterface;
use Joomla\CMS\Component\Router\RouterServiceTrait;
use Joomla\CMS\Extension\MVCComponent;

final class DevartsupportComponent extends MVCComponent implements RouterServiceInterface
{
    use RouterServiceTrait;

    private PortalTicketService $portalTicketService;

    private CategoryService $categoryService;

    private TicketMailService $ticketMailService;

    public function setPortalTicketService(PortalTicketService $portalTicketService): void
    {
        $this->portalTicketService = $portalTicketService;
    }

    public function getPortalTicketService(): PortalTicketService
    {
        return $this->portalTicketService;
    }

    public function setCategoryService(CategoryService $categoryService): void
    {
        $this->categoryService = $categoryService;
    }

    public function getCategoryService(): CategoryService
    {
        return $this->categoryService;
    }

    public function setTicketMailService(TicketMailService $ticketMailService): void
    {
        $this->ticketMailService = $ticketMailService;
    }

    public function getTicketMailService(): TicketMailService
    {
        return $this->ticketMailService;
    }
}
