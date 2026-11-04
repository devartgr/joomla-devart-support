<?php
/**
 * @package     DevArt Support
 * @subpackage  com_devartsupport
 *
 * @copyright   Copyright (C) 2026 Stathopoulos Kostas - DevArt. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

declare(strict_types=1);

/** @var \Devart\Component\Devartsupport\Administrator\View\Settings\HtmlView $this */

defined('_JEXEC') or die;

use Devart\Component\Devartsupport\Administrator\Helper\AdminChrome;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

$wa = Factory::getApplication()->getDocument()->getWebAssetManager();
$wa->useScript('form.validate');
$wa->useScript('keepalive');

$escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
?>
<div class="com-devartsupport">
<div class="devart-support-admin-wrap" data-devart-support-view="settings">
    <?php echo AdminChrome::header(AdminChrome::IMAGE_TOOLS, Text::_('COM_DEVARTSUPPORT_SETTINGS')); ?>
    <p class="devart-support-hub-intro"><?php echo $escape(Text::_('COM_DEVARTSUPPORT_SETTINGS_INTRO')); ?></p>

    <form
        action="<?php echo $escape(Route::_('index.php?option=com_devartsupport&view=settings', false)); ?>"
        method="post"
        name="adminForm"
        id="adminForm"
        class="form-validate"
    >
        <div class="row">
            <div class="col-lg-8">
                <?php echo $this->form->renderFieldset('email'); ?>
            </div>
        </div>

        <input type="hidden" name="task" value="">
        <?php echo HTMLHelper::_('form.token'); ?>
    </form>

    <?php echo AdminChrome::footer(); ?>
</div>
</div>
