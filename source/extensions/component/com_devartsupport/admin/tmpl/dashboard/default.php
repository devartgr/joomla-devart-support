<?php
/**
 * @package     DevArt Support
 * @subpackage  com_devartsupport
 *
 * @copyright   Copyright (C) 2026 Stathopoulos Kostas - DevArt. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

declare(strict_types=1);

/** @var \Devart\Component\Devartsupport\Administrator\View\Dashboard\HtmlView $this */

defined('_JEXEC') or die;

use Devart\Component\Devartsupport\Administrator\Helper\AdminChrome;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

$escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
$hubs = [
    [
        'icon'     => 'icon-list',
        'mod'      => 'tickets',
        'title'    => 'COM_DEVARTSUPPORT_DASHBOARD_HUB_TICKETS_TITLE',
        'desc'     => 'COM_DEVARTSUPPORT_DASHBOARD_HUB_TICKETS_DESC',
        'btn'      => 'COM_DEVARTSUPPORT_DASHBOARD_HUB_TICKETS_BTN',
        'btnClass' => 'btn-primary',
        'url'      => Route::_('index.php?option=com_devartsupport&view=tickets', false),
    ],
    [
        'icon'     => 'icon-folder',
        'mod'      => 'categories',
        'title'    => 'COM_DEVARTSUPPORT_DASHBOARD_HUB_CATEGORIES_TITLE',
        'desc'     => 'COM_DEVARTSUPPORT_DASHBOARD_HUB_CATEGORIES_DESC',
        'btn'      => 'COM_DEVARTSUPPORT_DASHBOARD_HUB_CATEGORIES_BTN',
        'btnClass' => 'btn-danger',
        'url'      => Route::_('index.php?option=com_devartsupport&view=categories', false),
    ],
    [
        'icon'     => 'icon-cog',
        'mod'      => 'tools',
        'title'    => 'COM_DEVARTSUPPORT_DASHBOARD_HUB_TOOLS_TITLE',
        'desc'     => 'COM_DEVARTSUPPORT_DASHBOARD_HUB_TOOLS_DESC',
        'btn'      => 'COM_DEVARTSUPPORT_DASHBOARD_HUB_TOOLS_BTN',
        'btnClass' => 'btn-warning',
        'url'      => Route::_('index.php?option=com_devartsupport&view=tools', false),
    ],
];
?>
<div class="com-devartsupport">
<div class="devart-support-admin-wrap" data-devart-support-view="dashboard">
    <?php echo AdminChrome::header(AdminChrome::IMAGE_DASHBOARD, Text::_('COM_DEVARTSUPPORT_DASHBOARD')); ?>
    <p class="devart-support-hub-intro"><?php echo $escape(Text::_('COM_DEVARTSUPPORT_DASHBOARD_INTRO')); ?></p>

    <div class="row g-4">
        <?php foreach ($hubs as $hub) : ?>
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

    <?php echo AdminChrome::footer(); ?>
</div>
</div>
