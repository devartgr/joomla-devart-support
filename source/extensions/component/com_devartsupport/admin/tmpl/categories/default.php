<?php
/**
 * @package     DevArt Support
 * @subpackage  com_devartsupport
 *
 * @copyright   Copyright (C) 2026 Stathopoulos Kostas - DevArt. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

declare(strict_types=1);

/** @var \Devart\Component\Devartsupport\Administrator\View\Categories\HtmlView $this */

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
?>
<div class="com-devartsupport">
<div class="devart-support-admin-wrap" data-devart-support-view="categories">
    <?php echo AdminChrome::header(AdminChrome::IMAGE_CATEGORIES, Text::_('COM_DEVARTSUPPORT_CATEGORIES')); ?>
<form
    action="<?php echo $escape(Route::_('index.php?option=com_devartsupport&view=categories', false)); ?>"
    method="post"
    name="adminForm"
    id="adminForm"
>
        <div class="row mb-3">
            <div class="col-md-4">
                <input
                    type="text"
                    name="filter_search"
                    class="form-control"
                    value="<?php echo $escape($this->search); ?>"
                    placeholder="<?php echo $escape(Text::_('COM_DEVARTSUPPORT_FILTER_CATEGORY_SEARCH')); ?>"
                    aria-label="<?php echo $escape(Text::_('COM_DEVARTSUPPORT_FILTER_CATEGORY_SEARCH')); ?>"
                >
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary"><?php echo $escape(Text::_('JSEARCH_FILTER')); ?></button>
            </div>
        </div>

        <table class="table">
            <thead>
                <tr>
                    <th style="width:1%;">
                        <input type="checkbox" name="checkall-toggle" value="" data-devart-support-checkall>
                    </th>
                    <th style="width:6%;"><?php echo $escape(Text::_('COM_DEVARTSUPPORT_HEADING_ID')); ?></th>
                    <th><?php echo $escape(Text::_('COM_DEVARTSUPPORT_HEADING_TITLE')); ?></th>
                    <th style="width:12%;"><?php echo $escape(Text::_('COM_DEVARTSUPPORT_HEADING_PUBLISHED')); ?></th>
                    <th style="width:14%;"><?php echo $escape(Text::_('COM_DEVARTSUPPORT_HEADING_ATTACHMENTS')); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php if ($this->items === []) : ?>
                <tr>
                    <td colspan="5"><?php echo $escape(Text::_('COM_DEVARTSUPPORT_NO_CATEGORIES')); ?></td>
                </tr>
            <?php endif; ?>
            <?php foreach ($this->items as $i => $item) : ?>
                <tr>
                    <td><?php echo HTMLHelper::_('grid.id', $i, (int) $item->id); ?></td>
                    <td><?php echo (int) $item->id; ?></td>
                    <td>
                        <a href="<?php echo $escape(Route::_('index.php?option=com_devartsupport&task=category.edit&id=' . (int) $item->id, false)); ?>">
                            <?php echo $escape((string) $item->title); ?>
                        </a>
                    </td>
                    <td><?php echo $escape(Text::_((int) $item->published === 1 ? 'JYES' : 'JNO')); ?></td>
                    <td><?php echo $escape(Text::_((int) ($item->attachments_enabled ?? 1) === 1 ? 'JYES' : 'JNO')); ?></td>
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
