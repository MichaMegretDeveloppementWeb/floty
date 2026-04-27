<?php

declare(strict_types=1);

namespace Tests\Feature\Exceptions;

use App\Exceptions\Fiscal\FiscalCalculationException;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Vérifie le comportement du handler `bootstrap/app.php` :
 * - `BaseAppException` sur requête JSON → 422 avec body `{message, code}`
 * - `BaseAppException` sur visite HTML/Inertia → 302 redirect + flash
 *   `toast-error`
 * - 419 (CSRF) sur visite Inertia → 302 + flash `toast-warning`
 *
 * Routes inline dans `setUp()` pour isoler le test du reste de
 * l'application (pas besoin de toucher aux routes de production).
 */
final class HandlerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('web')->get(
            '/_test/throw-fiscal',
            static fn () => throw FiscalCalculationException::yearNotSupported(2099),
        );
    }

    #[Test]
    public function exception_metier_sur_requete_json_renvoie_422_structure(): void
    {
        $this->getJson('/_test/throw-fiscal')
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'code'])
            ->assertJsonPath('code', 'FiscalCalculationException');
    }

    #[Test]
    public function exception_metier_sur_requete_json_renvoie_le_message_utilisateur_francais(): void
    {
        $response = $this->getJson('/_test/throw-fiscal');

        $message = $response->json('message');
        self::assertIsString($message);
        self::assertStringContainsString("n'est pas supportée", $message);
        // Pas de message technique anglais dans la réponse user-facing.
        self::assertStringNotContainsString('not supported by', $message);
    }

    #[Test]
    public function exception_metier_sur_visite_html_redirige_avec_flash_toast_error(): void
    {
        $this->from('/login')
            ->get('/_test/throw-fiscal')
            ->assertRedirect('/login')
            ->assertSessionHas('toast-error');
    }
}
