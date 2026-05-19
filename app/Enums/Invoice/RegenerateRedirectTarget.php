<?php

declare(strict_types=1);

namespace App\Enums\Invoice;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Redirect target after invoice regeneration. The caller passes the target
 * explicitly rather than the backend guessing via `Referer`.
 *
 * - `Show`: redirect to the new invoice detail page.
 * - `Index`: redirect to the invoices list.
 * - `CompanyTab`: redirect to the company billing tab.
 */
#[TypeScript]
enum RegenerateRedirectTarget: string
{
    case Show = 'show';
    case Index = 'index';
    case CompanyTab = 'company-tab';
}
