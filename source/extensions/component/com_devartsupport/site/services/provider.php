<?php
/**
 * @package     DevArt Support
 * @subpackage  com_devartsupport
 *
 * @copyright   Copyright (C) 2026 Stathopoulos Kostas - DevArt. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

declare(strict_types=1);

defined('_JEXEC') or die;

use Devart\Component\Devartsupport\Administrator\Service\CategoryService;
use Devart\Component\Devartsupport\Administrator\Service\Storage\AttachmentStorage;
use Devart\Component\Devartsupport\Administrator\Service\Storage\CategoryStorage;
use Devart\Component\Devartsupport\Administrator\Service\Storage\MailQueueStorage;
use Devart\Component\Devartsupport\Administrator\Service\Storage\MessageStorage;
use Devart\Component\Devartsupport\Administrator\Service\Storage\TicketStorage;
use Devart\Component\Devartsupport\Administrator\Service\PortalTicketService;
use Devart\Component\Devartsupport\Administrator\Service\TicketMailService;
use Devart\Component\Devartsupport\Site\Extension\DevartsupportComponent;
use Joomla\CMS\Component\Router\RouterFactoryInterface;
use Joomla\CMS\Dispatcher\ComponentDispatcherFactoryInterface;
use Joomla\CMS\Extension\ComponentInterface;
use Joomla\CMS\Extension\Service\Provider\ComponentDispatcherFactory;
use Joomla\CMS\Extension\Service\Provider\MVCFactory;
use Joomla\CMS\Extension\Service\Provider\RouterFactory;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\Database\DatabaseInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;

return new class implements ServiceProviderInterface {
    public function register(Container $container): void
    {
        $container->registerServiceProvider(new MVCFactory('Devart\\Component\\Devartsupport'));
        $container->registerServiceProvider(new ComponentDispatcherFactory('Devart\\Component\\Devartsupport'));
        $container->registerServiceProvider(new RouterFactory('Devart\\Component\\Devartsupport'));

        $container->set(TicketStorage::class, static function (Container $container): TicketStorage {
            return new TicketStorage($container->get(DatabaseInterface::class));
        });
        $container->set(MessageStorage::class, static function (Container $container): MessageStorage {
            return new MessageStorage($container->get(DatabaseInterface::class));
        });
        $container->set(CategoryStorage::class, static function (Container $container): CategoryStorage {
            return new CategoryStorage($container->get(DatabaseInterface::class));
        });
        $container->set(AttachmentStorage::class, static function (Container $container): AttachmentStorage {
            return new AttachmentStorage($container->get(DatabaseInterface::class));
        });
        $container->set(MailQueueStorage::class, static function (Container $container): MailQueueStorage {
            return new MailQueueStorage($container->get(DatabaseInterface::class));
        });
        $container->set(TicketMailService::class, static function (Container $container): TicketMailService {
            return new TicketMailService($container->get(MailQueueStorage::class));
        });
        $container->set(PortalTicketService::class, static function (Container $container): PortalTicketService {
            return new PortalTicketService(
                $container->get(TicketStorage::class),
                $container->get(MessageStorage::class),
                $container->get(CategoryStorage::class),
                $container->get(AttachmentStorage::class),
                $container->get(TicketMailService::class),
                $container->get(MailQueueStorage::class)
            );
        });
        $container->set(CategoryService::class, static function (Container $container): CategoryService {
            return new CategoryService(
                $container->get(CategoryStorage::class),
                $container->get(TicketStorage::class)
            );
        });

        $container->set(ComponentInterface::class, static function (Container $container) {
            $component = new DevartsupportComponent($container->get(ComponentDispatcherFactoryInterface::class));
            $component->setMVCFactory($container->get(MVCFactoryInterface::class));
            $component->setRouterFactory($container->get(RouterFactoryInterface::class));
            $component->setPortalTicketService($container->get(PortalTicketService::class));
            $component->setCategoryService($container->get(CategoryService::class));
            $component->setTicketMailService($container->get(TicketMailService::class));

            return $component;
        });
    }
};
