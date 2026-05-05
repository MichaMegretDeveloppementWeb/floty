<?php

declare(strict_types=1);

namespace App\Data\Shared;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Bloc `fiscal` des shared props Inertia — liste des années configurées
 * dans le moteur fiscal (cf. `config('floty.fiscal.available_years')`).
 *
 * **Chantier J (ADR-0020)** : la propriété `currentYear` (qui dépendait
 * de `session('fiscal.active_year')` via `FiscalYearResolver`) a été
 * retirée. Chaque page consommatrice gère désormais sa propre année
 * via `?year=` URL + sélecteur local. `availableYears` reste exposé pour
 * peupler les sélecteurs.
 */
#[TypeScript]
final class FiscalSharedData extends Data
{
    /**
     * @param  list<int>  $availableYears
     */
    public function __construct(
        public array $availableYears,
    ) {}
}
