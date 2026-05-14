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
 * Pour les déclarations, expose ·
 *   - `pendingDeclarationsCount` · nombre total de couples (entreprise, année)
 *     en attente · 1 déclaration par couple donc compteur = nombre de
 *     déclarations à traiter
 *   - `pendingDeclarations` · top 5 items triés par urgence (overdue d'abord)
 *
 * Pour les factures, expose ·
 *   - `pendingInvoicesCount` · nombre total de couples (entreprise, année)
 *     en attente. Utilisé pour le footer « Voir les N autres entreprises »
 *   - `pendingInvoicesMonthlyTotal` · **somme des factures mensuelles à
 *     générer** toutes lignes confondues. C'est ce que l'utilisateur
 *     voit comme « N factures en attente » dans le header · une ligne
 *     peut représenter plusieurs factures mensuelles (jusqu'à 12 par
 *     couple entreprise-année), donc le compteur de lignes ne suffit pas.
 *   - `pendingInvoices` · top 5 lignes triées par année croissante
 *
 * Si `pendingDeclarationsCount > 5` ou `pendingInvoicesCount > 5`, le
 * front affiche un lien « Voir les N autres » pointant vers la page
 * Index filtrée.
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
        public int $pendingInvoicesMonthlyTotal,
        #[DataCollectionOf(DashboardPendingInvoiceItemData::class)]
        public array $pendingInvoices,
    ) {}

    public static function noPending(): self
    {
        return new self(
            pendingDeclarationsCount: 0,
            pendingDeclarations: [],
            pendingInvoicesCount: 0,
            pendingInvoicesMonthlyTotal: 0,
            pendingInvoices: [],
        );
    }
}
