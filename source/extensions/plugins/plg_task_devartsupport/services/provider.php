<?php
/**
 * @package     DevArt Support
 * @subpackage  plg_task_devartsupport
 *
 * @copyright   Copyright (C) 2026 Stathopoulos Kostas - DevArt. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

declare(strict_types=1);

defined('_JEXEC') or die;

use Devart\Plugin\Task\Devartsupport\Extension\Devartsupport;
use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\DispatcherInterface;

return new class () implements ServiceProviderInterface {
    public function register(Container $container): void
    {
        $container->set(
            PluginInterface::class,
            static function (Container $container): PluginInterface {
                $plugin = new Devartsupport(
                    $container->get(DispatcherInterface::class),
                    (array) PluginHelper::getPlugin('task', 'devartsupport')
                );
                $plugin->setApplication(Factory::getApplication());

                return $plugin;
            }
        );
    }
};
