<?php

declare(strict_types=1);

namespace App\Enums\Invoice;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Failure reason emitted during bulk invoice generation.
 */
#[TypeScript]
enum BulkInvoiceGenerationFailureReason: string
{
    /** Missing yearly pricing for at least one vehicle on the month. */
    case MissingPricing = 'missing_pricing';

    /** Race condition: a concurrent generation already posted an invoice on the same month. */
    case AlreadyExists = 'already_exists';

    /** Safety net refusing generation on a non-elapsed month. */
    case NotPastMonth = 'not_past_month';

    /** Any other untyped exception. */
    case Unexpected = 'unexpected';
}
