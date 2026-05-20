<?php

declare(strict_types=1);

namespace App\Enums\Fiscal;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Reason why a fiscal segment starts at its given boundary inside the
 * full-year tax breakdown of a vehicle.
 *
 * The fiscal pipeline cuts a year along two independent dimensions:
 *   - VFC effectivity windows (e.g. mid-year characteristics change);
 *   - rule effectivity windows (e.g. CIBS bracket change on July 1st).
 *
 * The cause flag lets the UI explain how the current segment was
 * produced (new VFC version, new fiscal regime, or both at once).
 */
#[TypeScript]
enum SegmentBoundaryCause: string
{
    case Initial = 'initial';
    case Vfc = 'vfc';
    case Rule = 'rule';
    case Both = 'both';
}
