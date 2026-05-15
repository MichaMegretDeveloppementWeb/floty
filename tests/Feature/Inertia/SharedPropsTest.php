<?php

declare(strict_types=1);

namespace Tests\Feature\Inertia;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Vérifie la shape des shared props Inertia exposées par
 * {@see HandleInertiaRequests}.
 *
 * Le typage front (TypeScript) repose sur ces props ; tout
 * changement de structure côté backend doit casser ce test.
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
                    ->where('info', null))
                ->etc(),
            );
    }

    #[Test]
    public function shared_props_pour_guest_exposent_auth_user_null(): void
    {
        // Garde-fou Lot 6 D9 (F-32-010) · vérifier que la branche guest
        // de `HandleInertiaRequests::resolveAuthenticatedUser()` ne fuit
        // pas un objet CurrentUserData non-nul (ce qui pourrait casser
        // le typage TS `auth.user: User | null` côté front).
        $this->get('/login')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('appName')
                ->where('auth.user', null)
                ->has('flash', fn (AssertableInertia $f) => $f
                    ->where('success', null)
                    ->where('error', null)
                    ->where('warning', null)
                    ->where('info', null))
                ->etc(),
            );
    }
}
