<?php

declare(strict_types=1);

namespace Tests\Unit\Fiscal\Invariants;

use App\Models\Contract;
use App\Models\Unavailability;
use App\Models\Vehicle;

/**
 * Scénario fiscal complet généré par {@see FiscalScenarioGenerator}.
 *
 * Bundle in-memory exclusivement (pas de persistance DB) pour rester
 * compatible avec les tests d'invariants exécutés N=100 fois sans
 * pénalité de performance.
 *
 * Le scénario respecte par construction les invariants du domaine :
 *   - Aucun chevauchement entre VFC successives
 *   - Tous les contrats tombent dans la plage couverte par les VFC
 *   - Toutes les indispos tombent dans l'année cible
 *   - Au moins 1 VFC, au moins 1 contrat
 *
 * `seed` est conservé pour permettre la reproduction d'un scénario en
 * cas d'échec d'invariant (le test dump la seed et on peut la rejouer
 * en isolation).
 */
final readonly class FiscalScenario
{
    /**
     * @param  list<Contract>  $contracts
     * @param  list<Unavailability>  $unavailabilities
     */
    public function __construct(
        public int $seed,
        public int $year,
        public Vehicle $vehicle,
        public array $contracts,
        public array $unavailabilities,
    ) {}

    /**
     * Variante du scénario sans les indispos non-réductrices
     * (Maintenance, etc.). Utilisée par l'invariant de neutralité —
     * supprimer une indispo non-réductrice ne doit pas changer la taxe.
     */
    public function withoutNonReductiveUnavailabilities(): self
    {
        $filtered = array_values(array_filter(
            $this->unavailabilities,
            static fn (Unavailability $u): bool => $u->type->isFiscallyReductive(),
        ));

        return new self(
            seed: $this->seed,
            year: $this->year,
            vehicle: $this->vehicle,
            contracts: $this->contracts,
            unavailabilities: $filtered,
        );
    }
}
