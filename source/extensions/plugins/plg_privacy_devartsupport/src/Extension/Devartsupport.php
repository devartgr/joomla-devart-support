<?php
/**
 * @package     DevArt Support
 * @subpackage  plg_privacy_devartsupport
 *
 * @copyright   Copyright (C) 2026 Stathopoulos Kostas - DevArt. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Devart\Plugin\Privacy\Devartsupport\Extension;

defined('_JEXEC') or die;

use Devart\Component\Devartsupport\Administrator\Helper\TicketDesk;
use Joomla\CMS\Event\Privacy\ExportRequestEvent;
use Joomla\CMS\Event\Privacy\RemoveDataEvent;
use Joomla\Event\SubscriberInterface;

final class Devartsupport extends \Joomla\Component\Privacy\Administrator\Plugin\PrivacyPlugin implements SubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            'onPrivacyExportRequest' => 'onPrivacyExportRequest',
            'onPrivacyRemoveData'    => 'onPrivacyRemoveData',
        ];
    }

    public function onPrivacyExportRequest(ExportRequestEvent $event): void
    {
        $request = $event->getRequest();
        $user = $event->getUser();
        $email = trim((string) ($request->email ?? ''));
        $userId = $user !== null ? (int) $user->id : 0;

        if ($email === '' && $userId < 1) {
            return;
        }

        if (!$this->componentAvailable()) {
            return;
        }

        $payload = TicketDesk::service()->privacyCollect($email, $userId);
        $domains = [];

        $ticketsDomain = $this->createDomain('devartsupport_tickets', 'PLG_PRIVACY_DEVARTSUPPORT_DOMAIN_TICKETS');

        foreach ($payload['tickets'] as $row) {
            $ticketsDomain->addItem($this->createItemFromArray($row, (int) ($row['id'] ?? 0)));
        }

        $domains[] = $ticketsDomain;

        $messagesDomain = $this->createDomain('devartsupport_messages', 'PLG_PRIVACY_DEVARTSUPPORT_DOMAIN_MESSAGES');

        foreach ($payload['messages'] as $row) {
            $messagesDomain->addItem($this->createItemFromArray($row, (int) ($row['id'] ?? 0)));
        }

        $domains[] = $messagesDomain;

        $attachmentsDomain = $this->createDomain('devartsupport_attachments', 'PLG_PRIVACY_DEVARTSUPPORT_DOMAIN_ATTACHMENTS');

        foreach ($payload['attachments'] as $row) {
            $attachmentsDomain->addItem($this->createItemFromArray($row, (int) ($row['id'] ?? 0)));
        }

        $domains[] = $attachmentsDomain;

        $event->addResult($domains);
    }

    public function onPrivacyRemoveData(RemoveDataEvent $event): void
    {
        $request = $event->getRequest();
        $user = $event->getUser();
        $email = trim((string) ($request->email ?? ''));
        $userId = $user !== null ? (int) $user->id : 0;

        if ($email === '' && $userId < 1) {
            return;
        }

        if (!$this->componentAvailable()) {
            return;
        }

        TicketDesk::service()->privacyErase($email, $userId);
    }

    private function componentAvailable(): bool
    {
        try {
            TicketDesk::service();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
