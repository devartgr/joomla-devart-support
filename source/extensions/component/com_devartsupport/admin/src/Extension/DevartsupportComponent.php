<?php
/**
 * @package     DevArt Support
 * @subpackage  com_devartsupport
 *
 * @copyright   Copyright (C) 2026 Stathopoulos Kostas - DevArt. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Devart\Component\Devartsupport\Administrator\Extension;

defined('_JEXEC') or die;

use Devart\Component\Devartsupport\Administrator\Service\CategoryService;
use Devart\Component\Devartsupport\Administrator\Service\TicketMailService;
use Devart\Component\Devartsupport\Administrator\Service\TicketService;
use Joomla\CMS\Extension\MVCComponent;

final class DevartsupportComponent extends MVCComponent
{
    private TicketService $ticketService;

    private CategoryService $categoryService;

    private TicketMailService $ticketMailService;

    public function setTicketService(TicketService $ticketService): void
    {
        $this->ticketService = $ticketService;
    }

    public function getTicketService(): TicketService
    {
        return $this->ticketService;
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
