<?php

declare(strict_types=1);

namespace App\Data\User\Invoice;

use App\Enums\Invoice\RegenerateRedirectTarget;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Payload for POST regenerate. The client picks an explicit redirect
 * target instead of relying on the Referer header server-side (see
 * {@see RegenerateRedirectTarget}). Default `Show` matches the common
 * "regenerate from the invoice detail page" flow.
 */
#[TypeScript]
#[MapInputName(SnakeCaseMapper::class)]
final class RegenerateInvoiceRequestData extends Data
{
    public function __construct(
        public RegenerateRedirectTarget $redirectTarget = RegenerateRedirectTarget::Show,
    ) {}
}
