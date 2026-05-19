<?php

declare(strict_types=1);

namespace App\Enums\FiscalDeclaration;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Application-level status for a fiscal declaration (ADR-0015 § D4 rev. 1.1).
 *
 * - `draft`: under review, editable.
 * - `deferred`: voluntarily set aside; semantically a draft, PDF generation still allowed.
 * - `generated`: PDF produced, immutable snapshot. May become obsolete but never returns to `draft`.
 */
#[TypeScript]
enum FiscalDeclarationStatus: string
{
    case Draft = 'draft';
    case Deferred = 'deferred';
    case Generated = 'generated';
}
