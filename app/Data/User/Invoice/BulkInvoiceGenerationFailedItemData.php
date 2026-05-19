<?php

declare(strict_types=1);

namespace App\Data\User\Invoice;

use App\Enums\Invoice\BulkInvoiceGenerationFailureReason;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Month that raised an exception during `GenerateInvoiceAction::execute()`.
 * `reason` is enum-typed so the front-end can render a consistent label
 * without depending on the `errorMessage` string (subject to i18n and
 * rewording).
 */
#[TypeScript]
final class BulkInvoiceGenerationFailedItemData extends Data
{
    public function __construct(
        public int $month,
        public BulkInvoiceGenerationFailureReason $reason,
        public string $errorMessage,
    ) {}
}
