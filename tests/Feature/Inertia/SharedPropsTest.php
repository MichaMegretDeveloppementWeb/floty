<?php

declare(strict_types=1);

namespace Tests\Feature\Inertia;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Vérifie la shape des shared props Inertia exposées par {@see HandleInertiaRequests}.
 */
final class SharedPropsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function shared_props_exposees_avec_la_bonne_shape_pour_user_authentifie(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/app/dashboard')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('appName')
                ->has('auth.user', fn (AssertableInertia $u) => $u
                    ->where('id', $user->id)
                    ->where('email', $user->email)
                    ->where('firstName', $user->first_name)
                    ->where('lastName', $user->last_name)
                    ->where('fullName', $user->full_name)
                    ->etc())
                ->has('flash', fn (AssertableInertia $f) => $f
                    ->where('success', null)
                    ->where('error', null)
                    ->where('warning', null)
                    ->where('info', null)
                    ->where('toasts', []))
                ->etc(),
            );
    }

    #[Test]
    public function un_toast_flashe_est_expose_dans_flash_toasts(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['toast-success' => 'Véhicule retiré de la flotte.'])
            ->get('/app/dashboard')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('flash.success', 'Véhicule retiré de la flotte.')
                ->has('flash.toasts', 1, fn (AssertableInertia $toast) => $toast
                    ->where('tone', 'success')
                    ->where('message', 'Véhicule retiré de la flotte.')
                    ->has('id'))
                ->etc(),
            );
    }

    #[Test]
    public function deux_requetes_avec_le_meme_message_produisent_des_ids_de_toast_distincts(): void
    {
        // Garde-fou anti-régression : un id dérivé du hash du contenu ferait
        // dédupliquer côté front une action répétée (même message) comme un
        // simple retour arrière, et le 2e toast ne s'afficherait jamais. L'id
        // doit être unique par requête.
        $user = User::factory()->create();
        $ids = [];

        foreach (range(1, 2) as $ignored) {
            $response = $this->actingAs($user)
                ->withSession(['toast-success' => 'Événement ajouté.'])
                ->get('/app/dashboard')
                ->assertOk();

            $page = $this->inertiaPage($response);
            $ids[] = $page['props']['flash']['toasts'][0]['id'];
        }

        self::assertCount(2, $ids);
        self::assertNotSame($ids[0], $ids[1]);
    }

    /**
     * Extract the fully-serialized Inertia page payload from a test response
     * (same round-trip as {@see AssertableInertia::fromTestResponse}).
     *
     * @param  TestResponse<\Illuminate\Http\Response>  $response
     * @return array{props: array{flash: array{toasts: list<array{id: string, tone: string, message: string}>}}}
     */
    private function inertiaPage(TestResponse $response): array
    {
        /** @var array{props: array{flash: array{toasts: list<array{id: string, tone: string, message: string}>}}} $page */
        $page = json_decode((string) json_encode($response->viewData('page')), true);

        return $page;
    }

    #[Test]
    public function shared_props_pour_guest_exposent_auth_user_null(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('appName')
                ->where('auth.user', null)
                ->has('flash', fn (AssertableInertia $f) => $f
                    ->where('success', null)
                    ->where('error', null)
                    ->where('warning', null)
                    ->where('info', null)
                    ->where('toasts', []))
                ->etc(),
            );
    }
}
