<?php

declare(strict_types=1);

namespace App\Data\User\FiscalDeclaration;

use App\Enums\FiscalDeclaration\DeclarationLifecycleState;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A declaration awaiting user attention for a company.
 *
 * `deadline` follows the CIBS doctrine (declaration N due on 30 April
 * N+1). `isOverdue` is derived from a comparison with today: lateness
 * is information for the user, never a hard block.
 *
 * "Pending" extends beyond "never prepared": a declaration appears in
 * this list until it reaches `GeneratedActive`. Fields `state`,
 * `currentDeclarationId` and `pendingClustersCount` let the frontend
 * render an adaptive card with the right CTA (Préparer / Reprendre /
 * Régénérer / Reprendre la régénération).
 */
#[TypeScript]
final class PendingDeclarationData extends Data
{
    public function __construct(
        public int $fiscalYear,
        /** ISO 8601 (Y-m-d). Deadline = 30/04 of N+1. */
        public string $deadline,
        public bool $isOverdue,
        public DeclarationLifecycleState $state,
        /**
         * Current-year declaration id used to build `/review` or `/show`
         * links. Null in the `Untouched` state where no declaration
         * exists yet.
         */
        public ?int $currentDeclarationId,
        public int $pendingClustersCount,
        /**
         * Obsolescence context for the adaptive sub-mention rendered in
         * `PendingDeclarationsAlert`. Populated only in the
         * GeneratedObsoleteOrphan and RegenerationInProgress states so
         * the UI shows "3 motifs depuis le 14/03/2025" rather than a
         * misleading deadline mention. Null for Untouched / Draft /
         * Deferred where the deadline remains relevant.
         */
        public ?string $obsoleteSinceDate,
        public int $obsoleteReasonsCount,
    ) {}
}
