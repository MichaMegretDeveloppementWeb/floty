<?php

declare(strict_types=1);

namespace Tests\Feature\Seeders;

use App\Models\Contract;
use App\Models\Vehicle;
use App\Models\VehicleEvent;
use App\Models\VehicleFiscalCharacteristics;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Smoke test du DemoSeeder (chantier E) : valide que les enrichissements
 * multi-VFC + indispos cohabitant avec contrats sont bien produits par
 * un `db:seed --class=DemoSeeder`.
 *
 * Le test n'inspecte pas les valeurs exactes (qui peuvent évoluer avec
 * les démos) mais l'**existence** des cas particuliers que le seeder
 * doit couvrir, tels que documentés dans le plan chantier E.
 */
final class DemoSeederSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoSeeder::class);
    }

    #[Test]
    public function au_moins_un_vehicule_a_quatre_versions_fiscales_ou_plus(): void
    {
        // Cas multi-VFC riche : EE-005-EE (Renault 21) seedé avec 4 VFC
        // (initiale + 3 extraVfcs). Garde-fou contre une régression du
        // refactor `splitVfcOn` → `extraVfcs`.
        $vehicleWith4PlusVfcs = Vehicle::query()
            ->withCount('fiscalCharacteristics')
            ->having('fiscal_characteristics_count', '>=', 4)
            ->exists();

        $this->assertTrue(
            $vehicleWith4PlusVfcs,
            'Au moins un véhicule doit avoir ≥ 4 VFC pour exercer le segmenteur.',
        );
    }

    #[Test]
    public function au_moins_un_vehicule_a_deux_versions_fiscales_dans_la_meme_annee(): void
    {
        // Cas multi-VFC intra-annuelle : EE-005-EE (2024-03-15 + 2024-09-10)
        // ou EH-008-HH (2024-04-01 + 2024-09-01). Vérifie qu'au moins un
        // véhicule a 2 VFC commençant la même année.
        $vehicleIds = Vehicle::query()->pluck('id');
        $hasIntraYear = false;
        foreach ($vehicleIds as $vehicleId) {
            $yearCounts = VehicleFiscalCharacteristics::query()
                ->where('vehicle_id', $vehicleId)
                ->get()
                ->groupBy(fn ($vfc): int => (int) $vfc->effective_from->year)
                ->map(fn ($group) => $group->count());

            if ($yearCounts->max() >= 2) {
                $hasIntraYear = true;
                break;
            }
        }

        $this->assertTrue(
            $hasIntraYear,
            'Au moins un véhicule doit avoir ≥ 2 VFC commençant la même année.',
        );
    }

    #[Test]
    public function au_moins_un_contrat_chevauche_une_bascule_vfc(): void
    {
        // EE-005-EE COR 2024-03-04 → 2024-03-28 chevauche la bascule VFC
        // 2024-03-15. Vérifie qu'au moins un contrat couvre une date
        // d'effective_from postérieure à start_date d'une VFC qui n'est
        // pas la première du véhicule.
        $hasContractCrossingVfc = false;
        foreach (Vehicle::query()->get() as $vehicle) {
            $contracts = Contract::query()->where('vehicle_id', $vehicle->id)->get();
            $vfcs = $vehicle->fiscalCharacteristics()
                ->orderBy('effective_from')
                ->get();
            if ($vfcs->count() < 2) {
                continue;
            }

            // Bornes de bascule = effective_from des VFC à partir de la 2ᵉ.
            $transitionDates = $vfcs->skip(1)->pluck('effective_from');
            foreach ($contracts as $contract) {
                foreach ($transitionDates as $transition) {
                    if (
                        $contract->start_date->lessThan($transition)
                        && $contract->end_date->greaterThanOrEqualTo($transition)
                    ) {
                        $hasContractCrossingVfc = true;
                        break 3;
                    }
                }
            }
        }

        $this->assertTrue(
            $hasContractCrossingVfc,
            'Au moins un contrat doit chevaucher une bascule VFC pour exercer le segmenteur.',
        );
    }

    #[Test]
    public function au_moins_une_indispo_reductrice_cohabite_avec_un_contrat(): void
    {
        $hasReductiveCohabitation = false;
        foreach (VehicleEvent::query()->get() as $u) {
            if (! $u->has_fiscal_impact) {
                continue;
            }
            $cohabitates = Contract::query()
                ->where('vehicle_id', $u->vehicle_id)
                ->where('start_date', '<=', $u->end_date ?? '2099-12-31')
                ->where('end_date', '>=', $u->start_date)
                ->exists();

            if ($cohabitates) {
                $hasReductiveCohabitation = true;
                break;
            }
        }

        $this->assertTrue(
            $hasReductiveCohabitation,
            'Au moins une indispo réductrice doit cohabiter avec un contrat.',
        );
    }

    #[Test]
    public function au_moins_une_indispo_non_reductrice_cohabite_avec_un_contrat(): void
    {
        // EH-008-HH Maintenance 02-12 → 02-15 cohabite avec BTP 01-08 →
        // 03-15. Garde-fou : le moteur fiscal doit IGNORER cette indispo,
        // mais le seeder doit la produire pour pouvoir tester l'invariant
        // visuellement.
        $hasNonReductiveCohabitation = false;
        foreach (VehicleEvent::query()->get() as $u) {
            if ($u->has_fiscal_impact) {
                continue;
            }
            // Nature non réductrice (maintenance, événement personnalisé, etc.).
            $cohabitates = Contract::query()
                ->where('vehicle_id', $u->vehicle_id)
                ->where('start_date', '<=', $u->end_date ?? '2099-12-31')
                ->where('end_date', '>=', $u->start_date)
                ->exists();

            if ($cohabitates) {
                $hasNonReductiveCohabitation = true;
                break;
            }
        }

        $this->assertTrue(
            $hasNonReductiveCohabitation,
            'Au moins une indispo NON réductrice doit cohabiter avec un contrat (cas Maintenance).',
        );
    }

    #[Test]
    public function le_seeder_produit_au_moins_un_cas_mixte_indispo_contrat_bascule_vfc(): void
    {
        // Cas mixte EE-005-EE : indispo « Suspension du CI » 03-10 → 03-22
        // cohabite avec contrat COR 03-04 → 03-28 ET chevauche la bascule VFC
        // 03-15. Exerce simultanément les 3 mécaniques. Garde-fou contre
        // un futur seeder qui retirerait par erreur ce cas pédagogique.
        $hasMixedCase = false;
        foreach (VehicleEvent::query()->where('title', 'Suspension du CI')->get() as $u) {
            $vehicle = Vehicle::query()->find($u->vehicle_id);
            if ($vehicle === null) {
                continue;
            }
            $vfcs = $vehicle->fiscalCharacteristics()->orderBy('effective_from')->get();
            if ($vfcs->count() < 2) {
                continue;
            }

            $cohabitatingContract = Contract::query()
                ->where('vehicle_id', $u->vehicle_id)
                ->where('start_date', '<=', $u->end_date ?? '2099-12-31')
                ->where('end_date', '>=', $u->start_date)
                ->first();

            if ($cohabitatingContract === null) {
                continue;
            }

            // Bascule VFC chevauchée par l'indispo ?
            $transitionDates = $vfcs->skip(1)->pluck('effective_from');
            foreach ($transitionDates as $transition) {
                if (
                    $u->start_date->lessThan($transition)
                    && ($u->end_date ?? $u->start_date)->greaterThanOrEqualTo($transition)
                ) {
                    $hasMixedCase = true;
                    break 2;
                }
            }
        }

        $this->assertTrue(
            $hasMixedCase,
            'Le seeder doit produire au moins un cas mixte (indispo + contrat + bascule VFC).',
        );
    }
}
