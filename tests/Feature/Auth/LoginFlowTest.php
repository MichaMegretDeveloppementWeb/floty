<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\Auth\LoginAttemptService;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Couvre le flow de connexion bout en bout : login OK / KO,
 * rate-limit double couche (email+IP / IP seule), logout, et trace
 * `last_login_at`.
 *
 * Complète {@see UserRoutesAuthTest} qui ne fait que vérifier la
 * protection middleware sur les routes user.*.
 */
final class LoginFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Reset systématique des compteurs RateLimiter pour éviter
        // les fuites d'état entre tests (le RateLimiter est global).
        RateLimiter::clear('login:email:test@floty.test|127.0.0.1');
        RateLimiter::clear('login:ip:127.0.0.1');
    }

    #[Test]
    public function login_ok_cree_session_redirige_dashboard_et_met_a_jour_last_login_at(): void
    {
        $user = User::factory()->create([
            'email' => 'test@floty.test',
            'password' => Hash::make('correct-password'),
            'last_login_at' => null,
        ]);

        $now = Date::parse('2026-04-28 12:00:00');
        Date::setTestNow($now);

        $response = $this->post('/login', [
            'email' => 'test@floty.test',
            'password' => 'correct-password',
        ]);

        $response->assertRedirect('/app/dashboard');
        $this->assertAuthenticatedAs($user);

        $user->refresh();
        $this->assertNotNull($user->last_login_at);
        $this->assertSame($now->toDateTimeString(), $user->last_login_at->toDateTimeString());
    }

    #[Test]
    public function login_ko_renvoie_erreur_validation_sur_email_et_n_authentifie_pas(): void
    {
        User::factory()->create([
            'email' => 'test@floty.test',
            'password' => Hash::make('correct-password'),
        ]);

        $response = $this->from('/login')->post('/login', [
            'email' => 'test@floty.test',
            'password' => 'wrong-password',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors(['email' => 'Identifiants invalides.']);
        $this->assertGuest();
    }

    #[Test]
    public function login_ko_email_inconnu_donne_meme_message_qu_un_password_faux(): void
    {
        $response = $this->from('/login')->post('/login', [
            'email' => 'unknown@floty.test',
            'password' => 'whatever',
        ]);

        $response->assertSessionHasErrors(['email' => 'Identifiants invalides.']);
        $this->assertGuest();
    }

    #[Test]
    public function rate_limit_email_apres_5_tentatives_bloque_avec_message_attente(): void
    {
        User::factory()->create([
            'email' => 'test@floty.test',
            'password' => Hash::make('correct-password'),
        ]);

        Event::fake([Lockout::class]);

        // 5 tentatives ratées → 5e enregistre toujours l'attempt.
        for ($i = 0; $i < LoginAttemptService::MAX_ATTEMPTS_PER_EMAIL; $i++) {
            $this->post('/login', [
                'email' => 'test@floty.test',
                'password' => 'wrong-password',
            ]);
        }

        // 6e tentative → blocage avant même la vérif de password.
        $response = $this->from('/login')->post('/login', [
            'email' => 'test@floty.test',
            'password' => 'correct-password',
        ]);

        $response->assertRedirect('/login');
        $response->assertInvalid(['email' => 'Trop de tentatives. Réessayez dans']);
        $this->assertGuest();

        Event::assertDispatched(Lockout::class);
    }

    #[Test]
    public function rate_limit_ip_apres_50_tentatives_bloque_avec_message_attente(): void
    {
        // Chaque tentative utilise un email différent → seul le compteur
        // IP s'incrémente à 50, le compteur email+IP reste sous 5.
        Event::fake([Lockout::class]);

        for ($i = 0; $i < LoginAttemptService::MAX_ATTEMPTS_PER_IP; $i++) {
            $this->post('/login', [
                'email' => "user{$i}@floty.test",
                'password' => 'wrong-password',
            ]);
        }

        $response = $this->from('/login')->post('/login', [
            'email' => 'autre@floty.test',
            'password' => 'wrong-password',
        ]);

        $response->assertRedirect('/login');
        $response->assertInvalid(['email' => 'Trop de tentatives depuis cette IP']);
        $this->assertGuest();

        Event::assertDispatched(Lockout::class);
    }

    #[Test]
    public function logout_invalide_session_et_redirige_vers_home(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $response->assertRedirect('/');
        $this->assertGuest();
        $this->assertNull(Auth::user());
    }

    #[Test]
    public function un_user_deja_connecte_est_redirige_par_le_middleware_guest(): void
    {
        $user = User::factory()->create();

        // GET /login derrière le middleware `guest` → redirect home (par
        // défaut Laravel `RedirectIfAuthenticated`). Pas un dashboard,
        // c'est un comportement Laravel standard non surchargé en V1.
        $this->actingAs($user)
            ->get('/login')
            ->assertRedirect('/');
    }
}
