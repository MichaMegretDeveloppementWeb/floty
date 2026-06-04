<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Fiscal\Declaration;

use App\Enums\FiscalDeclaration\InvalidationReasonType;
use App\Enums\VehicleEvent\VehicleEventType;
use App\Models\Company;
use App\Models\Contract;
use App\Models\FiscalDeclaration;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleEvent;
use App\Models\VehicleFiscalCharacteristics;
use App\Services\Fiscal\Declaration\DeclarationInvalidationDetector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Couvre la logique d'invalidation centralisée (Phase 11 D3, ADR-0015
 * § D8). Vérifie le périmètre des déclarations impactées + la
 * construction du motif typé empilé dans `obsolete_reasons`.
 */
final class DeclarationInvalidationDetectorTest extends TestCase
{
    use RefreshDatabase;

    private DeclarationInvalidationDetector $detector;

    private Company $company;

    private Vehicle $vehicle;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->detector = $this->app->make(DeclarationInvalidationDetector::class);
        $this->company = Company::factory()->create();
        $this->vehicle = Vehicle::factory()->create();
        $this->user = User::factory()->create();
    }

    #[Test]
    public function flag_for_contract_invalide_la_declaration_de_l_annee(): void
    {
        $declaration = $this->makeDeclaration(2025);
        $contract = $this->makeContract('2025-03-01', '2025-03-15');

        $this->detector->flagForContract($contract, InvalidationReasonType::ContractCreated, $this->user->id);

        $fresh = $declaration->fresh();
        self::assertTrue($fresh->is_obsolete);
        self::assertCount(1, $fresh->obsolete_reasons);
        self::assertSame(
            InvalidationReasonType::ContractCreated->value,
            $fresh->obsolete_reasons[0]['type'],
        );
    }

    #[Test]
    public function flag_for_contract_n_invalide_pas_les_autres_annees(): void
    {
        $decl2024 = $this->makeDeclaration(2024);
        $decl2025 = $this->makeDeclaration(2025);
        $contract = $this->makeContract('2025-03-01', '2025-03-15');

        $this->detector->flagForContract($contract, InvalidationReasonType::ContractCreated, $this->user->id);

        self::assertFalse($decl2024->fresh()->is_obsolete);
        self::assertTrue($decl2025->fresh()->is_obsolete);
    }

    #[Test]
    public function flag_for_contract_a_cheval_invalide_les_2_annees(): void
    {
        $decl2024 = $this->makeDeclaration(2024);
        $decl2025 = $this->makeDeclaration(2025);
        $contract = $this->makeContract('2024-12-26', '2025-01-15');

        $this->detector->flagForContract($contract, InvalidationReasonType::ContractCreated, $this->user->id);

        self::assertTrue($decl2024->fresh()->is_obsolete);
        self::assertTrue($decl2025->fresh()->is_obsolete);
    }

    #[Test]
    public function flag_for_contract_avec_previous_dates_couvre_les_annees_avant_et_apres(): void
    {
        $decl2024 = $this->makeDeclaration(2024);
        $decl2025 = $this->makeDeclaration(2025);
        $decl2026 = $this->makeDeclaration(2026);
        $contract = $this->makeContract('2026-03-01', '2026-03-15');

        // Anciennes bornes : 2024 → contrat invalidait 2024
        $this->detector->flagForContract(
            $contract,
            InvalidationReasonType::ContractUpdated,
            $this->user->id,
            previousStartDate: '2024-06-01',
            previousEndDate: '2024-06-15',
        );

        self::assertTrue($decl2024->fresh()->is_obsolete);
        self::assertFalse($decl2025->fresh()->is_obsolete);
        self::assertTrue($decl2026->fresh()->is_obsolete);
    }

    #[Test]
    public function flag_for_contract_avec_previous_company_invalide_les_2_companies(): void
    {
        $other = Company::factory()->create();
        $declMine = FiscalDeclaration::factory()->forCompany($this->company)->forYear(2025)->generated()->create();
        $declOther = FiscalDeclaration::factory()->forCompany($other)->forYear(2025)->generated()->create();
        $contract = $this->makeContract('2025-03-01', '2025-03-15');

        $this->detector->flagForContract(
            $contract,
            InvalidationReasonType::ContractUpdated,
            $this->user->id,
            previousCompanyId: $other->id,
        );

        self::assertTrue($declMine->fresh()->is_obsolete);
        self::assertTrue($declOther->fresh()->is_obsolete);
    }

    #[Test]
    public function flag_for_contract_n_invalide_aucune_declaration_d_une_autre_company(): void
    {
        $other = Company::factory()->create();
        $declMine = $this->makeDeclaration(2025);
        $declOther = FiscalDeclaration::factory()->forCompany($other)->forYear(2025)->generated()->create();
        $contract = $this->makeContract('2025-03-01', '2025-03-15');

        $this->detector->flagForContract($contract, InvalidationReasonType::ContractCreated, $this->user->id);

        self::assertTrue($declMine->fresh()->is_obsolete);
        self::assertFalse($declOther->fresh()->is_obsolete);
    }

    #[Test]
    public function flag_for_unavailability_court_circuite_si_non_reductrice(): void
    {
        $declaration = $this->makeDeclaration(2025);
        $this->makeContract('2025-01-01', '2025-12-31'); // contrat actif sur l'année
        $vehicleEvent = $this->makeVehicleEvent(
            type: VehicleEventType::Maintenance, // non réductrice
            start: '2025-03-01',
            end: '2025-03-15',
        );

        $this->detector->flagForVehicleEvent($vehicleEvent, InvalidationReasonType::VehicleEventCreated, $this->user->id);

        self::assertFalse($declaration->fresh()->is_obsolete);
    }

    #[Test]
    public function flag_for_unavailability_invalide_si_reductrice(): void
    {
        $declaration = $this->makeDeclaration(2025);
        $this->makeContract('2025-01-01', '2025-12-31');
        $vehicleEvent = $this->makeVehicleEvent(
            type: VehicleEventType::PoundPublic, // réductrice
            start: '2025-03-01',
            end: '2025-03-15',
        );

        $this->detector->flagForVehicleEvent($vehicleEvent, InvalidationReasonType::VehicleEventCreated, $this->user->id);

        self::assertTrue($declaration->fresh()->is_obsolete);
    }

    #[Test]
    public function flag_for_vfc_invalide_les_declarations_des_companies_avec_contrats_sur_le_vehicule(): void
    {
        $declaration = $this->makeDeclaration(2025);
        $this->makeContract('2025-06-01', '2025-06-30');

        $vfc = VehicleFiscalCharacteristics::withoutEvents(
            fn (): VehicleFiscalCharacteristics => VehicleFiscalCharacteristics::factory()->create([
                'vehicle_id' => $this->vehicle->id,
            ]),
        );

        $this->detector->flagForVfcMutation($vfc, InvalidationReasonType::VfcUpdated, $this->user->id);

        $fresh = $declaration->fresh();
        self::assertTrue($fresh->is_obsolete);
        self::assertSame(
            InvalidationReasonType::VfcUpdated->value,
            $fresh->obsolete_reasons[0]['type'],
        );
    }

    #[Test]
    public function flag_for_vfc_n_invalide_rien_si_aucun_contrat_sur_le_vehicule(): void
    {
        $declaration = $this->makeDeclaration(2025);
        // Pas de contrat sur ce véhicule

        $vfc = VehicleFiscalCharacteristics::withoutEvents(
            fn (): VehicleFiscalCharacteristics => VehicleFiscalCharacteristics::factory()->create([
                'vehicle_id' => $this->vehicle->id,
            ]),
        );

        $this->detector->flagForVfcMutation($vfc, InvalidationReasonType::VfcUpdated, $this->user->id);

        self::assertFalse($declaration->fresh()->is_obsolete);
    }

    #[Test]
    public function flag_for_contract_n_invalide_pas_les_brouillons_draft(): void
    {
        // Phase 13 D5.10.O : un brouillon n'est jamais marqué obsolète.
        $draft = FiscalDeclaration::factory()
            ->forCompany($this->company)
            ->forYear(2025)
            ->draft()
            ->create();
        $contract = $this->makeContract('2025-03-01', '2025-03-15');

        $this->detector->flagForContract($contract, InvalidationReasonType::ContractCreated, $this->user->id);

        $fresh = $draft->fresh();
        self::assertFalse($fresh->is_obsolete);
        self::assertNull($fresh->obsolete_reasons);
        self::assertNull($fresh->obsolete_at);
    }

    #[Test]
    public function flag_for_contract_n_invalide_pas_les_brouillons_deferred(): void
    {
        // Phase 13 D5.10.O : un brouillon mis de côté reste à jour
        // dynamiquement à chaque ouverture en Review, donc pas de flag.
        $deferred = FiscalDeclaration::factory()
            ->forCompany($this->company)
            ->forYear(2025)
            ->deferred()
            ->create();
        $contract = $this->makeContract('2025-03-01', '2025-03-15');

        $this->detector->flagForContract($contract, InvalidationReasonType::ContractCreated, $this->user->id);

        $fresh = $deferred->fresh();
        self::assertFalse($fresh->is_obsolete);
        self::assertNull($fresh->obsolete_reasons);
        self::assertNull($fresh->obsolete_at);
    }

    #[Test]
    public function flag_for_contract_invalide_la_generee_meme_si_brouillon_coexiste(): void
    {
        // Phase 13 D5.10.O : garantit que la coexistence d'un brouillon
        // (de régénération) ne neutralise pas l'empilement du motif sur
        // la générée déjà obsolète.
        //
        // Lot 5 D3 (index unique partiel `decl_active_uniqueness`) ·
        // une generated active et un draft active ne peuvent pas
        // coexister sur le même couple (company, year). Le scénario
        // réaliste est : generated devient obsolète, puis un draft de
        // régénération est créé. Le détecteur doit alors empiler un
        // motif supplémentaire sur la generated obsolète sans toucher
        // au draft.
        $generated = $this->makeDeclaration(2025);
        $generated->forceFill([
            'is_obsolete' => true,
            'obsolete_at' => now(),
            'obsolete_reasons' => [],
        ])->save();

        $draft = FiscalDeclaration::factory()
            ->forCompany($this->company)
            ->forYear(2025)
            ->draft()
            ->create();
        $contract = $this->makeContract('2025-03-01', '2025-03-15');

        $this->detector->flagForContract($contract, InvalidationReasonType::ContractCreated, $this->user->id);

        $freshGenerated = $generated->fresh();
        self::assertTrue($freshGenerated->is_obsolete);
        self::assertCount(1, $freshGenerated->obsolete_reasons);
        self::assertFalse($draft->fresh()->is_obsolete);
    }

    #[Test]
    public function le_motif_empile_contient_le_payload_complet(): void
    {
        $declaration = $this->makeDeclaration(2025);
        $contract = $this->makeContract('2025-03-01', '2025-03-15');

        $this->detector->flagForContract(
            $contract,
            InvalidationReasonType::ContractUpdated,
            $this->user->id,
            fieldsChanged: ['start_date', 'end_date'],
        );

        $reason = $declaration->fresh()->obsolete_reasons[0];
        self::assertSame($this->user->id, $reason['actor_user_id']);
        self::assertSame('contract', $reason['entity']['type']);
        self::assertSame($contract->id, $reason['entity']['id']);
        self::assertSame(['start_date', 'end_date'], $reason['fields_changed']);
    }

    private function makeDeclaration(int $year): FiscalDeclaration
    {
        return FiscalDeclaration::factory()
            ->forCompany($this->company)
            ->forYear($year)
            ->generated()
            ->create();
    }

    /**
     * Crée un contrat sans déclencher les observers : on isole la
     * logique du Detector pour qu'il soit testé seul, sans la cascade
     * automatique du `ContractObserver` qui réinvoquerait le Detector
     * pendant la fixture et polluerait les assertions sur les motifs.
     */
    private function makeContract(string $start, string $end): Contract
    {
        return Contract::withoutEvents(
            fn (): Contract => Contract::factory()->create([
                'company_id' => $this->company->id,
                'vehicle_id' => $this->vehicle->id,
                'start_date' => $start,
                'end_date' => $end,
                'contract_type' => Contract::deriveTypeFromDates($start, $end),
            ]),
        );
    }

    private function makeVehicleEvent(VehicleEventType $type, string $start, string $end): VehicleEvent
    {
        return VehicleEvent::withoutEvents(
            fn (): VehicleEvent => VehicleEvent::factory()->create([
                'vehicle_id' => $this->vehicle->id,
                'type' => $type,
                'start_date' => $start,
                'end_date' => $end,
                'has_fiscal_impact' => $type->isFiscallyReductive(),
            ]),
        );
    }
}
