<?php

declare(strict_types=1);

namespace Tests\Feature\Exceptions;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Couvre le custom render `ValidationException` JSON : pour les requêtes JSON,
 * le `message` de la réponse 422 doit être un texte FR explicite citant les champs
 * en erreur, pas la clé brute `validation.required`.
 */
final class ValidationExceptionRenderingTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function un_seul_champ_invalide_renvoie_un_message_fr_qui_le_cite(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/app/planning/contracts', [
                'vehicle_ids' => [],
                'company_id' => 1,
                'start_date' => '2025-01-01',
                'end_date' => '2025-01-15',
                'contract_type' => 'lcd',
            ]);

        $response->assertStatus(422);
        $message = $response->json('message');
        $this->assertIsString($message);
        $this->assertStringContainsString('vehicle_ids', $message);
        $this->assertStringNotContainsString('validation.required', $message);
        $response->assertJsonStructure(['errors' => ['vehicle_ids']]);
    }

    #[Test]
    public function plusieurs_champs_invalides_renvoient_un_message_fr_listant_les_champs(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/app/planning/contracts', []);

        $response->assertStatus(422);
        $message = $response->json('message');
        $this->assertIsString($message);
        $this->assertStringContainsString('Plusieurs champs sont invalides', $message);
        $this->assertStringNotContainsString('validation.required', $message);
        $cited = false;
        foreach (['vehicle_ids', 'company_id', 'start_date', 'end_date'] as $f) {
            if (str_contains($message, $f)) {
                $cited = true;
                break;
            }
        }
        $this->assertTrue($cited, 'Le message doit citer au moins un champ requis manquant.');
    }

    #[Test]
    public function les_requetes_html_continuent_a_etre_redirigees_avec_session_errors(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->from('/app/contracts/create')
            ->post('/app/contracts', [
                'vehicle_id' => null,
                'company_id' => null,
                'start_date' => '',
                'end_date' => '',
            ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors();
    }
}
