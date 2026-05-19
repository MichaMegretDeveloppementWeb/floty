<?php

declare(strict_types=1);

namespace Tests\Feature\Logging;

use App\Actions\FiscalDeclaration\CreateDraftDeclarationAction;
use App\Models\Company;
use App\Models\FiscalDeclaration;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Anti-régression sur le routing des Actions FiscalDeclaration vers le canal
 * de log thématique `declarations` (pattern file-based · vérification que les
 * events atterrissent dans storage/logs/declarations-*.log).
 */
final class FiscalDeclarationActionsRouteToDeclarationsChannelTest extends TestCase
{
    use RefreshDatabase;

    private string $declarationsLogFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->declarationsLogFile = storage_path('logs/declarations-'.Carbon::now()->format('Y-m-d').'.log');
        @unlink($this->declarationsLogFile);
    }

    #[Test]
    public function un_event_create_draft_refused_atterrit_dans_le_canal_declarations(): void
    {
        $company = Company::factory()->create();
        FiscalDeclaration::factory()->forCompany($company)->forYear(2025)->draft()->create();

        $action = $this->app->make(CreateDraftDeclarationAction::class);

        try {
            $action->execute($company->id, 2025);
            self::fail('La 2e création aurait dû lever DomainException.');
        } catch (DomainException) {
            // Refus attendu · on continue pour vérifier le log.
        }

        self::assertFileExists(
            $this->declarationsLogFile,
            'Le fichier `declarations-YYYY-MM-DD.log` doit être créé après un refus de draft.',
        );

        $content = (string) file_get_contents($this->declarationsLogFile);

        // Le préfixe `notice` du level + le nom d'event canonique doivent apparaître.
        self::assertStringContainsString('NOTICE', $content);
        self::assertStringContainsString('FiscalDeclaration.draft_create_refused', $content);
        self::assertStringContainsString('"company_id":'.$company->id, $content);
        self::assertStringContainsString('"fiscal_year":2025', $content);
        self::assertStringContainsString('"reason":"active_declaration_already_exists"', $content);
    }
}
