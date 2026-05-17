<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Profiling **interne** des hot paths Floty (Phase 1 audit perf
 * 2026-05-16).
 *
 * Complément à `perf:bench` (TTFB HTTP réel) · cette commande dispatch
 * les controllers **in-process** via `Kernel::handle()` et instrumente
 * le breakdown ·
 *   - Temps total wall-clock
 *   - Temps SQL cumulé (via `DB::listen`)
 *   - Temps PHP (= total - SQL)
 *   - Mémoire peak
 *   - Top 5 queries SQL les plus lentes
 *   - Signatures SQL groupées (détection N+1 patterns)
 *
 * Objectif · identifier la **vraie cause** des latences (SQL ? PHP
 * pipeline fiscal ? sérialisation ?) avant de décider du chantier
 * S3.x. Sans ce diagnostic on continue à optimiser à l'aveugle.
 *
 * Usage ·
 *   php artisan perf:profile dashboard
 *   php artisan perf:profile contracts_index
 *   php artisan perf:profile planning_2025
 *   php artisan perf:profile --all  (tous les scenarios)
 */
final class PerfProfileCommand extends Command
{
    protected $signature = 'perf:profile
                            {scenario? : dashboard|contracts_index|contracts_index_sorted|planning_2025|companies_index|vehicles_index|company_show_overview|company_show_fiscal|vehicle_show}
                            {--all : Profile tous les scenarios}
                            {--email=admin@floty.test : Email user pour auth}
                            {--top-queries=10 : Nb de top queries lentes à afficher}
                            {--top-signatures=10 : Nb de top signatures (N+1) à afficher}';

    protected $description = 'Profile in-process des hot paths · breakdown SQL/PHP + top queries (audit perf)';

    /**
     * Scenarios alignés sur `PerfBenchmarkCommand` pour la comparaison
     * profil ↔ TTFB HTTP.
     *
     * @var array<string, string>
     */
    private const SCENARIOS = [
        'dashboard' => '/app/dashboard',
        'contracts_index' => '/app/contracts',
        'contracts_index_sorted' => '/app/contracts?sort=start_date&direction=asc',
        'planning_2025' => '/app/planning?year=2025',
        'companies_index' => '/app/companies',
        'vehicles_index' => '/app/vehicles',
        'company_show_overview' => '/app/companies/1',
        'company_show_fiscal' => '/app/companies/1?tab=fiscal',
        'vehicle_show' => '/app/vehicles/1',
    ];

    public function handle(): int
    {
        $user = User::where('email', $this->option('email'))->first();
        if ($user === null) {
            $this->error("User introuvable · {$this->option('email')}");

            return self::FAILURE;
        }

        $scenarios = $this->option('all') ? array_keys(self::SCENARIOS) : [$this->argument('scenario')];

        if ($scenarios[0] === null) {
            $this->error('Scenario requis · php artisan perf:profile dashboard');
            $this->info('Disponibles · '.implode(', ', array_keys(self::SCENARIOS)));

            return self::FAILURE;
        }

        foreach ($scenarios as $scenario) {
            if (! isset(self::SCENARIOS[$scenario])) {
                $this->error("Scenario inconnu · {$scenario}");

                continue;
            }
            $this->profileScenario($scenario, self::SCENARIOS[$scenario], $user);
        }

        return self::SUCCESS;
    }

    private function profileScenario(string $scenario, string $path, User $user): void
    {
        // Réinitialise l'état entre 2 scenarios (cache services per-request).
        // Re-bind un container neuf serait plus propre mais coûte cher · on
        // accepte que les caches singleton (S1.1 + S2.3) restent chauds pour
        // approximer "navigation intra-app" plutôt que "cold load".
        Auth::login($user);

        $queries = [];
        DB::flushQueryLog();
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = [
                'sql' => $query->sql,
                'time_ms' => (float) $query->time,
                'bindings_count' => count($query->bindings),
            ];
        });

        // Warmup · dispatch une fois pour amortir cold-start OPcache.
        $this->dispatchOnce($path, $user);
        $queries = [];

        // Mesure réelle. On capture aussi la taille du body via
        // `getContent()` pour forcer toute sérialisation lazy
        // (Inertia DTO Spatie Data → JSON → HTML wrapper).
        $memBefore = memory_get_usage(true);
        $start = microtime(true);
        $response = $this->dispatchOnce($path, $user);
        $kernelMs = (microtime(true) - $start) * 1000;
        $startSerialize = microtime(true);
        $body = $response->getContent();
        $serializeMs = (microtime(true) - $startSerialize) * 1000;
        $totalMs = $kernelMs + $serializeMs;
        $bodyKb = $body === false ? 0 : strlen($body) / 1024;
        $memPeak = memory_get_peak_usage(true);
        $memDelta = $memPeak - $memBefore;

        $this->renderReport(
            $scenario,
            $path,
            $response->getStatusCode(),
            $totalMs,
            $kernelMs,
            $serializeMs,
            $bodyKb,
            $memDelta,
            $queries,
        );
    }

    private function dispatchOnce(string $path, User $user): Response
    {
        // Construit une Request HTTP-like avec Host correct (Herd) + auth user
        // injecté · le Kernel applique tous les middlewares (Auth, CSRF
        // validate-only sur GET, Inertia version, etc.).
        $request = Request::create(
            $path,
            'GET',
            [],
            [],
            [],
            [
                'HTTP_HOST' => parse_url(config('app.url'), PHP_URL_HOST) ?? 'floty.test',
                'HTTPS' => 'on',
                'SERVER_PORT' => 443,
            ],
        );
        $request->setUserResolver(static fn () => $user);

        return $this->laravel->make(HttpKernel::class)->handle($request);
    }

    /**
     * @param  list<array{sql: string, time_ms: float, bindings_count: int}>  $queries
     */
    private function renderReport(
        string $scenario,
        string $path,
        int $status,
        float $totalMs,
        float $kernelMs,
        float $serializeMs,
        float $bodyKb,
        int $memDelta,
        array $queries,
    ): void {
        $sqlTime = array_sum(array_column($queries, 'time_ms'));
        $phpTime = max(0.0, $totalMs - $sqlTime);
        $sqlPct = $totalMs > 0 ? ($sqlTime / $totalMs) * 100 : 0.0;
        $phpPct = $totalMs > 0 ? ($phpTime / $totalMs) * 100 : 0.0;
        $queriesCount = count($queries);

        $this->info('');
        $this->info("=== PROFILE · {$scenario} ({$path}) ===");
        $this->line("Status HTTP   · {$status}");
        $this->line(sprintf('Body size     · %.1f KB', $bodyKb));
        $this->line(sprintf('Total wall    · %.1f ms', $totalMs));
        $this->line(sprintf('  Kernel      · %.1f ms (handle + middleware + controller)', $kernelMs));
        $this->line(sprintf('  Serialize   · %.1f ms (response getContent · DTO → JSON → HTML)', $serializeMs));
        $this->line(sprintf('  ↳ SQL       · %.1f ms (%.0f%%) · %d queries', $sqlTime, $sqlPct, $queriesCount));
        $this->line(sprintf('  ↳ PHP       · %.1f ms (%.0f%%)', $phpTime, $phpPct));
        $this->line(sprintf('Memory delta  · %.1f MB (peak)', $memDelta / 1024 / 1024));

        if ($queriesCount === 0) {
            $this->info('');

            return;
        }

        // Top N queries les plus lentes (instances individuelles).
        $sortedBySpeed = $queries;
        usort($sortedBySpeed, static fn ($a, $b) => $b['time_ms'] <=> $a['time_ms']);
        $topSlow = array_slice($sortedBySpeed, 0, (int) $this->option('top-queries'));

        $this->info('');
        $this->line('--- Top '.count($topSlow).' queries les plus lentes ---');
        foreach ($topSlow as $i => $q) {
            $this->line(sprintf(
                '%2d. %6.1f ms · %s',
                $i + 1,
                $q['time_ms'],
                $this->truncate($q['sql'], 130),
            ));
        }

        // Signatures groupées · détecte N+1 (même requête répétée N fois).
        $signatures = [];
        foreach ($queries as $q) {
            $sig = $this->signatureOf($q['sql']);
            if (! isset($signatures[$sig])) {
                $signatures[$sig] = ['count' => 0, 'time_ms' => 0.0, 'sql' => $q['sql']];
            }
            $signatures[$sig]['count']++;
            $signatures[$sig]['time_ms'] += $q['time_ms'];
        }
        // Tri par temps cumulé desc.
        uasort($signatures, static fn ($a, $b) => $b['time_ms'] <=> $a['time_ms']);
        $topSig = array_slice($signatures, 0, (int) $this->option('top-signatures'), true);

        $this->info('');
        $this->line('--- Top '.count($topSig).' signatures SQL (temps cumulé · détection N+1) ---');
        $i = 1;
        foreach ($topSig as $sig => $info) {
            $marker = $info['count'] >= 5 ? ' ⚠️ N+1' : '';
            $this->line(sprintf(
                '%2d. %4d× · %6.1f ms total · %s%s',
                $i++,
                $info['count'],
                $info['time_ms'],
                $this->truncate($sig, 110),
                $marker,
            ));
        }
        $this->info('');
    }

    /**
     * Normalise une query SQL en signature · remplace les valeurs scalaires
     * par `?` et les listes IN (?, ?, ?) par `IN (...)`. Permet de regrouper
     * les requêtes identiques aux paramètres près (détecte le N+1).
     */
    private function signatureOf(string $sql): string
    {
        // Compresser les listes "in (?, ?, ?, ...)" en "in (...)".
        $sql = preg_replace('/\bin\s*\(\s*\?(\s*,\s*\?)*\s*\)/i', 'in (...)', $sql) ?? $sql;
        // Réduire les espaces multiples.
        $sql = preg_replace('/\s+/', ' ', $sql) ?? $sql;

        return trim($sql);
    }

    private function truncate(string $s, int $max): string
    {
        if (mb_strlen($s) <= $max) {
            return $s;
        }

        return mb_substr($s, 0, $max - 1).'…';
    }
}
