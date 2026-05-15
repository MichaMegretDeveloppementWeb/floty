<?php

declare(strict_types=1);

namespace Tests\Feature\User\Invoice;

use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests garde-fou « performance » de l'Index Invoices (T6 / Phase 14.R).
 *
 * Vérifie que l'Index ne paie **aucun** coût lié à la divergence (plus
 * de N+1 `BillingCalculator::calculate`). La détection de divergence
 * est désormais lue sur la colonne matérialisée `is_divergent`,
 * flippée à l'écriture par les observers · plus de recalcul à la
 * lecture.
 */
final class InvoiceIndexQueryCountTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function index_standard_n_appelle_jamais_billing_calculator(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        // 20 factures sur la page courante.
        for ($month = 1; $month <= 12; $month++) {
            Invoice::factory()
                ->for($company)
                ->for($user, 'generatedBy')
                ->forYearMonth(2024, $month)
                ->create();
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAs($user)->get('/app/invoices')->assertOk();

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // Aucune query ne doit toucher la table `contracts` ni
        // `vehicle_yearly_pricings` ni `invoice_lines` (signaux d'un
        // calcul de divergence à la lecture).
        $forbiddenTables = ['contracts', 'vehicle_yearly_pricings', 'invoice_lines'];
        foreach ($queries as $entry) {
            $sql = strtolower((string) $entry['query']);
            foreach ($forbiddenTables as $table) {
                self::assertStringNotContainsString(
                    "`{$table}`",
                    $sql,
                    "L'Index ne doit pas requêter la table `{$table}` (signal d'un recalcul de divergence à la lecture).",
                );
            }
        }
    }

    #[Test]
    public function index_avec_filtre_divergent_only_pagine_en_sql(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();

        // 6 factures dont 2 flaggées divergentes.
        for ($month = 1; $month <= 6; $month++) {
            Invoice::factory()
                ->for($company)
                ->for($user, 'generatedBy')
                ->forYearMonth(2024, $month)
                ->create([
                    'is_divergent' => $month <= 2,
                ]);
        }

        $response = $this->actingAs($user)
            ->get('/app/invoices?divergentOnly=1')
            ->assertOk();

        $response->assertInertia(
            fn ($page) => $page
                ->component('User/Invoices/Index/Index')
                ->where('invoices.meta.total', 2)
        );
    }

    /**
     * Lot 3 D09+D10 · garde-fou query budget chiffré complémentaire au
     * test sémantique `index_standard_n_appelle_jamais_billing_calculator`.
     *
     * Le test sémantique vérifie l'**absence** de tables interdites
     * (signal d'un N+1 fiscal/divergence à la lecture). Ce test ajoute
     * un cap **numérique** sur le total de queries · si demain quelqu'un
     * remet un eager-load coûteux ou un N+1 d'un autre type, le test
     * sautera. Couverture défensive croisée vs régression silencieuse.
     */
    #[Test]
    public function index_respecte_un_budget_query_borne_par_constante(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        // 12 factures sur la page courante (1 année complète).
        for ($month = 1; $month <= 12; $month++) {
            Invoice::factory()
                ->for($company)
                ->for($user, 'generatedBy')
                ->forYearMonth(2024, $month)
                ->create();
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAs($user)->get('/app/invoices')->assertOk();

        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Cap Lot 3 D09+D10 · baseline mesurée 6 queries pour 12 factures
        // (auth + count paginé + load Invoices + eager-load company +
        // eager-load supersededBy + queries collatérales Inertia).
        // Indépendant du nombre N de factures grâce à l'eager-load déjà
        // en place dans `paginateForIndex`. Cap à 12 · marge +6 pour
        // évolutions Inertia/middleware sans masquer une régression N+1
        // (qui ferait sauter ≥12 queries pour 12 factures).
        self::assertLessThan(
            12,
            $queryCount,
            "Trop de queries SQL ({$queryCount}) sur l'Index Invoices avec 12 factures - possible régression eager-load.",
        );
    }

    #[Test]
    public function show_renvoie_le_dto_avec_la_divergence(): void
    {
        // Sanity check : la fiche Show conserve `InvoiceDivergenceChecker`
        // pour fournir snapshot vs courant au banner. C'est volontaire.
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $invoice = Invoice::factory()
            ->for($company)
            ->for($user, 'generatedBy')
            ->forYearMonth(2024, 3)
            ->create();

        $this->actingAs($user)
            ->get("/app/invoices/{$invoice->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('invoice.divergence'));
    }
}
