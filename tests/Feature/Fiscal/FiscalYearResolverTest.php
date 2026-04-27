<?php

declare(strict_types=1);

namespace Tests\Feature\Fiscal;

use App\Exceptions\Fiscal\FiscalCalculationException;
use App\Fiscal\Resolver\FiscalYearResolver;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Vérifie la résolution de l'année fiscale active :
 * - session présente et valide → renvoie session
 * - session absente → fallback availableYears[0]
 * - session avec année non supportée → fallback availableYears[0]
 * - setActiveYear avec année non supportée → exception
 */
final class FiscalYearResolverTest extends TestCase
{
    #[Test]
    public function fallback_sur_la_premiere_annee_disponible_quand_session_vide(): void
    {
        config(['floty.fiscal.available_years' => [2024]]);

        $resolver = $this->app->make(FiscalYearResolver::class);

        self::assertSame(2024, $resolver->resolve());
    }

    #[Test]
    public function lit_l_annee_active_de_la_session_quand_supportee(): void
    {
        config(['floty.fiscal.available_years' => [2024, 2025]]);
        session(['fiscal.active_year' => 2025]);

        $resolver = $this->app->make(FiscalYearResolver::class);

        self::assertSame(2025, $resolver->resolve());
    }

    #[Test]
    public function ignore_la_session_si_l_annee_n_est_pas_supportee(): void
    {
        config(['floty.fiscal.available_years' => [2024]]);
        session(['fiscal.active_year' => 2099]);

        $resolver = $this->app->make(FiscalYearResolver::class);

        self::assertSame(2024, $resolver->resolve());
    }

    #[Test]
    public function set_active_year_pose_la_session_quand_l_annee_est_supportee(): void
    {
        config(['floty.fiscal.available_years' => [2024, 2025]]);

        $resolver = $this->app->make(FiscalYearResolver::class);
        $resolver->setActiveYear(2025);

        self::assertSame(2025, session('fiscal.active_year'));
    }

    #[Test]
    public function set_active_year_leve_pour_annee_non_supportee(): void
    {
        config(['floty.fiscal.available_years' => [2024]]);

        $resolver = $this->app->make(FiscalYearResolver::class);

        $this->expectException(FiscalCalculationException::class);
        $resolver->setActiveYear(2099);
    }

    #[Test]
    public function leve_si_aucune_annee_n_est_configuree(): void
    {
        config(['floty.fiscal.available_years' => []]);

        $resolver = $this->app->make(FiscalYearResolver::class);

        $this->expectException(FiscalCalculationException::class);
        $resolver->resolve();
    }
}
