<?php
/**
 * @package     DevArt Support
 * @subpackage  com_devartsupport
 *
 * @copyright   Copyright (C) 2026 Stathopoulos Kostas - DevArt. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Devart\Component\Devartsupport\Administrator\Exception;

defined('_JEXEC') or die;

final class TicketValidationException extends \RuntimeException
{
    public function __construct(string $languageKey)
    {
        parent::__construct($languageKey);
    }

    public function getLanguageKey(): string
    {
        return $this->getMessage();
    }
}
