<?php

declare(strict_types=1);

namespace App\Data\User\Dashboard;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Tâches opérationnelles en attente affichées sur le Dashboard (Phase
 * 13 D5.15 · refonte chantier η Phase 4 qui posait les placeholders).
 *
 * Pour chaque domaine (déclarations + factures), expose ·
 *   - `*Count` · nombre total d'items pending toutes entreprises confondues
 *   - `*Items` · top 5 items triés par urgence (échéance overdue d'abord, puis date croissante) pour rendu liste détaillée sur le dashboard
 *
 * Si `*Count > count(*Items)`, le front affiche un lien « Voir les N autres »
 * pointant vers la page Index filtrée (Déclarations Index ou Factures Index).
 *
 * Si `*Count === 0`, le front affiche un état vide explicite (cf. Q3=B
 * du brief D5.15).
 */
#[TypeScript]
final class DashboardPendingTasksData extends Data
{
    /**
     * @param  list<DashboardPendingDeclarationItemData>  $pendingDeclarations
     * @param  list<DashboardPendingInvoiceItemData>  $pendingInvoices
     */
    public function __construct(
        public int $pendingDeclarationsCount,
        #[DataCollectionOf(DashboardPendingDeclarationItemData::class)]
        public array $pendingDeclarations,
        public int $pendingInvoicesCount,
        #[DataCollectionOf(DashboardPendingInvoiceItemData::class)]
        public array $pendingInvoices,
    ) {}

    public static function noPending(): self
    {
        return new self(
            pendingDeclarationsCount: 0,
            pendingDeclarations: [],
            pendingInvoicesCount: 0,
            pendingInvoices: [],
        );
    }
}
