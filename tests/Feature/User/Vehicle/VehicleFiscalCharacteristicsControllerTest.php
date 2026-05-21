<?php

declare(strict_types=1);

namespace Tests\Feature\User\Vehicle;

use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleFiscalCharacteristics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class VehicleFiscalCharacteristicsControllerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function store_insere_nouvelle_vfc_a_la_fin_et_ferme_la_courante(): void
    {
        // Cas A de la cartographie : insertion en fin → vN.effective_to ← D-1, nv courante.
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        $current = VehicleFiscalCharacteristics::factory()->create([
            'vehicle_id' => $vehicle->id,
            'effective_from' => '2022-01-01',
            'effective_to' => null,
        ]);

        $payload = $this->buildVfcPayload($current, [
            'effective_from' => '2024-06-01',
            'effective_to' => null,
            'change_reason' => 'recharacterization',
        ]);

        $this->actingAs($user)
            ->post("/app/vehicles/{$vehicle->id}/fiscal-characteristics", $payload)
            ->assertRedirect();

        // Précédente fermée au 2024-05-31
        $this->assertDatabaseHas('vehicle_fiscal_characteristics', [
            'id' => $current->id,
            'effective_to' => '2024-05-31',
        ]);
        // Nouvelle créée comme courante
        $this->assertDatabaseHas('vehicle_fiscal_characteristics', [
            'vehicle_id' => $vehicle->id,
            'effective_from' => '2024-06-01',
            'effective_to' => null,
        ]);
    }

    #[Test]
    public function store_refuse_si_effective_from_matche_existante(): void
    {
        // Cas R3 de la cartographie : collision exacte sur effective_from
        // → exception InvalidFiscalCharacteristicsBoundsException → toast erreur, pas d'insertion.
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        $existing = VehicleFiscalCharacteristics::factory()->create([
            'vehicle_id' => $vehicle->id,
            'effective_from' => '2024-01-01',
            'effective_to' => null,
        ]);

        $payload = $this->buildVfcPayload($existing, [
            'effective_from' => '2024-01-01',
            'effective_to' => null,
            'change_reason' => 'recharacterization',
        ]);

        $this->actingAs($user)
            ->post("/app/vehicles/{$vehicle->id}/fiscal-characteristics", $payload)
            ->assertRedirect();

        // Aucune création supplémentaire
        $this->assertSame(1, VehicleFiscalCharacteristics::query()
            ->where('vehicle_id', $vehicle->id)
            ->count());
    }

    #[Test]
    public function store_avec_destructif_sans_confirmed_throw_confirmation_required(): void
    {
        // Cas G de la cartographie : insertion E=NULL avec D antérieur à
        // la courante → engloutit la courante (Delete) → confirmation
        // requise. Sans confirmed=true, l'Action throw et rien n'est persisté.
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        $current = VehicleFiscalCharacteristics::factory()->create([
            'vehicle_id' => $vehicle->id,
            'effective_from' => '2024-06-01',
            'effective_to' => null,
        ]);

        $payload = $this->buildVfcPayload($current, [
            'effective_from' => '2024-01-01',
            'effective_to' => null,
            'change_reason' => 'recharacterization',
            'confirmed' => false,
        ]);

        $this->actingAs($user)
            ->post("/app/vehicles/{$vehicle->id}/fiscal-characteristics", $payload)
            ->assertRedirect();

        // VFC courante toujours présente (pas de Delete)
        $this->assertDatabaseHas('vehicle_fiscal_characteristics', [
            'id' => $current->id,
        ]);
        // Aucune création
        $this->assertSame(1, VehicleFiscalCharacteristics::query()
            ->where('vehicle_id', $vehicle->id)
            ->count());
    }

    #[Test]
    public function store_avec_destructif_et_confirmed_supprime_la_courante_et_insere(): void
    {
        // Suite du cas G : avec confirmed=true, l'Action applique la
        // cascade : Delete v_courante + INSERT nouvelle courante.
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        $current = VehicleFiscalCharacteristics::factory()->create([
            'vehicle_id' => $vehicle->id,
            'effective_from' => '2024-06-01',
            'effective_to' => null,
        ]);

        $payload = $this->buildVfcPayload($current, [
            'effective_from' => '2024-01-01',
            'effective_to' => null,
            'change_reason' => 'recharacterization',
            'confirmed' => true,
        ]);

        $this->actingAs($user)
            ->post("/app/vehicles/{$vehicle->id}/fiscal-characteristics", $payload)
            ->assertRedirect();

        $this->assertDatabaseMissing('vehicle_fiscal_characteristics', [
            'id' => $current->id,
        ]);
        $this->assertDatabaseHas('vehicle_fiscal_characteristics', [
            'vehicle_id' => $vehicle->id,
            'effective_from' => '2024-01-01',
            'effective_to' => null,
        ]);
    }

    #[Test]
    public function store_message_propose_de_retirer_la_date_de_fin_quand_la_contenant_est_courante(): void
    {
        // Cas user-reporté (chantier D) : VFC courante (effective_to=null)
        // contient la nouvelle plage saisie. Le toast-error doit guider
        // vers le fix simple = retirer la date de fin (pas vers la modale
        // d'historique pour raccourcir la courante).
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        $current = VehicleFiscalCharacteristics::factory()->create([
            'vehicle_id' => $vehicle->id,
            'effective_from' => '2024-04-16',
            'effective_to' => null,
        ]);

        $payload = $this->buildVfcPayload($current, [
            'effective_from' => '2024-09-08',
            'effective_to' => '2024-10-12',
            'change_reason' => 'recharacterization',
        ]);

        $response = $this->actingAs($user)
            ->from("/app/vehicles/{$vehicle->id}")
            ->post("/app/vehicles/{$vehicle->id}/fiscal-characteristics", $payload);

        $response->assertRedirect("/app/vehicles/{$vehicle->id}");
        /** @var string|null $message */
        $message = session('toast-error');
        $this->assertIsString($message);
        // Mention de la solution simple
        $this->assertStringContainsString('retirez la date de fin', $message);
        // Date de clôture auto = newFrom-1 = 07/09/2024 (format FR)
        $this->assertStringContainsString('07/09/2024', $message);
        // La date de début existante en FR
        $this->assertStringContainsString('16/04/2024', $message);
        // Pas de pollution avec l'ancien wording
        $this->assertStringNotContainsString('Modifiez ou raccourcissez', $message);
    }

    #[Test]
    public function store_message_generique_quand_la_contenant_est_finie(): void
    {
        // Cas symétrique : la VFC contenante est bornée (finie). Le
        // wording doit rester générique et NE PAS proposer « retirez la
        // date de fin » (ce qui ne résoudrait rien : la nouvelle VFC
        // ouverte chevaucherait toujours la finie par la gauche).
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        VehicleFiscalCharacteristics::factory()->create([
            'vehicle_id' => $vehicle->id,
            'effective_from' => '2023-01-01',
            'effective_to' => '2024-12-31',
        ]);
        $current = VehicleFiscalCharacteristics::factory()->create([
            'vehicle_id' => $vehicle->id,
            'effective_from' => '2025-01-01',
            'effective_to' => null,
        ]);

        $payload = $this->buildVfcPayload($current, [
            'effective_from' => '2023-06-01',
            'effective_to' => '2023-07-31',
            'change_reason' => 'recharacterization',
        ]);

        $response = $this->actingAs($user)
            ->from("/app/vehicles/{$vehicle->id}")
            ->post("/app/vehicles/{$vehicle->id}/fiscal-characteristics", $payload);

        $response->assertRedirect("/app/vehicles/{$vehicle->id}");
        /** @var string|null $message */
        $message = session('toast-error');
        $this->assertIsString($message);
        // Le message cite la plage finie complète en FR
        $this->assertStringContainsString('01/01/2023', $message);
        $this->assertStringContainsString('31/12/2024', $message);
        // Ancien wording générique conservé
        $this->assertStringContainsString('Modifiez ou raccourcissez', $message);
        // Pas la mention courante
        $this->assertStringNotContainsString('retirez la date de fin', $message);
    }

    #[Test]
    public function store_refuse_si_nouvelle_plage_est_strictement_contenue_dans_une_existante(): void
    {
        // R4 (parité TS) : insertion d'une plage [newFrom, newTo]
        // strictement contenue dans une VFC existante → exception
        // InvalidFiscalCharacteristicsBoundsException → toast erreur
        // (sans ce garde-fou, le trigger DB rejette avec un message
        // technique opaque).
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        $previous = VehicleFiscalCharacteristics::factory()->create([
            'vehicle_id' => $vehicle->id,
            'effective_from' => '2023-08-20',
            'effective_to' => '2024-06-15',
        ]);
        $current = VehicleFiscalCharacteristics::factory()->create([
            'vehicle_id' => $vehicle->id,
            'effective_from' => '2024-06-16',
            'effective_to' => null,
        ]);

        $payload = $this->buildVfcPayload($current, [
            'effective_from' => '2023-09-16',
            'effective_to' => '2023-12-20',
            'change_reason' => 'recharacterization',
        ]);

        $this->actingAs($user)
            ->from("/app/vehicles/{$vehicle->id}")
            ->post("/app/vehicles/{$vehicle->id}/fiscal-characteristics", $payload)
            ->assertRedirect("/app/vehicles/{$vehicle->id}")
            ->assertSessionHas('toast-error');

        // Aucune création supplémentaire et bornes existantes intactes
        $this->assertSame(2, VehicleFiscalCharacteristics::query()
            ->where('vehicle_id', $vehicle->id)
            ->count());
        $this->assertDatabaseHas('vehicle_fiscal_characteristics', [
            'id' => $previous->id,
            'effective_from' => '2023-08-20',
            'effective_to' => '2024-06-15',
        ]);
    }

    #[Test]
    public function update_modifie_une_vfc_isolee_depuis_la_modale_historique(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        $vfc = VehicleFiscalCharacteristics::factory()->create([
            'vehicle_id' => $vehicle->id,
            'effective_from' => '2024-01-01',
            'effective_to' => null,
            'seats_count' => 5,
        ]);

        $payload = $this->buildVfcPayload($vfc, ['seats_count' => 9]);

        $this->actingAs($user)
            ->patch("/app/vehicle-fiscal-characteristics/{$vfc->id}", $payload)
            ->assertRedirect();

        $this->assertDatabaseHas('vehicle_fiscal_characteristics', [
            'id' => $vfc->id,
            'seats_count' => 9,
        ]);
    }

    #[Test]
    public function destroy_avec_extend_next_supprime_la_vfc_et_etend_la_suivante(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();

        $oldest = VehicleFiscalCharacteristics::factory()->create([
            'vehicle_id' => $vehicle->id,
            'effective_from' => '2022-01-01',
            'effective_to' => '2023-12-31',
        ]);
        $current = VehicleFiscalCharacteristics::factory()->create([
            'vehicle_id' => $vehicle->id,
            'effective_from' => '2024-01-01',
            'effective_to' => null,
        ]);

        $this->actingAs($user)
            ->delete(
                "/app/vehicle-fiscal-characteristics/{$oldest->id}",
                ['extension_strategy' => 'extend_next'],
            )
            ->assertRedirect();

        $this->assertDatabaseMissing('vehicle_fiscal_characteristics', [
            'id' => $oldest->id,
        ]);
        $this->assertDatabaseHas('vehicle_fiscal_characteristics', [
            'id' => $current->id,
            'effective_from' => '2022-01-01',
        ]);
    }

    #[Test]
    public function destroy_avec_extend_previous_supprime_la_vfc_et_etend_la_precedente(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();

        $oldest = VehicleFiscalCharacteristics::factory()->create([
            'vehicle_id' => $vehicle->id,
            'effective_from' => '2022-01-01',
            'effective_to' => '2023-12-31',
        ]);
        $current = VehicleFiscalCharacteristics::factory()->create([
            'vehicle_id' => $vehicle->id,
            'effective_from' => '2024-01-01',
            'effective_to' => null,
        ]);

        $this->actingAs($user)
            ->delete(
                "/app/vehicle-fiscal-characteristics/{$current->id}",
                ['extension_strategy' => 'extend_previous'],
            )
            ->assertRedirect();

        $this->assertDatabaseMissing('vehicle_fiscal_characteristics', [
            'id' => $current->id,
        ]);
        // La précédente reprend le rôle de courante (effective_to = null).
        $this->assertDatabaseHas('vehicle_fiscal_characteristics', [
            'id' => $oldest->id,
            'effective_to' => null,
        ]);
    }

    #[Test]
    public function update_decalage_avant_etend_automatiquement_la_precedente_pour_combler_le_trou(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        $previous = VehicleFiscalCharacteristics::factory()->create([
            'vehicle_id' => $vehicle->id,
            'effective_from' => '2023-01-01',
            'effective_to' => '2023-12-31',
        ]);
        $current = VehicleFiscalCharacteristics::factory()->create([
            'vehicle_id' => $vehicle->id,
            'effective_from' => '2024-01-01',
            'effective_to' => null,
        ]);

        $payload = $this->buildVfcPayload(
            $current,
            ['effective_from' => '2024-03-15'],
        );

        $this->actingAs($user)
            ->patch("/app/vehicle-fiscal-characteristics/{$current->id}", $payload)
            ->assertRedirect()
            ->assertSessionHas('toast-success')
            ->assertSessionHas('toast-info');

        $this->assertDatabaseHas('vehicle_fiscal_characteristics', [
            'id' => $current->id,
            'effective_from' => '2024-03-15',
            'effective_to' => null,
        ]);
        $this->assertDatabaseHas('vehicle_fiscal_characteristics', [
            'id' => $previous->id,
            'effective_to' => '2024-03-14',
        ]);
    }

    #[Test]
    public function update_decalage_arriere_chevauche_partiellement_raccourcit_la_precedente(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        $previous = VehicleFiscalCharacteristics::factory()->create([
            'vehicle_id' => $vehicle->id,
            'effective_from' => '2023-01-01',
            'effective_to' => '2023-12-31',
        ]);
        $current = VehicleFiscalCharacteristics::factory()->create([
            'vehicle_id' => $vehicle->id,
            'effective_from' => '2024-01-01',
            'effective_to' => null,
        ]);

        $payload = $this->buildVfcPayload(
            $current,
            ['effective_from' => '2023-06-15'],
        );

        $this->actingAs($user)
            ->patch("/app/vehicle-fiscal-characteristics/{$current->id}", $payload)
            ->assertRedirect()
            ->assertSessionHas('toast-info');

        $this->assertDatabaseHas('vehicle_fiscal_characteristics', [
            'id' => $current->id,
            'effective_from' => '2023-06-15',
        ]);
        $this->assertDatabaseHas('vehicle_fiscal_characteristics', [
            'id' => $previous->id,
            'effective_to' => '2023-06-14',
        ]);
    }

    #[Test]
    public function update_chevauchement_total_sans_confirmation_refuse_avec_toast_d_avertissement(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        $previous = VehicleFiscalCharacteristics::factory()->create([
            'vehicle_id' => $vehicle->id,
            'effective_from' => '2023-06-01',
            'effective_to' => '2023-12-31',
        ]);
        $current = VehicleFiscalCharacteristics::factory()->create([
            'vehicle_id' => $vehicle->id,
            'effective_from' => '2024-01-01',
            'effective_to' => null,
        ]);

        // Reculer effective_from à 01/01/2023 → engloutit entièrement
        // la précédente (qui démarre au 01/06/2023).
        $payload = $this->buildVfcPayload(
            $current,
            ['effective_from' => '2023-01-01'],
        );

        $this->actingAs($user)
            ->from("/app/vehicles/{$vehicle->id}")
            ->patch("/app/vehicle-fiscal-characteristics/{$current->id}", $payload)
            ->assertRedirect("/app/vehicles/{$vehicle->id}")
            ->assertSessionHas('toast-error');

        // Aucun changement appliqué : la précédente est intacte, la
        // courante n'a pas bougé.
        $this->assertDatabaseHas('vehicle_fiscal_characteristics', [
            'id' => $previous->id,
            'effective_from' => '2023-06-01',
            'effective_to' => '2023-12-31',
        ]);
        $this->assertDatabaseHas('vehicle_fiscal_characteristics', [
            'id' => $current->id,
            'effective_from' => '2024-01-01',
        ]);
    }

    #[Test]
    public function update_chevauchement_total_avec_confirmation_supprime_la_voisine(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        $previous = VehicleFiscalCharacteristics::factory()->create([
            'vehicle_id' => $vehicle->id,
            'effective_from' => '2023-06-01',
            'effective_to' => '2023-12-31',
        ]);
        $current = VehicleFiscalCharacteristics::factory()->create([
            'vehicle_id' => $vehicle->id,
            'effective_from' => '2024-01-01',
            'effective_to' => null,
        ]);

        $payload = $this->buildVfcPayload(
            $current,
            [
                'effective_from' => '2023-01-01',
                'confirmed' => true,
            ],
        );

        $this->actingAs($user)
            ->patch("/app/vehicle-fiscal-characteristics/{$current->id}", $payload)
            ->assertRedirect()
            ->assertSessionHas('toast-success')
            ->assertSessionHas('toast-info');

        $this->assertDatabaseMissing('vehicle_fiscal_characteristics', [
            'id' => $previous->id,
        ]);
        $this->assertDatabaseHas('vehicle_fiscal_characteristics', [
            'id' => $current->id,
            'effective_from' => '2023-01-01',
        ]);
    }

    #[Test]
    public function update_borne_droite_invalide_renvoie_un_toast_error(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        $vfc = VehicleFiscalCharacteristics::factory()->create([
            'vehicle_id' => $vehicle->id,
            'effective_from' => '2023-01-01',
            'effective_to' => '2023-12-31',
        ]);

        $payload = $this->buildVfcPayload(
            $vfc,
            [
                'effective_from' => '2023-12-01',
                'effective_to' => '2023-06-01',
            ],
        );

        $this->actingAs($user)
            ->from("/app/vehicles/{$vehicle->id}")
            ->patch("/app/vehicle-fiscal-characteristics/{$vfc->id}", $payload)
            ->assertRedirect("/app/vehicles/{$vehicle->id}")
            ->assertSessionHas('toast-error');

        $this->assertDatabaseHas('vehicle_fiscal_characteristics', [
            'id' => $vfc->id,
            'effective_from' => '2023-01-01',
            'effective_to' => '2023-12-31',
        ]);
    }

    #[Test]
    public function destroy_refuse_si_unique_version(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        $vfc = VehicleFiscalCharacteristics::factory()->create([
            'vehicle_id' => $vehicle->id,
            'effective_from' => '2024-01-01',
            'effective_to' => null,
        ]);

        $this->actingAs($user)
            ->from("/app/vehicles/{$vehicle->id}")
            ->delete(
                "/app/vehicle-fiscal-characteristics/{$vfc->id}",
                ['extension_strategy' => 'extend_previous'],
            )
            ->assertRedirect("/app/vehicles/{$vehicle->id}")
            ->assertSessionHas('toast-error');

        // VFC toujours présente.
        $this->assertDatabaseHas('vehicle_fiscal_characteristics', [
            'id' => $vfc->id,
        ]);
    }

    #[Test]
    public function update_derive_vehicle_user_type_depuis_reception_category(): void
    {
        // Régression prod (MySQL 8+) : le payload force `vehicle_user_type = VU`
        // tout en laissant `reception_category = M1`. Sans la dérivation côté
        // repository, l'UPDATE partiel d'Eloquent ne pousse que
        // `vehicle_user_type`, ce qui viole la contrainte CHECK
        // `chk_vfc_user_type_consistent_with_reception` et déclenche un
        // SQLSTATE 23000 / erreur 500. Le repo doit donc ignorer la valeur
        // d'entrée et la recalculer depuis `reception_category`.
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        $vfc = VehicleFiscalCharacteristics::factory()->create([
            'vehicle_id' => $vehicle->id,
            'effective_from' => '2024-01-01',
            'effective_to' => null,
            'reception_category' => 'M1',
            'vehicle_user_type' => 'VP',
        ]);

        $payload = $this->buildVfcPayload($vfc, [
            'reception_category' => 'M1',
            'vehicle_user_type' => 'VU',
        ]);

        $this->actingAs($user)
            ->patch("/app/vehicle-fiscal-characteristics/{$vfc->id}", $payload)
            ->assertRedirect();

        $this->assertDatabaseHas('vehicle_fiscal_characteristics', [
            'id' => $vfc->id,
            'reception_category' => 'M1',
            'vehicle_user_type' => 'VP',
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function buildVfcPayload(VehicleFiscalCharacteristics $vfc, array $overrides = []): array
    {
        return array_merge([
            'effective_from' => $vfc->effective_from->format('Y-m-d'),
            'effective_to' => $vfc->effective_to?->format('Y-m-d'),
            'reception_category' => $vfc->reception_category->value,
            'vehicle_user_type' => $vfc->vehicle_user_type->value,
            'body_type' => $vfc->body_type->value,
            'seats_count' => $vfc->seats_count,
            'energy_source' => $vfc->energy_source->value,
            'underlying_combustion_engine_type' => $vfc->underlying_combustion_engine_type?->value,
            'euro_standard' => $vfc->euro_standard?->value,
            'homologation_method' => $vfc->homologation_method->value,
            'co2_wltp' => $vfc->co2_wltp,
            'co2_nedc' => $vfc->co2_nedc,
            'taxable_horsepower' => $vfc->taxable_horsepower,
            'kerb_mass' => $vfc->kerb_mass,
            'handicap_access' => $vfc->handicap_access,
            'm1_special_use' => $vfc->m1_special_use,
            'n1_passenger_transport' => $vfc->n1_passenger_transport,
            'n1_removable_second_row_seat' => $vfc->n1_removable_second_row_seat,
            'n1_ski_lift_use' => $vfc->n1_ski_lift_use,
            'change_reason' => $vfc->change_reason->value === 'initial_creation'
                ? 'recharacterization'
                : $vfc->change_reason->value,
            'change_note' => $vfc->change_note,
        ], $overrides);
    }
}
