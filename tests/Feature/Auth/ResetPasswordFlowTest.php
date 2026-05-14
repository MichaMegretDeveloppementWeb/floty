<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Couvre le flow reset-password bout en bout · affichage formulaire,
 * réinitialisation réussie, token expiré, token forgé, validation
 * password (min:8 + confirmation), audit log canal `auth`.
 *
 * Cf. plan-remédiation Vague 1 Lot 2 D4.3 (F-10-006) + ADR-0012 rev. 1.1.
 */
final class ResetPasswordFlowTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function la_page_reset_password_affiche_le_formulaire_avec_token_et_email_prefills(): void
    {
        $this->get('/reset-password/some-token?email=test@floty.test')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Auth/ResetPassword/Index')
                ->where('token', 'some-token')
                ->where('email', 'test@floty.test'),
            );
    }

    #[Test]
    public function un_token_valide_reset_le_password_et_redirige_vers_login_avec_toast(): void
    {
        $user = User::factory()->create([
            'email' => 'test@floty.test',
            'password' => Hash::make('old-password'),
        ]);

        $token = Password::createToken($user);

        $this->post('/reset-password', [
            'token' => $token,
            'email' => 'test@floty.test',
            'password' => 'new-password-12',
            'password_confirmation' => 'new-password-12',
        ])
            ->assertRedirect('/login')
            ->assertSessionHas('toast-success');

        // Le password a bien été mis à jour.
        $user->refresh();
        self::assertTrue(Hash::check('new-password-12', $user->password));
        self::assertFalse(Hash::check('old-password', $user->password));
    }

    #[Test]
    public function un_token_inconnu_renvoie_le_message_generique_invalide_ou_expire(): void
    {
        User::factory()->create([
            'email' => 'test@floty.test',
            'password' => Hash::make('old-password'),
        ]);

        $this->from('/reset-password/forged-token')
            ->post('/reset-password', [
                'token' => 'forged-token',
                'email' => 'test@floty.test',
                'password' => 'new-password-12',
                'password_confirmation' => 'new-password-12',
            ])
            ->assertSessionHasErrors(['email' => 'Le lien de réinitialisation est invalide ou a expiré. Veuillez en demander un nouveau.']);
    }

    #[Test]
    public function un_password_trop_court_renvoie_une_erreur_de_validation_min_8(): void
    {
        $this->from('/reset-password/whatever')
            ->post('/reset-password', [
                'token' => 'whatever',
                'email' => 'test@floty.test',
                'password' => 'short',
                'password_confirmation' => 'short',
            ])
            ->assertSessionHasErrors(['password' => 'Le mot de passe doit contenir au moins 8 caractères.']);
    }

    #[Test]
    public function une_confirmation_password_qui_ne_match_pas_renvoie_une_erreur_validation(): void
    {
        $this->from('/reset-password/whatever')
            ->post('/reset-password', [
                'token' => 'whatever',
                'email' => 'test@floty.test',
                'password' => 'valid-password-12',
                'password_confirmation' => 'different-password',
            ])
            ->assertSessionHasErrors(['password' => 'La confirmation du mot de passe ne correspond pas.']);
    }

    #[Test]
    public function le_canal_auth_logge_password_reset_completed_quand_le_token_est_valide(): void
    {
        $logFile = storage_path('logs/auth-'.now()->format('Y-m-d').'.log');
        @unlink($logFile);

        $this->withoutMiddleware(ThrottleRequests::class);

        $user = User::factory()->create([
            'email' => 'test@floty.test',
            'password' => Hash::make('old-password'),
        ]);

        $token = Password::createToken($user);

        $this->post('/reset-password', [
            'token' => $token,
            'email' => 'test@floty.test',
            'password' => 'new-password-12',
            'password_confirmation' => 'new-password-12',
        ])->assertRedirect('/login');

        self::assertFileExists($logFile);
        $content = (string) file_get_contents($logFile);

        self::assertStringContainsString('password.reset_completed', $content);
        self::assertStringContainsString(hash('sha256', 'test@floty.test'), $content);
        // Anti-PII · email en clair + nouveau password ne doivent pas leak.
        self::assertStringNotContainsString('"email":"test@floty.test"', $content);
        self::assertStringNotContainsString('new-password-12', $content);
    }

    #[Test]
    public function la_route_reset_password_porte_le_middleware_throttle_5_par_15min(): void
    {
        $route = Route::getRoutes()->getByName('password.update');

        $this->assertNotNull($route);
        $this->assertContains('throttle:5,15', $route->gatherMiddleware());
    }
}
