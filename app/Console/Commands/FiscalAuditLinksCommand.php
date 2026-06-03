<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Fiscal\Contracts\FiscalYearBoot;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Audit the Légifrance and BOFiP URLs referenced by each fiscal rule's
 * `legalBasis()` array.
 *
 * Two invariants are checked:
 *   1. HTTP reachability · the URL must not return 404. Légifrance blocks
 *      non-browser requests through a WAF, so a Chrome user-agent is used;
 *      403 responses on legifrance.gouv.fr are tolerated as known WAF
 *      false-positives.
 *   2. Freshness · `consulted_at` must be at most `--max-age` days old;
 *      anything older is flagged for manual re-consultation.
 *
 * Modes:
 *   - `--strict` exits non-zero on any anomaly (suitable for CI).
 *   - `--no-http` skips the HTTP check and only validates freshness
 *     (offline / quick audit).
 *
 * The command is synchronous and run on demand: Floty has no queue worker,
 * and although a cron scheduler is available (validated 2026-06-03), this
 * audit tool is intentionally not scheduled.
 */
final class FiscalAuditLinksCommand extends Command
{
    protected $signature = 'fiscal:audit-links
                            {--strict : Exit code != 0 si anomalie détectée}
                            {--no-http : Skip le check HTTP (offline)}
                            {--max-age=365 : Âge maximum acceptable de consulted_at (jours)}';

    protected $description = 'Audit URLs Légifrance/BOFiP citées par les règles fiscales (atteignabilité + fraîcheur)';

    /**
     * Collect every `legalBasis` entry across all years, then run the
     * freshness and reachability checks.
     */
    public function handle(): int
    {
        $boots = $this->fiscalYearBoots();
        $checkHttp = ! $this->option('no-http');
        $maxAgeDays = (int) $this->option('max-age');
        $today = CarbonImmutable::now();

        $entries = [];
        foreach ($boots as $boot) {
            foreach ($this->ruleClasses($boot) as $class) {
                /** @var object $rule */
                $rule = app($class);
                if (! method_exists($rule, 'legalBasis')) {
                    continue;
                }
                foreach ($rule->legalBasis() as $i => $basis) {
                    $entries[] = [
                        'year' => $boot->year(),
                        'rule_code' => method_exists($rule, 'ruleCode') ? $rule->ruleCode() : $class,
                        'index' => $i,
                        'type' => $basis['type'] ?? '?',
                        'article' => $basis['article'] ?? $basis['reference'] ?? '?',
                        'url' => $basis['url'] ?? null,
                        'consulted_at' => $basis['consulted_at'] ?? null,
                    ];
                }
            }
        }

        $this->info(sprintf(
            'Audit · %d entrées legalBasis recensées sur %d années fiscales.',
            count($entries),
            count($boots),
        ));

        $anomalies = [];
        foreach ($entries as $entry) {
            $issues = [];

            if ($entry['consulted_at'] === null) {
                $issues[] = 'consulted_at manquant';
            } else {
                try {
                    $consulted = CarbonImmutable::parse($entry['consulted_at']);
                    $age = (int) $consulted->diffInDays($today);
                    if ($age > $maxAgeDays) {
                        $issues[] = sprintf('consulted_at trop ancien (%d j)', $age);
                    }
                } catch (\Exception) {
                    $issues[] = 'consulted_at non-parsable';
                }
            }

            if ($checkHttp) {
                if ($entry['url'] === null || $entry['url'] === '') {
                    $issues[] = 'URL manquante';
                } else {
                    $status = $this->headStatus((string) $entry['url']);
                    if ($status === 404) {
                        $issues[] = '404 Not Found';
                    } elseif ($status >= 500) {
                        $issues[] = sprintf('serveur KO (%d)', $status);
                    } elseif ($status === 403 && str_contains((string) $entry['url'], 'legifrance.gouv.fr')) {
                        // Légifrance WAF false positive · ignored.
                    } elseif ($status >= 400) {
                        $issues[] = sprintf('HTTP %d', $status);
                    }
                }
            }

            if ($issues !== []) {
                $anomalies[] = [
                    'année' => (string) $entry['year'],
                    'règle' => $entry['rule_code'],
                    'article' => sprintf('%s %s', $entry['type'], $entry['article']),
                    'consulté' => $entry['consulted_at'] ?? '-',
                    'anomalie' => implode(' · ', $issues),
                ];
            }
        }

        if ($anomalies === []) {
            $this->info(sprintf(
                '✓ Aucune anomalie détectée (%d entrées contrôlées, max-age %d j).',
                count($entries),
                $maxAgeDays,
            ));

            return self::SUCCESS;
        }

        $this->warn(sprintf('Anomalies détectées · %d entrée(s) à inspecter.', count($anomalies)));
        $this->table(['Année', 'Règle', 'Article', 'Consulté', 'Anomalie'], $anomalies);

        return $this->option('strict') ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Instantiate every fiscal-year boot class declared in the config.
     *
     * @return list<FiscalYearBoot>
     */
    private function fiscalYearBoots(): array
    {
        /** @var list<class-string<FiscalYearBoot>> $bootClasses */
        $bootClasses = config('floty.fiscal.year_boots', []);

        return array_map(static fn (string $class): FiscalYearBoot => new $class, $bootClasses);
    }

    /**
     * Return the pipeline and informative rule classes of a given year.
     *
     * @return list<class-string>
     */
    private function ruleClasses(FiscalYearBoot $boot): array
    {
        return array_merge($boot->rules(), $boot->informativeRules());
    }

    /**
     * HEAD the URL with a browser user-agent (bypasses Légifrance's WAF
     * on default clients). Returns 0 on timeout or DNS error.
     */
    private function headStatus(string $url): int
    {
        try {
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'fr-FR,fr;q=0.9,en;q=0.8',
            ])
                ->timeout(10)
                ->withOptions(['allow_redirects' => true])
                ->head($url);

            return $response->status();
        } catch (\Throwable) {
            return 0;
        }
    }
}
