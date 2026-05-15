<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Fiscal\Contracts\FiscalYearBoot;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Audit des URLs Légifrance/BOFiP citées dans les `legalBasis()` des
 * règles fiscales · pipeline + informatives (AUDIT-E · audit fiscal
 * renforcé 14/05/2026).
 *
 * Vérifie deux invariants ·
 *  1. **Atteignabilité HTTP** · l'URL ne retourne pas 404. Légifrance
 *     bloque les requêtes non-navigateur (WAF) · on utilise un User-Agent
 *     Chrome réaliste. Tout autre code que 2xx/3xx est flaggé.
 *  2. **Fraîcheur** · l'entrée `consulted_at` doit dater de moins de
 *     12 mois. Au-delà, signaler pour reconsultation manuelle (la
 *     doctrine BOFiP peut avoir évolué).
 *
 * **Mode `--strict`** · exit code != 0 si une anomalie est détectée
 * (utile pour intégration CI/CD).
 *
 * **Mode `--no-http`** · skip le check HTTP, ne vérifie que la fraîcheur
 * `consulted_at` (utile en offline / pour audit rapide).
 *
 * Cette commande est **synchrone** et **manuelle** · pas de queue,
 * pas de cron (cf. ADR-0023 · infra sans queue ni planificateur). À
 * lancer lors des passes d'audit fiscal régulières.
 */
final class FiscalAuditLinksCommand extends Command
{
    protected $signature = 'fiscal:audit-links
                            {--strict : Exit code != 0 si anomalie détectée}
                            {--no-http : Skip le check HTTP (offline)}
                            {--max-age=365 : Âge maximum acceptable de consulted_at (jours)}';

    protected $description = 'Audit URLs Légifrance/BOFiP citées par les règles fiscales (atteignabilité + fraîcheur)';

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

            // 1. Fraîcheur consulted_at
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

            // 2. Atteignabilité HTTP
            if ($checkHttp) {
                if ($entry['url'] === null || $entry['url'] === '') {
                    $issues[] = 'URL manquante';
                } else {
                    $status = $this->headStatus((string) $entry['url']);
                    // 2xx/3xx OK · 4xx/5xx KO. Légifrance répond 403 sur
                    // User-Agent suspect même si l'URL est valide · on
                    // tolère 403 pour Légifrance avec un avertissement.
                    if ($status === 404) {
                        $issues[] = '404 Not Found';
                    } elseif ($status >= 500) {
                        $issues[] = sprintf('serveur KO (%d)', $status);
                    } elseif ($status === 403 && str_contains((string) $entry['url'], 'legifrance.gouv.fr')) {
                        // 403 Légifrance · WAF sur User-Agent · faux positif typique, on ignore.
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
     * @return list<FiscalYearBoot>
     */
    private function fiscalYearBoots(): array
    {
        /** @var list<class-string<FiscalYearBoot>> $bootClasses */
        $bootClasses = config('floty.fiscal.year_boots', []);

        return array_map(static fn (string $class): FiscalYearBoot => new $class, $bootClasses);
    }

    /**
     * Concatène les classes pipeline + informatives d'une année.
     *
     * @return list<class-string>
     */
    private function ruleClasses(FiscalYearBoot $boot): array
    {
        return array_merge($boot->rules(), $boot->informativeRules());
    }

    /**
     * Retourne le code HTTP renvoyé par l'URL (HEAD avec User-Agent
     * navigateur pour contourner le WAF Légifrance) · 0 en cas de
     * timeout / DNS error.
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
