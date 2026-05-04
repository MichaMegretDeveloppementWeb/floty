<?php

declare(strict_types=1);

namespace App\Data\User\Company;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Statistiques annuelles d'une entreprise — exercice par exercice.
 *
 * Utilisé deux fois côté `CompanyDetailData` :
 *   - `byYear` : exercice unique sélectionné par le sélecteur local
 *     (4 KPIs annuels affichés dans la section « Aperçu par année »)
 *   - `history[]` : un objet par exercice avec ≥ 1 contrat (tableau
 *     récap dans la section « Historique par année »)
 *
 * Cf. chantier K, ADR-0020 § 2 D3.
 */
#[TypeScript]
final class CompanyYearStatsData extends Data
{
    public function __construct(
        public int $year,
        /** Jours-contrats utilisés sur l'année (somme des couples). */
        public int $daysUsed,
        /** Nombre de contrats actifs sur l'année. */
        public int $contractsCount,
        /** Sous-décompte LCD (contrat ≤ 30 j ou mois civil entier). */
        public int $lcdCount,
        /** Sous-décompte LLD (contrat > 30 j hors mois civil entier). */
        public int $lldCount,
        /** Taxe annuelle due par l'entreprise pour cet exercice (€, arrondi 2 décimales). */
        public float $annualTaxDue,
        /** Loyer annuel facturé — null tant que la facturation V1.2 n'est pas livrée. */
        public ?float $rent,
    ) {}
}
