<?php

declare(strict_types=1);

namespace App\Data\User\Dashboard;

use App\Enums\FiscalDeclaration\DeclarationLifecycleState;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Item « déclaration en attente » affiché sur le dashboard (Phase 13
 * D5.15). Enrichit {@see App\Data\User\FiscalDeclaration\PendingDeclarationData}
 * avec le contexte entreprise (label, code court) pour que la carte
 * Dashboard soit autosuffisante · l'utilisateur sait immédiatement
 * quelle entreprise / année est concernée sans navigation préalable.
 *
 * L'état `state` (lifecycle) pilote la CTA contextuelle côté front ·
 *   - Untouched               → « Préparer »
 *   - DraftPending             → « Continuer la revue »
 *   - DraftReadyToGenerate     → « Générer »
 *   - Deferred                 → « Reprendre »
 *   - GeneratedObsoleteOrphan  → « Régénérer »
 *   - RegenerationInProgress   → « Finaliser la régénération »
 *   - DeferredRegeneration     → « Reprendre la régénération »
 */
#[TypeScript]
final class DashboardPendingDeclarationItemData extends Data
{
    public function __construct(
        public int $companyId,
        public string $companyShortCode,
        public string $companyLegalName,
        public int $fiscalYear,
        public string $deadline,
        public bool $isOverdue,
        public DeclarationLifecycleState $state,
        public ?int $currentDeclarationId,
        public int $pendingClustersCount,
        public ?string $obsoleteSinceDate,
        public int $obsoleteReasonsCount,
    ) {}
}
