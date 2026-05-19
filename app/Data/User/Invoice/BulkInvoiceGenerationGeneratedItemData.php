<?php

declare(strict_types=1);

namespace App\Data\User\Invoice;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class BulkInvoiceGenerationGeneratedItemData extends Data
{
    public function __construct(
        public int $month,
        public int $invoiceId,
        public string $invoiceNumber,
    ) {}
}
