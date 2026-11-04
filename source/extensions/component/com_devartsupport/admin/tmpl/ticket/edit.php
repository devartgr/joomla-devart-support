<?php
/**
 * @package     DevArt Support
 * @subpackage  com_devartsupport
 *
 * @copyright   Copyright (C) 2026 Stathopoulos Kostas - DevArt. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

declare(strict_types=1);

/** @var \Devart\Component\Devartsupport\Administrator\View\Ticket\HtmlView $this */

defined('_JEXEC') or die;

use Devart\Component\Devartsupport\Administrator\Helper\AdminChrome;
use Devart\Component\Devartsupport\Administrator\Helper\UserDisplay;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

Factory::getApplication()->getDocument()->getWebAssetManager()->useScript('form.validate');

$escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
$ticketId = (int) $this->form->getValue('id');
$attachmentFlags = [
    'global'     => $this->attachmentsGloballyEnabled ? 1 : 0,
    'categories' => $this->attachmentCategoryFlags,
];
?>
<div class="com-devartsupport">
<form
    action="<?php echo $escape(Route::_('index.php?option=com_devartsupport&view=ticket&layout=edit&id=' . $ticketId, false)); ?>"
    method="post"
    name="adminForm"
    id="adminForm"
    class="form-validate"
    enctype="multipart/form-data"
>
    <div
        class="devart-support-admin-wrap"
        data-devart-support-view="ticket"
        data-devart-support-attachments="<?php echo $escape(json_encode($attachmentFlags, JSON_THROW_ON_ERROR)); ?>"
    >
        <div class="row">
            <div class="<?php echo $this->isNew ? 'col-12' : 'col-lg-5'; ?>">
                <?php echo $this->form->renderFieldset('details'); ?>
                <div class="mb-3" data-devart-support-upload <?php echo $this->attachmentsUploadAllowed ? '' : 'hidden'; ?>>
                    <label class="form-label" for="ticket-attachments"><?php echo $escape(Text::_('COM_DEVARTSUPPORT_FIELD_ATTACHMENTS')); ?></label>
                    <input
                        id="ticket-attachments"
                        type="file"
                        name="attachments[]"
                        class="form-control"
                        multiple
                        accept="<?php echo $escape(\Devart\Component\Devartsupport\Administrator\Helper\AttachmentSettings::acceptAttribute()); ?>"
                    >
                    <div class="form-text">
                        <?php echo $escape(Text::sprintf('COM_DEVARTSUPPORT_FIELD_ATTACHMENTS_DESC', $this->attachmentMaxFiles, $this->attachmentMaxMegabytes)); ?>
                    </div>
                    <?php if ($this->attachmentsRetentionEnabled) : ?>
                        <div class="form-text text-warning">
                            <?php echo $escape(Text::sprintf('COM_DEVARTSUPPORT_ATTACHMENTS_RETENTION_WARNING', $this->attachmentsRetentionDays)); ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="alert alert-info" data-devart-support-upload-disabled <?php echo $this->attachmentsUploadAllowed ? 'hidden' : ''; ?>>
                    <?php echo $escape(Text::_(
                        $this->attachmentsGloballyEnabled
                            ? 'COM_DEVARTSUPPORT_ATTACHMENTS_DISABLED_CATEGORY'
                            : 'COM_DEVARTSUPPORT_ATTACHMENTS_DISABLED'
                    )); ?>
                </div>
            </div>
            <?php if (!$this->isNew) : ?>
                <div class="col-lg-7">
                    <h3><?php echo $escape(Text::_('COM_DEVARTSUPPORT_MESSAGES')); ?></h3>
                    <?php if ($this->messagesTruncated) : ?>
                        <p class="text-muted mb-2">
                            <?php echo $escape(Text::sprintf(
                                'COM_DEVARTSUPPORT_THREAD_TRUNCATED',
                                count($this->messages),
                                $this->messagesTotal
                            )); ?>
                        </p>
                        <?php if ($this->messagesNextLimit > $this->messagesLimit) : ?>
                            <p class="mb-3">
                                <a
                                    class="btn btn-sm btn-outline-secondary"
                                    href="<?php echo $escape(Route::_(
                                        'index.php?option=com_devartsupport&task=ticket.edit&id=' . $ticketId
                                        . '&msg_limit=' . $this->messagesNextLimit,
                                        false
                                    )); ?>"
                                >
                                    <?php echo $escape(Text::_('COM_DEVARTSUPPORT_THREAD_LOAD_OLDER')); ?>
                                </a>
                            </p>
                        <?php endif; ?>
                    <?php endif; ?>
                    <div class="devart-support-thread">
                        <?php
                        $ownerId = (int) $this->form->getValue('user_id');
                        foreach ($this->messages as $message) :
                            $authorId = (int) ($message->user_id ?? 0);
                            $isStaff = $authorId < 1 || ($ownerId > 0 && $authorId !== $ownerId);
                            $author = UserDisplay::messageAuthor($authorId);
                            $messageClass = 'devart-support-message'
                                . ($isStaff ? ' devart-support-message--staff' : '');
                            ?>
                            <article class="<?php echo $escape($messageClass); ?>">
                                <header>
                                    <strong><?php echo $escape($author); ?></strong>
                                    <?php if ($isStaff) : ?>
                                        <span class="devart-support-staff-label"><?php echo $escape(Text::_('COM_DEVARTSUPPORT_STAFF')); ?></span>
                                    <?php endif; ?>
                                    <span class="text-muted"><?php echo $escape((string) $message->created); ?></span>
                                </header>
                                <div><?php echo nl2br($escape((string) $message->body)); ?></div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <input type="hidden" name="task" value="">
    <?php if (!$this->isNew) : ?>
        <input
            type="hidden"
            name="jform[expected_version]"
            value="<?php echo (int) $this->form->getValue('expected_version'); ?>"
        >
    <?php endif; ?>
    <?php echo HTMLHelper::_('form.token'); ?>
</form>
<?php if ($this->attachments !== []) : ?>
    <div class="devart-support-admin-wrap">
        <h3><?php echo $escape(Text::_('COM_DEVARTSUPPORT_ATTACHMENTS')); ?></h3>
        <?php if ($this->attachmentsRetentionEnabled) : ?>
            <p class="alert alert-warning">
                <?php echo $escape(Text::sprintf('COM_DEVARTSUPPORT_ATTACHMENTS_RETENTION_WARNING', $this->attachmentsRetentionDays)); ?>
            </p>
        <?php endif; ?>
        <ul class="devart-support-attachments">
            <?php foreach ($this->attachments as $attachment) : ?>
                <?php
                $attachmentId = (int) $attachment->id;
                $downloadUrl = Route::_(
                    'index.php?option=com_devartsupport&task=ticket.download&attachment_id='
                    . $attachmentId
                    . '&ticket_id='
                    . $ticketId,
                    false
                );
                $deleteUrl = Route::_(
                    'index.php?option=com_devartsupport&task=ticket.deleteattachment&attachment_id='
                    . $attachmentId
                    . '&ticket_id='
                    . $ticketId,
                    false
                );
                ?>
                <li>
                    <form
                        action="<?php echo $escape($downloadUrl); ?>"
                        method="post"
                        class="d-inline"
                    >
                        <input type="hidden" name="option" value="com_devartsupport">
                        <input type="hidden" name="task" value="ticket.download">
                        <input type="hidden" name="attachment_id" value="<?php echo $attachmentId; ?>">
                        <input type="hidden" name="ticket_id" value="<?php echo $ticketId; ?>">
                        <?php echo HTMLHelper::_('form.token'); ?>
                        <button type="submit" class="btn btn-link p-0 align-baseline">
                            <?php echo $escape((string) $attachment->filename); ?>
                        </button>
                    </form>
                    <span class="text-muted"><?php echo (int) $attachment->size; ?> B</span>
                    <form
                        action="<?php echo $escape($deleteUrl); ?>"
                        method="post"
                        class="d-inline"
                    >
                        <input type="hidden" name="option" value="com_devartsupport">
                        <input type="hidden" name="task" value="ticket.deleteattachment">
                        <input type="hidden" name="attachment_id" value="<?php echo $attachmentId; ?>">
                        <input type="hidden" name="ticket_id" value="<?php echo $ticketId; ?>">
                        <?php echo HTMLHelper::_('form.token'); ?>
                        <button type="submit" class="btn btn-sm btn-outline-danger">
                            <?php echo $escape(Text::_('JACTION_DELETE')); ?>
                        </button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php echo AdminChrome::footer(); ?>
    </div>
<?php else : ?>
    <div class="devart-support-admin-wrap">
        <?php echo AdminChrome::footer(); ?>
    </div>
<?php endif; ?>
</div>
