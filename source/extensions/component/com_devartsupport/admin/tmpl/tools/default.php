<?php
/**
 * @package     DevArt Support
 * @subpackage  com_devartsupport
 *
 * @copyright   Copyright (C) 2026 Stathopoulos Kostas - DevArt. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

declare(strict_types=1);

/** @var \Devart\Component\Devartsupport\Administrator\View\Tools\HtmlView $this */

defined('_JEXEC') or die;

use Devart\Component\Devartsupport\Administrator\Helper\AdminChrome;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

$escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
$canAdmin = $this->getCurrentUser()->authorise('core.admin', 'com_devartsupport');

$hubs = [
    [
        'icon'     => 'icon-envelope',
        'mod'      => 'settings',
        'title'    => 'COM_DEVARTSUPPORT_TOOLS_HUB_SETTINGS_TITLE',
        'desc'     => 'COM_DEVARTSUPPORT_TOOLS_HUB_SETTINGS_DESC',
        'btn'      => 'COM_DEVARTSUPPORT_TOOLS_HUB_SETTINGS_BTN',
        'btnClass' => 'btn-warning',
        'url'      => Route::_('index.php?option=com_devartsupport&view=settings', false),
        'admin'    => true,
    ],
    [
        'icon'     => 'icon-options',
        'mod'      => 'options',
        'title'    => 'COM_DEVARTSUPPORT_TOOLS_HUB_OPTIONS_TITLE',
        'desc'     => 'COM_DEVARTSUPPORT_TOOLS_HUB_OPTIONS_DESC',
        'btn'      => 'COM_DEVARTSUPPORT_TOOLS_HUB_OPTIONS_BTN',
        'btnClass' => 'btn-secondary',
        'url'      => Route::_('index.php?option=com_config&view=component&component=com_devartsupport', false),
        'admin'    => true,
    ],
];
?>
<div class="com-devartsupport">
<div class="devart-support-admin-wrap" data-devart-support-view="tools">
    <?php echo AdminChrome::header(AdminChrome::IMAGE_TOOLS, Text::_('COM_DEVARTSUPPORT_TOOLS')); ?>
    <p class="devart-support-hub-intro"><?php echo $escape(Text::_('COM_DEVARTSUPPORT_TOOLS_INTRO')); ?></p>

    <div class="row g-4">
        <?php foreach ($hubs as $hub) : ?>
            <?php if (!empty($hub['admin']) && !$canAdmin) : ?>
                <?php continue; ?>
            <?php endif; ?>
            <div class="col-md-4">
                <div class="card p-4 h-100 devart-support-hub-action-card">
                    <div class="devart-support-hub-action-body">
                        <span class="devart-support-hub-icon devart-support-hub-icon--<?php echo $escape($hub['mod']); ?>" aria-hidden="true">
                            <span class="<?php echo $escape($hub['icon']); ?>"></span>
                        </span>
                        <h3 class="devart-support-hub-action-title"><?php echo $escape(Text::_($hub['title'])); ?></h3>
                        <p class="devart-support-hub-action-desc"><?php echo $escape(Text::_($hub['desc'])); ?></p>
                    </div>
                    <a class="btn <?php echo $escape($hub['btnClass']); ?> btn-lg devart-support-hub-action-btn" href="<?php echo $escape($hub['url']); ?>">
                        <?php echo $escape(Text::_($hub['btn'])); ?>
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="mt-4">
        <h3><?php echo $escape(Text::_('COM_DEVARTSUPPORT_TOOLS_STORAGE_HEALTH_TITLE')); ?></h3>
        <?php if ($this->storageHealth['ok']) : ?>
            <div class="alert alert-success"><?php echo $escape(Text::_('COM_DEVARTSUPPORT_TOOLS_STORAGE_HEALTH_OK')); ?></div>
        <?php else : ?>
            <div class="alert alert-warning"><?php echo $escape(Text::_('COM_DEVARTSUPPORT_TOOLS_STORAGE_HEALTH_FAIL')); ?></div>
        <?php endif; ?>
    </div>

    <div class="mt-4">
        <h3><?php echo $escape(Text::_('COM_DEVARTSUPPORT_TOOLS_MAIL_QUEUE_TITLE')); ?></h3>
        <div class="alert alert-info">
            <?php echo $escape(Text::sprintf('COM_DEVARTSUPPORT_TOOLS_MAIL_QUEUE_PENDING', $this->mailQueuePending)); ?>
        </div>
    </div>

    <?php echo AdminChrome::footer(); ?>
</div>
</div>
