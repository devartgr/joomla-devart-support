<?php
/**
 * @package     DevArt Support
 * @subpackage  com_devartsupport
 *
 * @copyright   Copyright (C) 2026 Stathopoulos Kostas - DevArt. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

declare(strict_types=1);

/** @var \Devart\Component\Devartsupport\Administrator\View\Tickets\HtmlView $this */

defined('_JEXEC') or die;

use Devart\Component\Devartsupport\Administrator\Helper\AdminChrome;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

$document = Factory::getApplication()->getDocument();
$document->getWebAssetManager()->useScript('multiselect');
$document->addScriptOptions('js-multiselect', ['formName' => 'adminForm']);

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
<div class="com-devartsupport">
<div class="devart-support-admin-wrap" data-devart-support-view="tickets">
    <?php echo AdminChrome::header(AdminChrome::IMAGE_TICKETS, Text::_('COM_DEVARTSUPPORT_TICKETS')); ?>
<form
    action="<?php echo $escape(Route::_('index.php?option=com_devartsupport&view=tickets', false)); ?>"
    method="post"
    name="adminForm"
    id="adminForm"
>
        <div class="row g-2 mb-3 align-items-end">
            <div class="col-md-3">
                <input
                    type="text"
                    name="filter_search"
                    class="form-control"
                    value="<?php echo $escape($this->search); ?>"
                    placeholder="<?php echo $escape(Text::_('COM_DEVARTSUPPORT_FILTER_SEARCH')); ?>"
                    aria-label="<?php echo $escape(Text::_('COM_DEVARTSUPPORT_FILTER_SEARCH')); ?>"
                >
            </div>
            <div class="col-md-2">
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
            <div class="col-md-2">
                <select
                    name="filter_priority"
                    class="form-select"
                    data-devart-support-autosubmit
                    aria-label="<?php echo $escape(Text::_('COM_DEVARTSUPPORT_FILTER_PRIORITY')); ?>"
                >
                    <option value=""><?php echo $escape(Text::_('COM_DEVARTSUPPORT_FILTER_PRIORITY')); ?></option>
                    <?php foreach ($priorityLabels as $value => $label) : ?>
                        <option value="<?php echo $escape($value); ?>"<?php echo $this->priority === $value ? ' selected' : ''; ?>>
                            <?php echo $escape($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select
                    name="filter_category"
                    class="form-select"
                    data-devart-support-autosubmit
                    aria-label="<?php echo $escape(Text::_('COM_DEVARTSUPPORT_FILTER_CATEGORY')); ?>"
                >
                    <option value="0"><?php echo $escape(Text::_('COM_DEVARTSUPPORT_FILTER_CATEGORY')); ?></option>
                    <?php foreach ($this->categories as $category) : ?>
                        <option value="<?php echo (int) $category->id; ?>"<?php echo $this->categoryId === (int) $category->id ? ' selected' : ''; ?>>
                            <?php echo $escape((string) $category->title); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-1">
                <select
                    name="filter_published"
                    class="form-select"
                    data-devart-support-autosubmit
                    aria-label="<?php echo $escape(Text::_('COM_DEVARTSUPPORT_FILTER_PUBLISHED')); ?>"
                >
                    <option value="1"<?php echo $this->published === 1 ? ' selected' : ''; ?>>
                        <?php echo $escape(Text::_('COM_DEVARTSUPPORT_FILTER_PUBLISHED_ACTIVE')); ?>
                    </option>
                    <option value="-2"<?php echo $this->published === -2 ? ' selected' : ''; ?>>
                        <?php echo $escape(Text::_('COM_DEVARTSUPPORT_FILTER_PUBLISHED_TRASHED')); ?>
                    </option>
                </select>
            </div>
            <div class="col-md-2">
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary"><?php echo $escape(Text::_('JSEARCH_FILTER_SUBMIT')); ?></button>
                    <a
                        class="btn btn-secondary"
                        href="<?php echo $escape(Route::_(
                            'index.php?option=com_devartsupport&view=tickets'
                            . '&filter_search='
                            . '&filter_status='
                            . '&filter_priority='
                            . '&filter_category=0'
                            . '&filter_published=1'
                            . '&limitstart=0',
                            false
                        )); ?>"
                    >
                        <?php echo $escape(Text::_('JSEARCH_FILTER_CLEAR')); ?>
                    </a>
                </div>
            </div>
        </div>

        <table class="table">
            <thead>
                <tr>
                    <th style="width:1%;">
                        <input type="checkbox" name="checkall-toggle" value="" data-devart-support-checkall>
                    </th>
                    <th style="width:6%;"><?php echo $escape(Text::_('COM_DEVARTSUPPORT_HEADING_ID')); ?></th>
                    <th><?php echo $escape(Text::_('COM_DEVARTSUPPORT_HEADING_SUBJECT')); ?></th>
                    <th><?php echo $escape(Text::_('COM_DEVARTSUPPORT_HEADING_REQUESTER')); ?></th>
                    <th style="width:12%;"><?php echo $escape(Text::_('COM_DEVARTSUPPORT_HEADING_CATEGORY')); ?></th>
                    <th style="width:10%;"><?php echo $escape(Text::_('COM_DEVARTSUPPORT_HEADING_PRIORITY')); ?></th>
                    <th style="width:10%;"><?php echo $escape(Text::_('COM_DEVARTSUPPORT_HEADING_STATUS')); ?></th>
                    <th style="width:10%;"><?php echo $escape(Text::_('COM_DEVARTSUPPORT_HEADING_MESSAGES')); ?></th>
                    <th style="width:16%;"><?php echo $escape(Text::_('COM_DEVARTSUPPORT_HEADING_LAST_MESSAGE')); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php if ($this->items === []) : ?>
                <tr>
                    <td colspan="9"><?php echo $escape(Text::_('COM_DEVARTSUPPORT_NO_TICKETS')); ?></td>
                </tr>
            <?php endif; ?>
            <?php foreach ($this->items as $i => $item) : ?>
                <?php
                $status = (string) $item->status;
                $statusLabel = $statusLabels[$status] ?? $status;
                $priority = (string) ($item->priority ?? 'normal');
                $priorityLabel = $priorityLabels[$priority] ?? $priority;
                $categoryTitle = trim((string) ($item->category_title ?? ''));
                $requester = trim((string) $item->requester_name);
                $email = trim((string) $item->requester_email);
                $ownerId = (int) ($item->user_id ?? 0);
                $lastMessageUserId = (int) ($item->last_message_user_id ?? 0);
                $needsStaffReply = $ownerId > 0 && $lastMessageUserId === $ownerId;
                ?>
                <tr class="<?php echo $needsStaffReply ? 'devart-support-ticket-row--needs-reply' : ''; ?>">
                    <td><?php echo HTMLHelper::_('grid.id', $i, (int) $item->id); ?></td>
                    <td><?php echo (int) $item->id; ?></td>
                    <td>
                        <a href="<?php echo $escape(Route::_('index.php?option=com_devartsupport&task=ticket.edit&id=' . (int) $item->id, false)); ?>">
                            <?php echo $escape((string) $item->subject); ?>
                        </a>
                        <?php if ($needsStaffReply) : ?>
                            <div class="small mt-1">
                                <span class="badge bg-warning text-dark"><?php echo $escape(Text::_('COM_DEVARTSUPPORT_NEEDS_REPLY_BADGE')); ?></span>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php echo $escape($requester); ?>
                        <?php if ($email !== '') : ?>
                            <div class="small text-muted"><?php echo $escape($email); ?></div>
                        <?php endif; ?>
                    </td>
                    <td><?php echo $categoryTitle !== '' ? $escape($categoryTitle) : $escape(Text::_('COM_DEVARTSUPPORT_CATEGORY_NONE')); ?></td>
                    <td>
                        <span class="badge devart-support-priority devart-support-priority--<?php echo $escape($priority); ?>">
                            <?php echo $escape($priorityLabel); ?>
                        </span>
                    </td>
                    <td>
                        <span class="badge devart-support-status devart-support-status--<?php echo $escape($status); ?>">
                            <?php echo $escape($statusLabel); ?>
                        </span>
                    </td>
                    <td><?php echo (int) ($item->message_count ?? 0); ?></td>
                    <td><?php echo $escape((string) $item->last_message_at); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <?php echo $this->pagination->getListFooter(); ?>

    <input type="hidden" name="task" value="">
    <input type="hidden" name="boxchecked" value="0">
    <?php echo HTMLHelper::_('form.token'); ?>
</form>
    <?php echo AdminChrome::footer(); ?>
</div>
</div>
