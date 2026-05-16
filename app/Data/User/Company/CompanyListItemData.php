<?php

declare(strict_types=1);

namespace App\Data\User\Company;

use App\Enums\Company\CompanyColor;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Ligne de la table « Entreprises utilisatrices » (page
 * User/Companies/Index). Inclut les agrégats annuels pour la flotte
 * (jours utilisés + taxe due).
 */
#[TypeScript]
final class CompanyListItemData extends Data
{
    public function __construct(
        public int $id,
        public string $legalName,
        public string $shortCode,
        public CompanyColor $color,
        public ?string $siren,
        public ?string $city,
        public bool $isActive,
        public int $daysUsed,
        /**
         * P0.1 (audit perf 2026-05-16) · null pendant l'hydratation
         * deferred · cellule en skeleton frontend tant que la prop
         * racine `costs` (Inertia::defer) n'a pas hydrate.
         */
        public ?float $annualTaxDue = null,
        /**
         * Phase 13 D5.10.L · prix location total annuel (somme des 12
         * facturations mensuelles). Null si au moins 1 véhicule de la
         * company a un tarif annuel manquant pour l'année.
         *
         * P0.1 · egalement deferred · meme skeleton que annualTaxDue.
         */
        public ?float $rentalPriceTotal = null,
    ) {}
}
