<?php
/**
 * @package     DevArt Support
 * @subpackage  com_devartsupport
 *
 * @copyright   Copyright (C) 2026 Stathopoulos Kostas - DevArt. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

declare(strict_types=1);

/** @var \Devart\Component\Devartsupport\Administrator\View\Category\HtmlView $this */

defined('_JEXEC') or die;

use Devart\Component\Devartsupport\Administrator\Helper\AdminChrome;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

Factory::getApplication()->getDocument()->getWebAssetManager()->useScript('form.validate');

$escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
?>
<div class="com-devartsupport">
<form
    action="<?php echo $escape(Route::_('index.php?option=com_devartsupport&view=category&layout=edit&id=' . (int) $this->form->getValue('id'), false)); ?>"
    method="post"
    name="adminForm"
    id="adminForm"
    class="form-validate"
>
    <div class="devart-support-admin-wrap" data-devart-support-view="category">
        <div class="row">
            <div class="col-lg-6">
                <?php echo $this->form->renderFieldset('details'); ?>
                <?php if (!$this->attachmentsGloballyEnabled) : ?>
                    <div class="alert alert-info">
                        <?php echo $escape(Text::_('COM_DEVARTSUPPORT_FIELD_CATEGORY_ATTACHMENTS_LOCKED')); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php echo AdminChrome::footer(); ?>
    </div>

    <input type="hidden" name="task" value="">
    <?php echo HTMLHelper::_('form.token'); ?>
</form>
</div>
