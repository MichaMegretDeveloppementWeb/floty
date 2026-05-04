<?php

declare(strict_types=1);

namespace Tests\Unit\Providers;

use App\Fiscal\Contracts\FiscalRule;
use App\Fiscal\Contracts\FiscalYearBoot;
use App\Fiscal\Registry\FiscalRuleRegistry;
use App\Fiscal\Year2024\Year2024Boot;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Garantit que `FiscalServiceProvider` peuple le registry **uniquement**
 * via `config('floty.fiscal.year_boots')` — sans aucune référence en dur
 * à une année particulière (chantier ζ).
 *
 * Une régression qui ramènerait du code spécifique à 2024 dans le
 * provider (ex. : appel `registerYear2024()` réintroduit) ferait échouer
 * le test « registre peuplé même sans Year2024Boot dans la config ».
 */
final class FiscalServiceProviderTest extends TestCase
{
    public function test_le_registry_est_peuple_pour_chaque_year_boot_configure(): void
    {
        // Configuration par défaut de l'app : un seul boot (2024).
        config()->set('floty.fiscal.year_boots', [Year2024Boot::class]);
        $this->refreshFiscalRegistry();

        /** @var FiscalRuleRegistry $registry */
        $registry = $this->app->make(FiscalRuleRegistry::class);

        self::assertSame([2024], $registry->registeredYears());
        self::assertCount(16, $registry->rulesForYear(2024));

        foreach ($registry->rulesForYear(2024) as $rule) {
            self::assertInstanceOf(FiscalRule::class, $rule);
        }
    }

    public function test_le_registry_est_vide_quand_aucun_boot_n_est_configure(): void
    {
        // Cas limite : pas de boot enregistré → registry instanciable mais
        // sans année. Garantit qu'aucun « 2024 » n'est résiduel en dur.
        config()->set('floty.fiscal.year_boots', []);
        $this->refreshFiscalRegistry();

        /** @var FiscalRuleRegistry $registry */
        $registry = $this->app->make(FiscalRuleRegistry::class);

        self::assertSame([], $registry->registeredYears());
    }

    public function test_le_registry_supporte_plusieurs_year_boots(): void
    {
        // Stub nommé pour simuler un futur Year2025Boot — pas besoin
        // d'attendre la vraie classe pour valider la mécanique multi-
        // année. Le FQCN doit être résolvable par `class_exists` depuis
        // le provider (cf. validation dans FiscalServiceProvider).
        config()->set('floty.fiscal.year_boots', [
            Year2024Boot::class,
            FiscalServiceProviderTestStub2025Boot::class,
        ]);
        $this->refreshFiscalRegistry();

        /** @var FiscalRuleRegistry $registry */
        $registry = $this->app->make(FiscalRuleRegistry::class);

        self::assertSame([2024, 2025], $registry->registeredYears());
    }

    public function test_une_classe_invalide_dans_la_config_leve_une_exception_explicite(): void
    {
        config()->set('floty.fiscal.year_boots', ['App\\NotAClass']);
        $this->refreshFiscalRegistry();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('FiscalYearBoot invalide');

        $this->app->make(FiscalRuleRegistry::class);
    }

    /**
     * Force le re-binding du singleton après modification de la config.
     * Sans ça, `make()` retournerait l'instance déjà résolue avec
     * l'ancienne config (effet de bord du conteneur Laravel).
     */
    private function refreshFiscalRegistry(): void
    {
        $this->app->forgetInstance(FiscalRuleRegistry::class);
    }
}

/**
 * Stub nommé pour le test multi-année. Doit être au top-level du fichier
 * pour que `class_exists` le trouve via PSR-4 ou autoload Composer.
 *
 * @internal Ne pas réutiliser hors de ce test.
 */
final class FiscalServiceProviderTestStub2025Boot implements FiscalYearBoot
{
    public function year(): int
    {
        return 2025;
    }

    public function rules(): array
    {
        return [];
    }
}
