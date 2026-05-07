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
 * flippée à l'écriture par les observers — plus de recalcul à la
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
