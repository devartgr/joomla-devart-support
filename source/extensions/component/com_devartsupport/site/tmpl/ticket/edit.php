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

use Devart\Component\Devartsupport\Site\Helper\PortalRoute;
use Devart\Component\Devartsupport\Site\Helper\SiteAssets;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

$escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
?>
<div class="com-devartsupport-portal <?php echo $escape(SiteAssets::themeClass()); ?>">
    <header class="devart-support-portal-head">
        <h1><?php echo $escape(Text::_('COM_DEVARTSUPPORT_PORTAL_TICKET_NEW_HEADING')); ?></h1>
        <p class="devart-support-portal-intro"><?php echo $escape(Text::_('COM_DEVARTSUPPORT_PORTAL_TICKET_NEW_INTRO')); ?></p>
    </header>

    <form
        action="<?php echo $escape(PortalRoute::_('index.php?option=com_devartsupport&task=ticket.save')); ?>"
        method="post"
        name="portalTicketForm"
        id="portalTicketForm"
        class="devart-support-portal-form form-validate"
        enctype="multipart/form-data"
    >
        <?php echo $this->form->renderField('subject'); ?>
        <?php echo $this->form->renderField('category_id'); ?>
        <?php echo $this->form->renderField('priority'); ?>
        <?php echo $this->form->renderField('message'); ?>

        <?php if ($this->attachmentsUploadAllowed) : ?>
            <div class="mb-3">
                <label class="form-label" for="portal-ticket-attachments">
                    <?php echo $escape(Text::_('COM_DEVARTSUPPORT_FIELD_ATTACHMENTS')); ?>
                </label>
                <input
                    id="portal-ticket-attachments"
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
            </div>
        <?php endif; ?>

        <div class="devart-support-portal-actions">
            <button type="submit" class="btn btn-primary">
                <?php echo $escape(Text::_('COM_DEVARTSUPPORT_PORTAL_SUBMIT_TICKET')); ?>
            </button>
            <a class="btn btn-secondary" href="<?php echo $escape(PortalRoute::_('index.php?option=com_devartsupport&view=tickets')); ?>">
                <?php echo $escape(Text::_('JCANCEL')); ?>
            </a>
        </div>

        <?php echo HTMLHelper::_('form.token'); ?>
    </form>
</div>
