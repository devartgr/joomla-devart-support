<?php
/**
 * @package     DevArt Support
 * @subpackage  com_devartsupport
 *
 * @copyright   Copyright (C) 2026 Stathopoulos Kostas - DevArt. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Devart\Component\Devartsupport\Administrator\Service;

defined('_JEXEC') or die;

use Devart\Component\Devartsupport\Administrator\Helper\MailQueueSettings;
use Devart\Component\Devartsupport\Administrator\Service\Storage\MailQueueStorage;

/**
 * Processes queued ticket notification emails for the Scheduler worker.
 */
final class MailQueueService
{
    public function __construct(
        private readonly MailQueueStorage $queue,
        private readonly TicketMailService $mail
    ) {
    }

    /**
     * @return array{sent: int, failed: int, purged: int}
     */
    public function processBatch(?int $limit = null): array
    {
        $limit = $limit ?? MailQueueSettings::batchSize();
        $candidates = $this->queue->listClaimCandidates($limit);
        $sent = 0;
        $failed = 0;

        foreach ($candidates as $row) {
            $id = (int) ($row->id ?? 0);

            if (!$this->queue->claim($id)) {
                continue;
            }

            $attempts = (int) ($row->attempts ?? 0) + 1;
            $maxAttempts = max(1, (int) ($row->max_attempts ?? MailQueueSettings::maxAttempts()));

            try {
                $recipients = $this->decodeEmails((string) ($row->recipients_json ?? '[]'));
                $bcc = $this->decodeEmails((string) ($row->bcc_json ?? '[]'));

                if ($recipients === []) {
                    throw new \RuntimeException('Mail queue row has no recipients.');
                }

                $this->mail->sendNow(
                    $recipients,
                    $bcc,
                    (string) ($row->subject ?? ''),
                    (string) ($row->body ?? '')
                );
                $this->queue->markSent($id);
                $sent++;
            } catch (\Throwable $error) {
                $this->queue->markRetryOrFailed($id, $attempts, $maxAttempts, $error->getMessage());
                $failed++;
            }
        }

        $purged = $this->queue->purgeOldSent();

        return [
            'sent'   => $sent,
            'failed' => $failed,
            'purged' => $purged,
        ];
    }

    /**
     * @return list<string>
     */
    private function decodeEmails(string $json): array
    {
        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        if (!is_array($decoded)) {
            return [];
        }

        $clean = [];

        foreach ($decoded as $email) {
            $email = strtolower(trim((string) $email));

            if ($email !== '') {
                $clean[] = $email;
            }
        }

        return array_values(array_unique($clean));
    }
}
