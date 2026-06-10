<?php

declare(strict_types=1);

namespace Tests\Unit\Fiscal\Invariants;

use App\DTO\Fiscal\FiscalBreakdown;
use App\Enums\Contract\ContractType;
use App\Models\Contract;
use App\Models\Vehicle;
use App\Models\VehicleEvent;
use App\Services\Fiscal\FiscalCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests d'invariants property-based pour le moteur fiscal Floty.
 *
 * **Pourquoi** : un test « par cas » ne couvre que les configurations
 * explicitement écrites. Un test d'invariant exécute la même propriété
 * mathématique sur N scénarios aléatoires valides · il vaut donc pour
 * l'**infinité** des combinaisons couvertes par les contraintes du
 * domaine. C'est ce qui permet d'affirmer « tous les cas de figure »
 * avec une confiance bien supérieure à un sweep de tests par cas.
 *
 * **Reproductibilité** : chaque scénario est produit à partir d'une
 * seed entière déterministe. Si un invariant casse pour la seed 42,
 * lancer `(new FiscalScenarioGenerator(42))->generate(2024)` rejoue
 * exactement le même scénario en isolation.
 *
 * **Quoi faire si un invariant échoue** : NE PAS amender l'invariant
 * pour le faire passer (ce serait masquer un bug fiscal). Convertir
 * la seed en test Unit reproductible, ouvrir un chantier dédié, fixer
 * le moteur. Les invariants sont un test de l'engin, pas une
 * spécification ajustable.
 *
 * **Volume** : N=30 scénarios par invariant (soit ~150 calculs fiscaux
 * par run de la suite). Compromis vitesse/couverture · un audit
 * périodique pourra augmenter à N=100 si besoin de durcir.
 */
final class FiscalInvariantsTest extends TestCase
{
    use RefreshDatabase;

    private const int SCENARIOS_PER_INVARIANT = 30;

    private const int FIRST_SEED = 1000;

    private FiscalCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = $this->app->make(FiscalCalculator::class);
    }

    #[Test]
    public function idempotence_deux_calculs_successifs_donnent_meme_resultat(): void
    {
        $this->forEachScenario(function (FiscalScenario $scenario): void {
            $r1 = $this->calc($scenario);
            $r2 = $this->calc($scenario);

            self::assertSame($r1->totalDue, $r2->totalDue,
                "seed={$scenario->seed} : recalcul non idempotent (totalDue diffère)");
            self::assertSame($r1->daysAssigned, $r2->daysAssigned,
                "seed={$scenario->seed} : recalcul non idempotent (daysAssigned diffère)");
            self::assertSame($r1->co2Due, $r2->co2Due,
                "seed={$scenario->seed} : recalcul non idempotent (co2Due diffère)");
            self::assertSame($r1->pollutantsDue, $r2->pollutantsDue,
                "seed={$scenario->seed} : recalcul non idempotent (pollutantsDue diffère)");
        });
    }

    #[Test]
    public function neutralite_indispo_non_reductrice_n_affecte_pas_la_taxe(): void
    {
        $this->forEachScenario(function (FiscalScenario $scenario): void {
            $hasNonReductive = false;
            foreach ($scenario->vehicleEvents as $u) {
                if (! $u->has_fiscal_impact) {
                    $hasNonReductive = true;
                    break;
                }
            }
            // Skip si aucune non-réductrice : invariant trivialement vrai.
            if (! $hasNonReductive) {
                return;
            }

            $with = $this->calc($scenario);
            $without = $this->calc($scenario->withoutNonReductiveUnavailabilities());

            self::assertSame($with->totalDue, $without->totalDue,
                "seed={$scenario->seed} : retirer une indispo non-réductrice a changé le total ".
                "(avec={$with->totalDue}, sans={$without->totalDue})");
        });
    }

    #[Test]
    public function monotonie_etendre_un_contrat_d_un_jour_ne_diminue_jamais_la_taxe(): void
    {
        $this->forEachScenario(function (FiscalScenario $scenario): void {
            // On prend le premier contrat LLD (ignorer les LCD : ajouter
            // un jour à un LCD < 30j peut basculer en LLD taxable et le
            // saut n'est pas monotone · c'est la sémantique souveraine
            // de R-2024-021).
            $base = null;
            foreach ($scenario->contracts as $c) {
                if ($c->contract_type === ContractType::Lld) {
                    $base = $c;
                    break;
                }
            }
            if ($base === null) {
                return; // pas de LLD à étendre
            }

            $extended = clone $base;
            // Étendre la fin du contrat d'un jour. Borné à 31 décembre
            // de l'année cible pour ne pas sortir de l'année fiscale.
            $newEnd = $this->shiftDate($base->end_date->toDateString(), 1);
            $yearEnd = sprintf('%04d-12-31', $scenario->year);
            if ($newEnd > $yearEnd) {
                return; // extension hors année cible · invariant non testable
            }
            $extended->setRawAttributes([
                'vehicle_id' => $base->vehicle_id,
                'company_id' => $base->company_id,
                'start_date' => $base->start_date->toDateString(),
                'end_date' => $newEnd,
                'contract_reference' => null,
                'contract_type' => $base->contract_type->value,
                'notes' => null,
            ], true);

            // Reconstruire la liste de contrats · remplacer le base par l'étendu
            // (match par fingerprint start/end/company plutôt qu'identité d'objet
            // pour rester robuste si la collection est ré-instanciée).
            $extendedContracts = [];
            foreach ($scenario->contracts as $c) {
                $extendedContracts[] = ($c->start_date->equalTo($base->start_date)
                    && $c->end_date->equalTo($base->end_date)
                    && $c->company_id === $base->company_id)
                    ? $extended : $c;
            }

            $rBase = $this->calc($scenario);
            $rExtended = $this->calcWithContracts($scenario->vehicle, $extendedContracts, $scenario->vehicleEvents, $scenario->year);

            self::assertGreaterThanOrEqual(
                $rBase->totalDue,
                $rExtended->totalDue,
                "seed={$scenario->seed} : étendre un contrat d'1 jour a DIMINUÉ la taxe ".
                "(base={$rBase->totalDue}, étendu={$rExtended->totalDue}). ".
                'Violation de monotonie · possible bug d\'arrondi ou exonération qui se déclenche.',
            );
        });
    }

    #[Test]
    public function additivite_segments_recouvrant_un_contrat_full_year_donnent_meme_taxe(): void
    {
        // Pour chaque scénario, on remplace les contrats existants par UN
        // contrat full-year LLD, puis on compare le calcul à la somme de
        // 4 contrats trimestriels couvrant la même année (ensemble disjoint
        // mais contigu). Tolérance : ±0,10 € (arrondi half-up cumulé).
        //
        // Cet invariant teste la pure additivité du prorata
        // jour-par-jour de R-2024-002 · toute violation = bug
        // d'arithmétique dans le moteur.
        $this->forEachScenario(function (FiscalScenario $scenario): void {
            $year = $scenario->year;
            $fullYearContract = $this->makeFullYearContract($scenario->vehicle, $year);
            $rFull = $this->calcWithContracts(
                $scenario->vehicle,
                [$fullYearContract],
                [], // pas d'indispo : on isole la propriété additive du prorata pur
                $year,
            );

            // Construit 4 contrats trimestriels (chacun > 30j → LLD,
            // pas d'effet LCD parasite).
            $quarters = [
                [sprintf('%04d-01-01', $year), sprintf('%04d-03-31', $year)],
                [sprintf('%04d-04-01', $year), sprintf('%04d-06-30', $year)],
                [sprintf('%04d-07-01', $year), sprintf('%04d-09-30', $year)],
                [sprintf('%04d-10-01', $year), sprintf('%04d-12-31', $year)],
            ];
            $sumQuarters = 0.0;
            foreach ($quarters as [$start, $end]) {
                $contract = $this->makeContract($scenario->vehicle, $start, $end);
                $r = $this->calcWithContracts(
                    $scenario->vehicle,
                    [$contract],
                    [],
                    $year,
                );
                $sumQuarters += $r->totalDue;
            }
            $sumQuarters = round($sumQuarters, 2);

            self::assertEqualsWithDelta(
                $rFull->totalDue,
                $sumQuarters,
                0.10,
                "seed={$scenario->seed} : Σ 4 trimestres ({$sumQuarters} €) ".
                "≠ taxe full-year ({$rFull->totalDue} €). Violation d'additivité du prorata.",
            );
        });
    }

    // --- Plumbing ------------------------------------------------------

    /**
     * Exécute la closure pour SCENARIOS_PER_INVARIANT scénarios générés
     * avec des seeds consécutives à partir de FIRST_SEED.
     */
    private function forEachScenario(callable $assertion): void
    {
        for ($i = 0; $i < self::SCENARIOS_PER_INVARIANT; $i++) {
            $seed = self::FIRST_SEED + $i;
            $scenario = (new FiscalScenarioGenerator($seed))->generate(2024);
            $assertion($scenario);
        }
    }

    private function calc(FiscalScenario $scenario): FiscalBreakdown
    {
        return $this->calcWithContracts(
            $scenario->vehicle,
            $scenario->contracts,
            $scenario->vehicleEvents,
            $scenario->year,
        );
    }

    /**
     * @param  list<Contract>  $contracts
     * @param  list<VehicleEvent>  $vehicleEvents
     */
    private function calcWithContracts(
        Vehicle $vehicle,
        array $contracts,
        array $vehicleEvents,
        int $year,
    ): FiscalBreakdown {
        return $this->calculator->calculate(
            $vehicle,
            $contracts,
            $vehicleEvents,
            $year,
        );
    }

    private function makeFullYearContract(Vehicle $vehicle, int $year): Contract
    {
        return $this->makeContract(
            $vehicle,
            sprintf('%04d-01-01', $year),
            sprintf('%04d-12-31', $year),
        );
    }

    private function makeContract(Vehicle $vehicle, string $start, string $end): Contract
    {
        $contract = new Contract;
        $contract->setRawAttributes([
            'vehicle_id' => $vehicle->id,
            'company_id' => 1,
            'start_date' => $start,
            'end_date' => $end,
            'contract_reference' => null,
            'contract_type' => ContractType::Lld->value,
            'notes' => null,
        ], true);

        return $contract;
    }

    private function shiftDate(string $date, int $days): string
    {
        return (new \DateTimeImmutable($date))
            ->modify(sprintf('%+d days', $days))
            ->format('Y-m-d');
    }
}
