<?php

declare(strict_types=1);

namespace App\Enums\FiscalDeclaration;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Lifecycle states for a `(company, year)` fiscal declaration. Drives the
 * `<DeclarationStateCard>` component's rendering and primary CTA.
 *
 * - `Untouched` (S1): no declaration prepared yet.
 * - `DraftPending` (S2): draft with at least one pending cluster.
 * - `DraftReadyToGenerate` (S3): draft with all decisions made.
 * - `Deferred` (S4): voluntarily set aside.
 * - `DeferredRegeneration` (S4-bis): deferred draft replacing an obsolete declaration.
 * - `GeneratedActive` (S5): generated, up-to-date, not obsolete.
 * - `GeneratedObsoleteOrphan` (S6): generated then invalidated, no regeneration started.
 * - `RegenerationInProgress` (S7): chained draft created to replace an obsolete version.
 */
#[TypeScript]
enum DeclarationLifecycleState: string
{
    case Untouched = 'untouched';
    case DraftPending = 'draft_pending';
    case DraftReadyToGenerate = 'draft_ready_to_generate';
    case Deferred = 'deferred';
    case DeferredRegeneration = 'deferred_regeneration';
    case GeneratedActive = 'generated_active';
    case GeneratedObsoleteOrphan = 'generated_obsolete_orphan';
    case RegenerationInProgress = 'regeneration_in_progress';
}
