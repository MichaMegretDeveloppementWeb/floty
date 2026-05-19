<?php

declare(strict_types=1);

namespace App\Data\User\Dashboard;

use App\Data\User\FiscalDeclaration\PendingDeclarationData;
use App\Enums\FiscalDeclaration\DeclarationLifecycleState;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Pending-declaration item shown on the Dashboard. Enriches
 * {@see PendingDeclarationData} with
 * the company context so the card is self-contained.
 *
 * The lifecycle `state` drives the CTA on the frontend (Préparer /
 * Continuer la revue / Générer / Reprendre / Régénérer / etc.).
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
