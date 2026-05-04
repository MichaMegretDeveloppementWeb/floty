<?php

declare(strict_types=1);

namespace App\Data\User\Company;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Statistiques cumulées « depuis le début » d'une entreprise — tous
 * exercices confondus.
 *
 * Alimente la rangée de 4 KPIs lifetime de la fiche entreprise
 * (cf. chantier K, ADR-0020 § 2 D3).
 */
#[TypeScript]
final class CompanyLifetimeStatsData extends Data
{
    public function __construct(
        /** Somme des jours-contrats sur tous les exercices. */
        public int $daysUsed,
        /** Nombre total de contrats actifs (non soft-deleted) toutes années. */
        public int $contractsCount,
        /** Somme des taxes calculées sur tous les exercices (€, arrondi 2 décimales). */
        public float $taxesGenerated,
        /** Total cumulé des loyers facturés tous exercices — null tant que la facturation V1.2 n'est pas livrée. */
        public ?float $rentTotal,
    ) {}
}
