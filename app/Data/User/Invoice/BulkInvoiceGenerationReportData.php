<?php

declare(strict_types=1);

namespace App\Data\User\Invoice;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Report returned by `BulkGenerateInvoicesAction` (bulk generation of all
 * annexes for a (company × year)).
 *
 * Best-effort strategy: each month runs in its own transaction via
 * `GenerateInvoiceAction`; a failure on one month does not abort the
 * sequence. The report collects the three possible outcomes:
 *
 *   - `generated`: actually emitted (number fixed, PDF persisted)
 *   - `failed`: months where a domain exception was thrown (missing
 *      tariff, unicity race, etc.)
 *   - `skipped`: months excluded before the try block (already invoiced
 *      meanwhile, no activity, month not elapsed, etc.)
 *
 * No merging happens DTO-side; the controller and Vue components display
 * the three sections separately for readability.
 */
#[TypeScript]
final class BulkInvoiceGenerationReportData extends Data
{
    public function __construct(
        public int $companyId,
        public int $year,

        /** @var list<BulkInvoiceGenerationGeneratedItemData> */
        #[DataCollectionOf(BulkInvoiceGenerationGeneratedItemData::class)]
        public array $generated,

        /** @var list<BulkInvoiceGenerationFailedItemData> */
        #[DataCollectionOf(BulkInvoiceGenerationFailedItemData::class)]
        public array $failed,

        /** @var list<BulkInvoiceGenerationSkippedItemData> */
        #[DataCollectionOf(BulkInvoiceGenerationSkippedItemData::class)]
        public array $skipped,
    ) {}
}
