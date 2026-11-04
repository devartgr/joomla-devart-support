<?php
/**
 * @package     DevArt Support
 * @subpackage  com_devartsupport
 *
 * @copyright   Copyright (C) 2026 Stathopoulos Kostas - DevArt. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

declare(strict_types=1);

/** @var \Devart\Component\Devartsupport\Site\View\Tickets\HtmlView $this */

defined('_JEXEC') or die;

use Devart\Component\Devartsupport\Administrator\Helper\PortalFrontSettings;
use Devart\Component\Devartsupport\Site\Helper\PortalRoute;
use Devart\Component\Devartsupport\Site\Helper\SiteAssets;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

$escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
$statusLabels = [
    'open'   => Text::_('COM_DEVARTSUPPORT_STATUS_OPEN'),
    'closed' => Text::_('COM_DEVARTSUPPORT_STATUS_CLOSED'),
];
$priorityLabels = [
    'low'    => Text::_('COM_DEVARTSUPPORT_PRIORITY_LOW'),
    'normal' => Text::_('COM_DEVARTSUPPORT_PRIORITY_NORMAL'),
    'high'   => Text::_('COM_DEVARTSUPPORT_PRIORITY_HIGH'),
    'urgent' => Text::_('COM_DEVARTSUPPORT_PRIORITY_URGENT'),
];
?>
<div class="com-devartsupport-portal <?php echo $escape(SiteAssets::themeClass()); ?>">
    <header class="devart-support-portal-head">
        <h1><?php echo $escape(Text::_('COM_DEVARTSUPPORT_PORTAL_TICKETS_HEADING')); ?></h1>
        <p class="devart-support-portal-intro"><?php echo $escape(Text::_('COM_DEVARTSUPPORT_PORTAL_TICKETS_INTRO')); ?></p>
        <?php if ($this->canCreate) : ?>
            <p>
                <a class="btn btn-primary" href="<?php echo $escape(PortalRoute::_('index.php?option=com_devartsupport&task=ticket.add')); ?>">
                    <?php echo $escape(Text::_('COM_DEVARTSUPPORT_PORTAL_NEW_TICKET')); ?>
                </a>
            </p>
        <?php endif; ?>
    </header>

    <form
        action="<?php echo $escape(PortalRoute::_('index.php?option=com_devartsupport&view=tickets')); ?>"
        method="post"
        name="portalTicketsForm"
        id="portalTicketsForm"
        class="devart-support-portal-filters"
    >
        <div class="row g-2 mb-3 align-items-end">
            <div class="col-md-5">
                <input
                    type="text"
                    name="filter_search"
                    class="form-control"
                    value="<?php echo $escape($this->search); ?>"
                    placeholder="<?php echo $escape(Text::_('COM_DEVARTSUPPORT_PORTAL_FILTER_SEARCH')); ?>"
                    aria-label="<?php echo $escape(Text::_('COM_DEVARTSUPPORT_PORTAL_FILTER_SEARCH')); ?>"
                >
            </div>
            <div class="col-md-3">
                <select
                    name="filter_status"
                    class="form-select"
                    data-devart-support-autosubmit
                    aria-label="<?php echo $escape(Text::_('COM_DEVARTSUPPORT_FILTER_STATUS')); ?>"
                >
                    <option value=""><?php echo $escape(Text::_('COM_DEVARTSUPPORT_FILTER_STATUS')); ?></option>
                    <?php foreach ($statusLabels as $value => $label) : ?>
                        <option value="<?php echo $escape($value); ?>"<?php echo $this->status === $value ? ' selected' : ''; ?>>
                            <?php echo $escape($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <?php echo $escape(Text::_('JSEARCH_FILTER_SUBMIT')); ?>
                    </button>
                    <a
                        class="btn btn-secondary"
                        href="<?php echo $escape(PortalRoute::_(
                            'index.php?option=com_devartsupport&view=tickets'
                            . '&filter_search='
                            . '&filter_status='
                            . '&limitstart=0'
                        )); ?>"
                    >
                        <?php echo $escape(Text::_('JSEARCH_FILTER_CLEAR')); ?>
                    </a>
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover devart-support-portal-table">
                <thead>
                    <tr>
                        <th scope="col"><?php echo $escape(Text::_('COM_DEVARTSUPPORT_HEADING_SUBJECT')); ?></th>
                        <th scope="col" class="devart-support-portal-col-status"><?php echo $escape(Text::_('COM_DEVARTSUPPORT_HEADING_STATUS')); ?></th>
                        <th scope="col" class="devart-support-portal-col-priority"><?php echo $escape(Text::_('COM_DEVARTSUPPORT_HEADING_PRIORITY')); ?></th>
                        <th scope="col" class="devart-support-portal-col-category"><?php echo $escape(Text::_('COM_DEVARTSUPPORT_HEADING_CATEGORY')); ?></th>
                        <th scope="col" class="devart-support-portal-col-last"><?php echo $escape(Text::_('COM_DEVARTSUPPORT_HEADING_LAST_MESSAGE')); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($this->items === []) : ?>
                    <tr>
                        <td colspan="5"><?php echo $escape(Text::_('COM_DEVARTSUPPORT_PORTAL_NO_TICKETS')); ?></td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($this->items as $item) : ?>
                    <?php
                    $status = (string) $item->status;
                    $statusLabel = $statusLabels[$status] ?? $status;
                    $priority = (string) ($item->priority ?? 'normal');
                    $priorityLabel = $priorityLabels[$priority] ?? $priority;
                    $categoryTitle = trim((string) ($item->category_title ?? ''));
                    ?>
                    <tr>
                        <td>
                            <a href="<?php echo $escape(PortalRoute::_('index.php?option=com_devartsupport&view=ticket&id=' . (int) $item->id)); ?>">
                                <?php echo $escape((string) $item->subject); ?>
                            </a>
                        </td>
                        <td>
                            <span class="devart-support-portal-status devart-support-portal-status--<?php echo $escape($status !== '' ? $status : 'open'); ?>">
                                <?php echo $escape($statusLabel !== '' ? $statusLabel : Text::_('COM_DEVARTSUPPORT_STATUS_OPEN')); ?>
                            </span>
                        </td>
                        <td>
                            <span class="devart-support-portal-priority devart-support-portal-priority--<?php echo $escape($priority !== '' ? $priority : 'normal'); ?>">
                                <?php echo $escape($priorityLabel !== '' ? $priorityLabel : Text::_('COM_DEVARTSUPPORT_PRIORITY_NORMAL')); ?>
                            </span>
                        </td>
                        <td><?php echo $categoryTitle !== '' ? $escape($categoryTitle) : $escape(Text::_('COM_DEVARTSUPPORT_CATEGORY_NONE')); ?></td>
                        <td><?php echo $escape(PortalFrontSettings::formatDate((string) $item->last_message_at)); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php echo $this->pagination->getPagesLinks(); ?>
        <?php echo HTMLHelper::_('form.token'); ?>
    </form>
</div>
