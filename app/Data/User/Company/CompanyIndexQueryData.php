<?php

declare(strict_types=1);

namespace App\Data\User\Company;

use App\Data\Shared\Listing\IndexQueryData;
use App\Data\Shared\Listing\SortDirection;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * DTO d'entrée pour l'Index Companies server-side (cf. ADR-0020).
 *
 * Filtres (tous SQL purs) :
 *  - `isActive` : statut activité (true/false/null)
 *  - `contractsScope` : 'with' = au moins un contrat ; 'without' = aucun
 *  - `companyType` : 'corporate' (personne morale) | 'individual'
 *     (entrepreneur individuel) — basé sur `is_individual_business`
 *  - `city` : LIKE sur `city`
 *
 * Whitelist sortKey : `shortCode | legalName | siren | city`. Les valeurs
 * calculées `daysUsed` et `annualTaxDue` sont volontairement exclues
 * (cf. ADR-0020 D6 — à matérialiser pour réactiver le tri).
 */
#[TypeScript]
final class CompanyIndexQueryData extends IndexQueryData
{
    public function __construct(
        public ?bool $isActive = null,
        public ?string $contractsScope = null,
        public ?string $companyType = null,
        public ?string $city = null,
        /**
         * Année qui pilote les colonnes financières (`daysUsed`,
         * `annualTaxDue`) calculées par le service. Sélecteur **local**
         * à la page (chantier J, ADR-0020). Si `null` côté DTO, le
         * controller résout via fallback année calendaire courante.
         */
        public ?int $year = null,
        int $page = 1,
        int $perPage = self::DEFAULT_PER_PAGE,
        ?string $search = null,
        ?string $sortKey = null,
        SortDirection $sortDirection = SortDirection::Asc,
    ) {
        parent::__construct($page, $perPage, $search, $sortKey, $sortDirection);
    }

    public static function allowedSortKeys(): array
    {
        return ['shortCode', 'legalName', 'siren', 'city'];
    }

    public static function rules(): array
    {
        // Doctrine "données métier ⊥ règles fiscales" (chantier η Phase 3) :
        // l'année saisie est libre (range calendaire raisonnable). Cf. note
        // dans VehicleIndexQueryData. Filtrer ici sur la config statique
        // morte rejetait silencieusement les années hors `[2024]` et
        // empêchait le sélecteur de basculer.
        $yearRule = ['nullable', 'integer', 'min:1900', 'max:2100'];

        return array_merge(parent::rules(), [
            'isActive' => ['nullable', 'boolean'],
            'contractsScope' => ['nullable', 'string', 'in:with,without'],
            'companyType' => ['nullable', 'string', 'in:corporate,individual'],
            'city' => ['nullable', 'string', 'max:255'],
            'year' => $yearRule,
        ]);
    }
}
