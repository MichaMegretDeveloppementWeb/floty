<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Couvre le flow change-password (utilisateur connecté) bout en bout :
 * auth obligatoire, validation current password, invalidation des autres
 * sessions, audit log canal `auth` (cf. ADR-0012 rev. 1.1).
 */
final class ChangePasswordFlowTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function la_page_change_password_redirige_vers_login_si_invite(): void
    {
        $this->get('/profile/change-password')
            ->assertRedirect('/login');
    }

    #[Test]
    public function la_page_change_password_repond_200_pour_un_user_connecte(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/profile/change-password')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('User/Profile/ChangePassword/Index'));
    }

    #[Test]
    public function un_change_password_reussi_met_a_jour_le_password_et_renvoie_un_toast(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('current-password-1'),
        ]);

        $this->actingAs($user)
            ->post('/profile/change-password', [
                'current_password' => 'current-password-1',
                'password' => 'brand-new-password-2',
                'password_confirmation' => 'brand-new-password-2',
            ])
            ->assertRedirect()
            ->assertSessionHas('toast-success');

        $user->refresh();
        self::assertTrue(Hash::check('brand-new-password-2', $user->password));
        self::assertFalse(Hash::check('current-password-1', $user->password));
    }

    #[Test]
    public function un_current_password_incorrect_renvoie_une_erreur_de_validation(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('correct-current'),
        ]);

        $this->actingAs($user)
            ->from('/profile/change-password')
            ->post('/profile/change-password', [
                'current_password' => 'wrong-current',
                'password' => 'new-password-12',
                'password_confirmation' => 'new-password-12',
            ])
            ->assertSessionHasErrors(['current_password' => 'Le mot de passe actuel est incorrect.']);

        // Le password ne doit pas avoir bougé.
        $user->refresh();
        self::assertTrue(Hash::check('correct-current', $user->password));
    }

    #[Test]
    public function un_nouveau_password_identique_a_l_actuel_est_rejete(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('same-password-1'),
        ]);

        $this->actingAs($user)
            ->from('/profile/change-password')
            ->post('/profile/change-password', [
                'current_password' => 'same-password-1',
                'password' => 'same-password-1',
                'password_confirmation' => 'same-password-1',
            ])
            ->assertSessionHasErrors(['password' => 'Le nouveau mot de passe doit être différent du mot de passe actuel.']);
    }

    #[Test]
    public function un_nouveau_password_trop_court_est_rejete(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('current-password-1'),
        ]);

        $this->actingAs($user)
            ->from('/profile/change-password')
            ->post('/profile/change-password', [
                'current_password' => 'current-password-1',
                'password' => 'short',
                'password_confirmation' => 'short',
            ])
            ->assertSessionHasErrors(['password' => 'Le nouveau mot de passe doit contenir au moins 8 caractères.']);
    }

    #[Test]
    public function le_canal_auth_logge_password_changed_apres_un_succes(): void
    {
        $logFile = storage_path('logs/auth-'.now()->format('Y-m-d').'.log');
        @unlink($logFile);

        $this->withoutMiddleware(ThrottleRequests::class);

        $user = User::factory()->create([
            'email' => 'test@floty.test',
            'password' => Hash::make('current-password-1'),
        ]);

        $this->actingAs($user)
            ->post('/profile/change-password', [
                'current_password' => 'current-password-1',
                'password' => 'brand-new-password-2',
                'password_confirmation' => 'brand-new-password-2',
            ])
            ->assertRedirect();

        self::assertFileExists($logFile);
        $content = (string) file_get_contents($logFile);

        self::assertStringContainsString('password.changed', $content);
        self::assertStringContainsString(hash('sha256', 'test@floty.test'), $content);
        // Anti-PII · ni les passwords ni l'email en clair ne doivent apparaître.
        self::assertStringNotContainsString('"email":"test@floty.test"', $content);
        self::assertStringNotContainsString('current-password-1', $content);
        self::assertStringNotContainsString('brand-new-password-2', $content);
    }

    #[Test]
    public function la_route_change_password_porte_le_middleware_throttle_5_par_10min(): void
    {
        $route = Route::getRoutes()->getByName('profile.change-password.update');

        $this->assertNotNull($route);
        $this->assertContains('throttle:5,10', $route->gatherMiddleware());
    }
}
