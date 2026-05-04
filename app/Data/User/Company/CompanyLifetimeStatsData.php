<?php

declare(strict_types=1);

namespace App\Data\User\Company;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Statistiques cumulées « depuis le début » d'une entreprise — tous
 * exercices confondus, indépendamment de l'année active du sélecteur
 * local de la fiche.
 *
 * Alimente la section « Depuis le début » de la fiche entreprise
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
    ) {}
}
