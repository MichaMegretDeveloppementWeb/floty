<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Carbon\CarbonImmutable;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Console\Command;

/**
 * Benchmark median TTFB over real HTTP requests against the critical
 * Inertia pages of the application.
 *
 * Unlike an in-process `Kernel::handle()` simulation, this command issues
 * real GET requests against the configured base URL (Herd by default) so
 * the measured timings reflect the end-user experience.
 *
 * Methodology:
 *   - Real HTTP login (`POST /login`) with a test user; session cookies
 *     are kept in a Guzzle cookie jar across requests.
 *   - Each scenario issues a `X-Inertia: true` GET so the server returns
 *     the Inertia JSON payload (closer to intra-app navigation than to a
 *     cold load).
 *   - One warm-up run per scenario is discarded; the median of the
 *     remaining N runs is reported.
 *
 * Output:
 *   - stdout — Markdown table.
 *   - JSON   — saved under `project-management/perf-bench/snapshots/`
 *              unless `--no-save` is passed.
 *
 * Prerequisites:
 *   - The base URL must be reachable (Herd usually serves
 *     https://floty.test).
 *   - The test user must exist in the database with a known cleartext password.
 *
 * Usage:
 *   php artisan perf:bench
 *   php artisan perf:bench --runs=5 --label="post-S1.1"
 */
final class PerfBenchmarkCommand extends Command
{
    protected $signature = 'perf:bench
                            {--runs=3 : Nombre de runs comptés par scénario (médiane)}
                            {--base-url= : URL de base (défaut · APP_URL)}
                            {--email=admin@floty.test : Email du user de test}
                            {--password=password : Password du user de test}
                            {--label= : Libellé du snapshot}
                            {--no-save : Ne pas sauvegarder le JSON (sortie stdout uniquement)}';

    protected $description = 'Benchmark TTFB HTTP réel des pages Inertia critiques';

    /**
     * Pages benchmarked by the command. Each scenario points to a stable
     * URL that can be served against the seeded dataset.
     *
     * @var array<string, array{path: string, label: string}>
     */
    private const SCENARIOS = [
        'dashboard' => ['path' => '/app/dashboard', 'label' => 'Dashboard'],
        'contracts_index' => ['path' => '/app/contracts', 'label' => 'Contracts Index (défaut)'],
        'contracts_index_sorted' => ['path' => '/app/contracts?sort=start_date&direction=asc', 'label' => 'Contracts Index avec tri'],
        'planning_2025' => ['path' => '/app/planning?year=2025', 'label' => 'Planning Heatmap 2025'],
        'companies_index' => ['path' => '/app/companies', 'label' => 'Companies Index'],
        'companies_index_sorted' => ['path' => '/app/companies?sort=legal_name&direction=desc', 'label' => 'Companies Index avec tri'],
        'vehicles_index' => ['path' => '/app/vehicles', 'label' => 'Vehicles Index'],
        'vehicles_index_sorted' => ['path' => '/app/vehicles?sort=license_plate&direction=desc', 'label' => 'Vehicles Index avec tri'],
        'company_show_overview' => ['path' => '/app/companies/1', 'label' => 'Company Show overview'],
        'company_show_fiscal' => ['path' => '/app/companies/1?tab=fiscal', 'label' => 'Company Show onglet Fiscalité'],
        'vehicle_show' => ['path' => '/app/vehicles/1', 'label' => 'Vehicle Show'],
    ];

    /**
     * Authenticate the HTTP client, warm each scenario then collect the
     * median TTFB across the requested number of runs.
     */
    public function handle(): int
    {
        $runs = max(1, (int) $this->option('runs'));
        $baseUrl = rtrim((string) ($this->option('base-url') ?? config('app.url')), '/');
        $email = (string) $this->option('email');
        $password = (string) $this->option('password');
        $label = $this->option('label') ?? 'snapshot';

        $this->info("Benchmark perf HTTP réel · {$runs} runs/scenario · base={$baseUrl} · user={$email}");

        $cookieJar = new CookieJar;
        $client = new Client([
            'base_uri' => $baseUrl,
            'cookies' => $cookieJar,
            'verify' => false,
            'allow_redirects' => true,
            'http_errors' => false,
            'timeout' => 60,
            'headers' => [
                'User-Agent' => 'Floty-PerfBench/1.0',
            ],
        ]);

        $this->info('Login HTTP en cours...');
        if (! $this->loginHttp($client, $cookieJar)) {
            $this->error('Login échoué · vérifier credentials et que Herd tourne sur '.$baseUrl);

            return self::FAILURE;
        }
        $this->info('✓ Logged in · session cookie obtenu');
        $this->info('Warmup en cours (1 run jeté par scénario)...');

        $results = [];
        foreach (self::SCENARIOS as $key => $config) {
            $client->get($config['path'], [
                'headers' => ['Accept' => 'text/html, application/xhtml+xml'],
            ]);

            $durationsMs = [];
            $statuses = [];

            for ($i = 0; $i < $runs; $i++) {
                $startNs = hrtime(true);
                $response = $client->get($config['path'], [
                    'headers' => ['Accept' => 'text/html, application/xhtml+xml'],
                ]);
                $durationMs = (hrtime(true) - $startNs) / 1e6;

                $durationsMs[] = $durationMs;
                $statuses[] = $response->getStatusCode();
            }

            sort($durationsMs);
            $median = static fn (array $a): float => $a[(int) floor(count($a) / 2)];

            $statusUnique = array_unique($statuses);
            $statusReport = count($statusUnique) === 1 ? (string) $statusUnique[0] : implode('/', $statusUnique);

            $results[$key] = [
                'label' => $config['label'],
                'path' => $config['path'],
                'ttfb_ms_median' => round($median($durationsMs), 1),
                'http_status' => $statusReport,
                'runs_ms' => array_map(static fn (float $v): float => round($v, 1), $durationsMs),
            ];

            $statusIcon = $statusReport === '200' ? '✓' : '✗';
            $this->line(sprintf(
                '%s %s · %.1f ms · HTTP %s',
                $statusIcon,
                $key,
                $results[$key]['ttfb_ms_median'],
                $statusReport,
            ));
        }

        $this->info('');
        $this->info('=== RÉSULTATS ===');
        $this->renderMarkdownTable($results, $label);

        if (! $this->option('no-save')) {
            $path = $this->saveSnapshot($results, $label, $email, $runs, $baseUrl);
            $this->info("Snapshot JSON sauvegardé · {$path}");
        }

        return self::SUCCESS;
    }

    /**
     * Authenticate against the Floty login route using the cookie-based
     * CSRF mechanism. Floty is an Inertia SPA, so the CSRF token comes
     * from the `XSRF-TOKEN` cookie and must be sent back as the
     * `X-XSRF-TOKEN` header on the POST. Returns true on success.
     */
    private function loginHttp(Client $client, CookieJar $cookieJar): bool
    {
        $client->get('/login');

        $xsrfRaw = null;
        foreach ($cookieJar->toArray() as $cookie) {
            if ($cookie['Name'] === 'XSRF-TOKEN') {
                $xsrfRaw = (string) $cookie['Value'];

                break;
            }
        }

        if ($xsrfRaw === null) {
            $this->error('Cookie XSRF-TOKEN non trouvé après GET /login');

            return false;
        }

        $xsrfToken = urldecode($xsrfRaw);

        $response = $client->post('/login', [
            'form_params' => [
                'email' => $this->option('email'),
                'password' => $this->option('password'),
            ],
            'headers' => [
                'X-XSRF-TOKEN' => $xsrfToken,
                'Accept' => 'application/json',
            ],
        ]);

        $status = $response->getStatusCode();
        if (! in_array($status, [200, 204, 302], true)) {
            $this->error("POST /login a retourné HTTP {$status} (attendu 200/204/302)");
            $this->line('Response body : '.substr((string) $response->getBody(), 0, 200));

            return false;
        }

        $check = $client->get('/app/dashboard', ['allow_redirects' => false]);
        if ($check->getStatusCode() !== 200) {
            $this->error("Vérification session échouée · GET /app/dashboard a retourné HTTP {$check->getStatusCode()} (attendu 200)");

            return false;
        }

        return true;
    }

    /**
     * Render the results as a Markdown table for stdout.
     *
     * @param  array<string, array{label: string, path: string, ttfb_ms_median: float, http_status: string, runs_ms: list<float>}>  $results
     */
    private function renderMarkdownTable(array $results, string $label): void
    {
        $this->line('');
        $this->line("### Snapshot · {$label} · ".CarbonImmutable::now()->toDateTimeString());
        $this->line('');
        $this->line('| Scénario | TTFB médian | HTTP |');
        $this->line('|---|---|---|');
        foreach ($results as $key => $r) {
            $this->line(sprintf(
                '| `%s` | **%.1f ms** | %s |',
                $key,
                $r['ttfb_ms_median'],
                $r['http_status'],
            ));
        }
        $this->line('');
    }

    /**
     * Save the full result set as a timestamped JSON snapshot.
     *
     * @param  array<string, array{label: string, path: string, ttfb_ms_median: float, http_status: string, runs_ms: list<float>}>  $results
     */
    private function saveSnapshot(array $results, string $label, string $email, int $runs, string $baseUrl): string
    {
        $dir = base_path('project-management/perf-bench/snapshots');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $timestamp = CarbonImmutable::now()->format('Y-m-d_His');
        $safeLabel = preg_replace('/[^a-z0-9._-]+/i', '-', $label) ?? 'snapshot';
        $path = "{$dir}/{$timestamp}_{$safeLabel}.json";

        file_put_contents($path, json_encode([
            'meta' => [
                'timestamp' => CarbonImmutable::now()->toIso8601String(),
                'label' => $label,
                'base_url' => $baseUrl,
                'user_email' => $email,
                'runs' => $runs,
                'php_version' => PHP_VERSION,
                'app_env' => app()->environment(),
            ],
            'results' => $results,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return str_replace(base_path().DIRECTORY_SEPARATOR, '', $path);
    }
}
