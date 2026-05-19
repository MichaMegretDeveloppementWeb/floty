<?php

declare(strict_types=1);

namespace App\Data\User\Invoice;

use App\Enums\Invoice\BulkInvoiceGenerationFailureReason;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Mois ayant levé une exception pendant `GenerateInvoiceAction::execute()`.
 * Le `reason` est typé par enum pour pouvoir afficher un label cohérent
 * côté front sans dépendre de la string `errorMessage` (sujette à i18n et
 * à reformulation).
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
