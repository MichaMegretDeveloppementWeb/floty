<?php

declare(strict_types=1);

namespace Tests\Feature\User\Settings;

use App\Models\FiscalRiskSettings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests Feature de la page Paramètres > Détection de risque (Phase 11 D1,
 * ADR-0015 § D7 rev. 1.1).
 */
final class FiscalRiskSettingsControllerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function edit_renvoie_les_valeurs_par_defaut_a_la_premiere_visite(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/app/settings/fiscal-risk')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('User/Settings/FiscalRisk/Index')
                ->has('settings', fn (AssertableInertia $s) => $s
                    ->where('maxInterval', 15)
                    ->where('thresholdLow', 30)
                    ->where('thresholdHigh', 90)
                    ->where('countHigh', 5)
                    ->etc()));

        // Le singleton est créé à la volée par le repo lors du premier hit.
        self::assertSame(1, FiscalRiskSettings::query()->count());
    }

    #[Test]
    public function update_persiste_les_valeurs_et_retourne_un_toast_success(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/app/settings/fiscal-risk', [
                'max_interval' => 7,
                'threshold_low' => 20,
                'threshold_high' => 60,
                'count_high' => 3,
            ])
            ->assertRedirect()
            ->assertSessionHas('toast-success');

        $settings = FiscalRiskSettings::singleton();
        self::assertSame(7, $settings->max_interval);
        self::assertSame(20, $settings->threshold_low);
        self::assertSame(60, $settings->threshold_high);
        self::assertSame(3, $settings->count_high);
    }

    #[Test]
    public function update_idempotent_ne_cree_pas_de_doublon(): void
    {
        $user = User::factory()->create();

        $payload = [
            'max_interval' => 10,
            'threshold_low' => 30,
            'threshold_high' => 90,
            'count_high' => 5,
            'lld_breaks_chain' => true,
        ];

        $this->actingAs($user)->post('/app/settings/fiscal-risk', $payload)->assertRedirect();
        $this->actingAs($user)->post('/app/settings/fiscal-risk', $payload)->assertRedirect();
        $this->actingAs($user)->post('/app/settings/fiscal-risk', $payload)->assertRedirect();

        self::assertSame(1, FiscalRiskSettings::query()->count());
    }

    #[Test]
    public function update_rejette_threshold_high_inferieur_au_low(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/app/settings/fiscal-risk', [
                'max_interval' => 15,
                'threshold_low' => 50,
                'threshold_high' => 30,
                'count_high' => 5,
            ])
            ->assertSessionHasErrors(['threshold_high']);
    }

    #[Test]
    public function update_rejette_max_interval_zero(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/app/settings/fiscal-risk', [
                'max_interval' => 0,
                'threshold_low' => 30,
                'threshold_high' => 90,
                'count_high' => 5,
            ])
            ->assertSessionHasErrors(['max_interval']);
    }

    #[Test]
    public function utilisateur_non_authentifie_redirige_vers_login(): void
    {
        $this->get('/app/settings/fiscal-risk')->assertRedirect('/login');
    }
}
