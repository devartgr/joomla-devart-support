<?php
/**
 * @package     DevArt Support
 * @subpackage  com_devartsupport
 *
 * @copyright   Copyright (C) 2026 Stathopoulos Kostas - DevArt. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

declare(strict_types=1);

/** @var \Devart\Component\Devartsupport\Site\View\Ticket\HtmlView $this */

defined('_JEXEC') or die;

use Devart\Component\Devartsupport\Administrator\Helper\PortalFrontSettings;
use Devart\Component\Devartsupport\Administrator\Helper\UserDisplay;
use Devart\Component\Devartsupport\Site\Helper\PortalRoute;
use Devart\Component\Devartsupport\Site\Helper\SiteAssets;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

$escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
$ticket = $this->ticket;
$status = (string) ($ticket->status ?? '');
$priority = (string) ($ticket->priority ?? 'normal');
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
$statusLabel = $statusLabels[$status] ?? $status;
$priorityLabel = $priorityLabels[$priority] ?? $priority;
$categoryTitle = trim((string) $this->categoryTitle);
$token = HTMLHelper::_('form.token');
?>
<div class="com-devartsupport-portal <?php echo $escape(SiteAssets::themeClass()); ?>">
    <header class="devart-support-portal-head">
        <p class="mb-2">
            <a href="<?php echo $escape(PortalRoute::_('index.php?option=com_devartsupport&view=tickets')); ?>">
                &larr; <?php echo $escape(Text::_('COM_DEVARTSUPPORT_PORTAL_BACK_TO_LIST')); ?>
            </a>
        </p>
        <h1><?php echo $escape((string) $ticket->subject); ?></h1>
        <p class="devart-support-portal-meta">
            <span class="devart-support-portal-status devart-support-portal-status--<?php echo $escape($status !== '' ? $status : 'open'); ?>">
                <?php echo $escape($statusLabel !== '' ? $statusLabel : Text::_('COM_DEVARTSUPPORT_STATUS_OPEN')); ?>
            </span>
            <span class="devart-support-portal-priority devart-support-portal-priority--<?php echo $escape($priority !== '' ? $priority : 'normal'); ?>">
                <?php echo $escape($priorityLabel !== '' ? $priorityLabel : Text::_('COM_DEVARTSUPPORT_PRIORITY_NORMAL')); ?>
            </span>
            <?php if ($categoryTitle !== '') : ?>
                <span class="text-muted"><?php echo $escape($categoryTitle); ?></span>
            <?php endif; ?>
        </p>
    </header>

    <?php if ($this->canManage) : ?>
        <section class="devart-support-portal-manage mb-4" aria-label="<?php echo $escape(Text::_('COM_DEVARTSUPPORT_PORTAL_MANAGE')); ?>">
            <?php if ($status === 'closed') : ?>
                <div class="devart-support-portal-actions">
                    <form
                        action="<?php echo $escape(PortalRoute::_('index.php?option=com_devartsupport&task=ticket.reopen')); ?>"
                        method="post"
                        class="d-inline"
                    >
                        <input type="hidden" name="id" value="<?php echo (int) $ticket->id; ?>">
                        <button type="submit" class="btn btn-primary">
                            <?php echo $escape(Text::_('COM_DEVARTSUPPORT_PORTAL_REOPEN_TICKET')); ?>
                        </button>
                        <?php echo $token; ?>
                    </form>
                </div>
            <?php else : ?>
                <form
                    action="<?php echo $escape(PortalRoute::_('index.php?option=com_devartsupport&task=ticket.savePriority')); ?>"
                    method="post"
                    class="devart-support-portal-priority-form"
                >
                    <input type="hidden" name="id" value="<?php echo (int) $ticket->id; ?>">
                    <label class="form-label" for="portal-priority">
                        <?php echo $escape(Text::_('COM_DEVARTSUPPORT_FIELD_PRIORITY')); ?>
                    </label>
                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        <select name="priority" id="portal-priority" class="form-select w-auto">
                            <?php foreach ($priorityLabels as $value => $label) : ?>
                                <option value="<?php echo $escape($value); ?>"<?php echo $priority === $value ? ' selected' : ''; ?>>
                                    <?php echo $escape($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-outline-secondary">
                            <?php echo $escape(Text::_('COM_DEVARTSUPPORT_PORTAL_SAVE_PRIORITY')); ?>
                        </button>
                    </div>
                    <?php echo $token; ?>
                </form>

                <form
                    action="<?php echo $escape(PortalRoute::_('index.php?option=com_devartsupport&task=ticket.close')); ?>"
                    method="post"
                    class="devart-support-portal-close-form"
                    onsubmit="return window.confirm(<?php echo htmlspecialchars(json_encode(Text::_('COM_DEVARTSUPPORT_PORTAL_CLOSE_CONFIRM'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8'); ?>);"
                >
                    <input type="hidden" name="id" value="<?php echo (int) $ticket->id; ?>">
                    <button type="submit" class="btn btn-sm btn-outline-secondary devart-support-portal-close">
                        <?php echo $escape(Text::_('COM_DEVARTSUPPORT_PORTAL_CLOSE_TICKET')); ?>
                    </button>
                    <?php echo $token; ?>
                </form>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <?php if ($this->messagesTruncated) : ?>
        <p class="alert alert-info">
            <?php echo $escape(Text::sprintf(
                'COM_DEVARTSUPPORT_THREAD_TRUNCATED',
                count($this->messages),
                $this->messagesTotal
            )); ?>
            <?php if ($this->messagesNextLimit > $this->messagesLimit) : ?>
                <br>
                <a href="<?php echo $escape(PortalRoute::_(
                    'index.php?option=com_devartsupport&view=ticket&id=' . (int) $ticket->id
                    . '&msg_limit=' . $this->messagesNextLimit
                )); ?>">
                    <?php echo $escape(Text::_('COM_DEVARTSUPPORT_THREAD_LOAD_OLDER')); ?>
                </a>
            <?php endif; ?>
        </p>
    <?php endif; ?>

    <section class="devart-support-portal-thread" aria-label="<?php echo $escape(Text::_('COM_DEVARTSUPPORT_MESSAGES')); ?>">
        <?php
        $ownerId = (int) ($ticket->user_id ?? 0);
        foreach ($this->messages as $message) :
            $authorId = (int) ($message->user_id ?? 0);
            $isStaff = $authorId < 1 || ($ownerId > 0 && $authorId !== $ownerId);
            $author = UserDisplay::messageAuthor($authorId);
            $messageClass = 'devart-support-portal-message'
                . ($isStaff ? ' devart-support-portal-message--staff' : '');
            ?>
            <article class="<?php echo $escape($messageClass); ?>">
                <header>
                    <strong><?php echo $escape($author); ?></strong>
                    <?php if ($isStaff) : ?>
                        <span class="devart-support-portal-staff-label"><?php echo $escape(Text::_('COM_DEVARTSUPPORT_STAFF')); ?></span>
                    <?php endif; ?>
                    <span class="text-muted">
                        <?php echo $escape(PortalFrontSettings::formatDate((string) $message->created)); ?>
                    </span>
                </header>
                <div><?php echo nl2br($escape((string) $message->body)); ?></div>
            </article>
        <?php endforeach; ?>
    </section>

    <?php if ($this->attachments !== []) : ?>
        <section class="devart-support-portal-attachments mt-4" aria-label="<?php echo $escape(Text::_('COM_DEVARTSUPPORT_ATTACHMENTS')); ?>">
            <h2><?php echo $escape(Text::_('COM_DEVARTSUPPORT_ATTACHMENTS')); ?></h2>
            <?php if ($this->attachmentsRetentionEnabled) : ?>
                <p class="alert alert-warning">
                    <?php echo $escape(Text::sprintf(
                        'COM_DEVARTSUPPORT_ATTACHMENTS_RETENTION_WARNING',
                        $this->attachmentsRetentionDays
                    )); ?>
                </p>
            <?php endif; ?>
            <ul class="devart-support-portal-attachment-list">
                <?php foreach ($this->attachments as $attachment) : ?>
                    <?php $attachmentId = (int) $attachment->id; ?>
                    <li>
                        <form
                            action="<?php echo $escape(PortalRoute::_('index.php?option=com_devartsupport&task=ticket.download')); ?>"
                            method="post"
                            class="d-inline"
                        >
                            <input type="hidden" name="attachment_id" value="<?php echo $attachmentId; ?>">
                            <input type="hidden" name="id" value="<?php echo (int) $ticket->id; ?>">
                            <?php echo HTMLHelper::_('form.token'); ?>
                            <button type="submit" class="btn btn-link p-0 align-baseline">
                                <?php echo $escape((string) $attachment->filename); ?>
                            </button>
                        </form>
                        <span class="text-muted"><?php echo (int) $attachment->size; ?> B</span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>

    <?php if ($this->canReply) : ?>
        <section class="devart-support-portal-reply mt-4">
            <h2><?php echo $escape(Text::_('COM_DEVARTSUPPORT_FIELD_REPLY')); ?></h2>
            <form
                action="<?php echo $escape(PortalRoute::_('index.php?option=com_devartsupport&task=ticket.reply')); ?>"
                method="post"
                name="portalReplyForm"
                id="portalReplyForm"
                class="form-validate"
                enctype="multipart/form-data"
            >
                <?php echo $this->form->renderField('id'); ?>
                <?php echo $this->form->renderField('reply'); ?>
                <?php if ($this->attachmentsUploadAllowed) : ?>
                    <div class="mb-3">
                        <label class="form-label" for="portal-reply-attachments">
                            <?php echo $escape(Text::_('COM_DEVARTSUPPORT_FIELD_ATTACHMENTS')); ?>
                        </label>
                        <input
                            id="portal-reply-attachments"
                            type="file"
                            name="attachments[]"
                            class="form-control"
                            multiple
                            accept="<?php echo $escape($this->attachmentAccept); ?>"
                        >
                        <div class="form-text">
                            <?php echo $escape(Text::sprintf(
                                'COM_DEVARTSUPPORT_FIELD_ATTACHMENTS_DESC',
                                $this->attachmentMaxFiles,
                                $this->attachmentMaxMegabytes
                            )); ?>
                        </div>
                        <?php if ($this->attachmentsRetentionEnabled) : ?>
                            <div class="form-text text-warning">
                                <?php echo $escape(Text::sprintf(
                                    'COM_DEVARTSUPPORT_ATTACHMENTS_RETENTION_WARNING',
                                    $this->attachmentsRetentionDays
                                )); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <button type="submit" class="btn btn-primary">
                    <?php echo $escape(Text::_('COM_DEVARTSUPPORT_PORTAL_SUBMIT_REPLY')); ?>
                </button>
                <?php echo HTMLHelper::_('form.token'); ?>
            </form>
        </section>
    <?php elseif ($this->canManage && $status === 'closed') : ?>
        <p class="alert alert-secondary mt-4">
            <?php echo $escape(Text::_('COM_DEVARTSUPPORT_PORTAL_CLOSED_HINT')); ?>
        </p>
    <?php endif; ?>
</div>
