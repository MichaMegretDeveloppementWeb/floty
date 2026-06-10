<?php

declare(strict_types=1);

namespace Tests\Feature\User\Planning;

use App\Data\User\Planning\PlanningExportRequestData;
use App\Enums\Planning\PlanningExportMode;
use App\Models\Company;
use App\Models\Contract;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleFiscalCharacteristics;
use App\Services\Pdf\BladeDomPdfPlanningRenderer;
use App\Services\Planning\PlanningExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class PlanningExportTest extends TestCase
{
    use RefreshDatabase;

    /**
     * One vehicle used by companyA on ISO week 10 and companyB on week 20.
     *
     * @return array{Vehicle, VehicleFiscalCharacteristics, Company, Company}
     */
    private function seedFleet(int $year): array
    {
        $vehicle = Vehicle::factory()->create();
        $fiscal = VehicleFiscalCharacteristics::factory()->create(['vehicle_id' => $vehicle->id]);
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();

        $weekA = Carbon::now()->setISODate($year, 10)->startOfWeek();
        Contract::factory()->forVehicle($vehicle)->forCompany($companyA)->create([
            'start_date' => $weekA->toDateString(),
            'end_date' => $weekA->toDateString(),
        ]);

        $weekB = Carbon::now()->setISODate($year, 20)->startOfWeek();
        Contract::factory()->forVehicle($vehicle)->forCompany($companyB)->create([
            'start_date' => $weekB->toDateString(),
            'end_date' => $weekB->toDateString(),
        ]);

        return [$vehicle, $fiscal, $companyA, $companyB];
    }

    #[Test]
    public function service_recompute_les_jours_hebdomadaires_et_les_scope_par_entreprise(): void
    {
        $year = 2024;
        [$vehicle, , $companyA] = $this->seedFleet($year);

        $service = $this->app->make(PlanningExportService::class);

        $scoped = $service->build(new PlanningExportRequestData(
            vehicleIds: [$vehicle->id],
            year: $year,
            mode: PlanningExportMode::Complete,
            companyId: $companyA->id,
        ));

        $global = $service->build(new PlanningExportRequestData(
            vehicleIds: [$vehicle->id],
            year: $year,
            mode: PlanningExportMode::Complete,
            companyId: null,
        ));

        self::assertCount(53, $scoped->rows[0]->weeks);

        // Scope entreprise A · seule la semaine 10 (index 9) est comptée.
        self::assertSame(1, $scoped->rows[0]->weeks[9]);
        self::assertSame(0, $scoped->rows[0]->weeks[19]);
        self::assertSame(1, $scoped->rows[0]->daysTotal);
        self::assertSame($companyA->legal_name, $scoped->companyName);
        self::assertSame($companyA->short_code, $scoped->companyShortCode);

        // Vue d'ensemble · les deux semaines (10 et 20) sont comptées.
        self::assertSame(1, $global->rows[0]->weeks[9]);
        self::assertSame(1, $global->rows[0]->weeks[19]);
        self::assertSame(2, $global->rows[0]->daysTotal);
        self::assertNull($global->companyName);
        self::assertNull($global->companyShortCode);

        // Montant recalculé côté serveur (année avec règles fiscales) · la
        // taxe pleine annuelle est strictement positive. La taxe réelle
        // (ici 2 jours d'usage) est seulement garantie calculée et non
        // négative · son scope par entreprise est couvert par
        // PlanningHeatmapServiceTest (logique identique réutilisée).
        self::assertGreaterThan(0.0, $global->rows[0]->fullYearTax);
        self::assertGreaterThanOrEqual(0.0, $global->rows[0]->annualTaxDue);
    }

    #[Test]
    public function service_ignore_les_ids_non_selectionnes(): void
    {
        $year = 2024;
        [$vehicle] = $this->seedFleet($year);
        $other = Vehicle::factory()->create();
        VehicleFiscalCharacteristics::factory()->create(['vehicle_id' => $other->id]);

        $data = $this->app->make(PlanningExportService::class)->build(new PlanningExportRequestData(
            vehicleIds: [$vehicle->id],
            year: $year,
            mode: PlanningExportMode::Vehicle,
            companyId: null,
        ));

        self::assertCount(1, $data->rows);
        self::assertSame($vehicle->id, $data->rows[0]->id);
    }

    #[Test]
    public function renderer_html_mode_complet_contient_la_plaque_le_perimetre_et_les_montants(): void
    {
        $year = 2024;
        [$vehicle] = $this->seedFleet($year);

        $data = $this->app->make(PlanningExportService::class)->build(new PlanningExportRequestData(
            vehicleIds: [$vehicle->id],
            year: $year,
            mode: PlanningExportMode::Complete,
            companyId: null,
        ));

        $html = $this->app->make(BladeDomPdfPlanningRenderer::class)->renderHtml($data);

        self::assertStringContainsString($vehicle->license_plate, $html);
        // Apostrophe HTML-encoded by Blade (d&#039;utilisation) · assert an
        // apostrophe-free slice of the title.
        self::assertStringContainsString('utilisation de la flotte', $html);
        self::assertStringContainsString('Répartition hebdomadaire', $html);
        self::assertStringContainsString('Exercice '.$year, $html);
        self::assertStringContainsString('Taxe réelle', $html);
        self::assertStringContainsString('€', $html);
        // « Flotte entière » a été retiré (trompeur sur une sélection).
        self::assertStringNotContainsString('Flotte entière', $html);
    }

    #[Test]
    public function renderer_html_mode_vehicule_contient_la_fiche_et_les_champs_fiscaux(): void
    {
        $year = 2024;
        [$vehicle, $fiscal] = $this->seedFleet($year);

        $data = $this->app->make(PlanningExportService::class)->build(new PlanningExportRequestData(
            vehicleIds: [$vehicle->id],
            year: $year,
            mode: PlanningExportMode::Vehicle,
            companyId: null,
        ));

        $html = $this->app->make(BladeDomPdfPlanningRenderer::class)->renderHtml($data);

        self::assertStringContainsString('Récapitulatif des véhicules', $html);
        self::assertStringContainsString('Caractéristiques', $html);
        self::assertStringContainsString('Catégorie polluants', $html);
        self::assertStringContainsString($fiscal->pollutant_category->label(), $html);
        self::assertStringContainsString($fiscal->energy_source->label(), $html);
        self::assertStringContainsString('1re immatriculation', $html);
        self::assertStringContainsString($vehicle->first_french_registration_date->format('d/m/Y'), $html);
    }

    #[Test]
    public function renderer_html_scope_entreprise_affiche_l_entreprise_et_le_nombre(): void
    {
        $year = 2024;
        [$vehicle, , $companyA] = $this->seedFleet($year);

        $data = $this->app->make(PlanningExportService::class)->build(new PlanningExportRequestData(
            vehicleIds: [$vehicle->id],
            year: $year,
            mode: PlanningExportMode::Complete,
            companyId: $companyA->id,
        ));

        $html = $this->app->make(BladeDomPdfPlanningRenderer::class)->renderHtml($data);

        // The company legal name (faker) may contain & or ' that Blade
        // encodes, so assert the label and the scoped DTO value separately.
        self::assertStringContainsString('Entreprise :', $html);
        self::assertSame($companyA->legal_name, $data->companyName);
        self::assertStringContainsString('1 véhicule', $html);
        self::assertStringNotContainsString('Flotte entière', $html);
    }

    #[Test]
    public function export_route_renvoie_un_pdf_en_piece_jointe(): void
    {
        $year = 2024;
        [$vehicle] = $this->seedFleet($year);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/app/planning/export', [
            'vehicle_ids' => [$vehicle->id],
            'year' => $year,
            'mode' => 'complete',
            'company_id' => null,
        ]);

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');

        $disposition = $response->headers->get('Content-Disposition');
        self::assertStringContainsString('attachment', (string) $disposition);
        self::assertStringContainsString('floty-planning-'.$year.'.pdf', (string) $disposition);

        self::assertStringStartsWith('%PDF', $response->getContent());
    }

    #[Test]
    public function export_route_scope_entreprise_nomme_le_fichier_avec_le_code_court(): void
    {
        $year = 2024;
        [$vehicle, , $companyA] = $this->seedFleet($year);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/app/planning/export', [
            'vehicle_ids' => [$vehicle->id],
            'year' => $year,
            'mode' => 'vehicle',
            'company_id' => $companyA->id,
        ]);

        $response->assertOk();
        $disposition = (string) $response->headers->get('Content-Disposition');
        self::assertStringContainsString('floty-planning-'.$companyA->short_code.'-'.$year.'.pdf', $disposition);
    }

    #[Test]
    public function export_route_refuse_une_selection_vide(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/app/planning/export', [
            'vehicle_ids' => [],
            'year' => 2024,
            'mode' => 'complete',
            'company_id' => null,
        ])->assertStatus(422);
    }
}
