<?php

declare(strict_types=1);

namespace App\Data\User\Invoice;

use App\Enums\Invoice\BulkInvoiceGenerationSkipReason;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Month excluded from generation before any `GenerateInvoiceAction` call.
 * Expected cases: invoice already issued (race between click and bulk),
 * no days used, month not elapsed, missing yearly tariff.
 */
#[TypeScript]
final class BulkInvoiceGenerationSkippedItemData extends Data
{
    public function __construct(
        public int $month,
        public BulkInvoiceGenerationSkipReason $reason,
    ) {}
}
