<?php
/**
 * @package     DevArt Support
 * @subpackage  com_devartsupport
 *
 * @copyright   Copyright (C) 2026 Stathopoulos Kostas - DevArt. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Devart\Component\Devartsupport\Administrator\View\Tools;

defined('_JEXEC') or die;

use Devart\Component\Devartsupport\Administrator\Helper\AdminAssets;
use Devart\Component\Devartsupport\Administrator\Helper\PrivateAttachmentPath;
use Devart\Component\Devartsupport\Administrator\Service\Storage\MailQueueStorage;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\Database\DatabaseInterface;

class HtmlView extends BaseHtmlView
{
    /** @var array{ok: bool, writable: bool, outside_webroot: bool} */
    public array $storageHealth = [
        'ok'              => false,
        'writable'        => false,
        'outside_webroot' => false,
    ];

    public int $mailQueuePending = 0;

    public function display($tpl = null): void
    {
        if (!$this->getCurrentUser()->authorise('core.manage', 'com_devartsupport')) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        ToolbarHelper::title(Text::_('COM_DEVARTSUPPORT_TOOLS'), 'cog');

        if ($this->getCurrentUser()->authorise('core.admin', 'com_devartsupport')) {
            ToolbarHelper::preferences('com_devartsupport');
        }

        $this->storageHealth = PrivateAttachmentPath::health();

        try {
            $db = Factory::getContainer()->get(DatabaseInterface::class);
            $this->mailQueuePending = (new MailQueueStorage($db))->countPending();
        } catch (\Throwable) {
            $this->mailQueuePending = 0;
        }

        AdminAssets::load();
        parent::display($tpl);
    }
}
