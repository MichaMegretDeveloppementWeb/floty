<?php

declare(strict_types=1);

namespace App\Console\Commands\Dev;

use Illuminate\Console\Command;
use Throwable;

/**
 * On-demand query report (dev tool, NOT scheduled). Reads the Laravel Debugbar
 * captures on disk (each = one REAL request, cold process, exactly what the
 * debugbar shows) and reports, per page, the number of SQL queries and the
 * exact duplicates. Worst case per route is kept (the heaviest capture).
 *
 * Workflow: browse the pages to check (the debugbar captures each request),
 * then run this. It "sleeps" until called again.
 *
 *   php artisan diag:query-report                 # /app pages, worst case per route
 *   php artisan diag:query-report --details       # + top repeated query shapes
 *   php artisan diag:query-report --all           # include non-/app requests
 *   php artisan diag:query-report --limit=500     # scan more captures
 */
final class QueryReportCommand extends Command
{
    protected $signature = 'diag:query-report
        {--details : Show the top repeated query shapes per page}
        {--all : Include requests outside /app}
        {--limit=400 : Number of most-recent captures to scan}';

    protected $description = 'Rapport du nombre de requêtes SQL et des doublons par page, depuis les captures Debugbar (outil ponctuel, dev only).';

    public function handle(): int
    {
        $dir = storage_path('debugbar');
        if (! is_dir($dir)) {
            $this->error("Aucune capture Debugbar ({$dir}). Active la debugbar et navigue sur les pages d'abord.");

            return self::FAILURE;
        }

        $files = glob($dir.'/*.json') ?: [];
        usort($files, static fn (string $a, string $b): int => filemtime($b) <=> filemtime($a));
        $files = array_slice($files, 0, (int) $this->option('limit'));

        if ($files === []) {
            $this->error('Aucune capture trouvée. Navigue sur les pages avec la debugbar active, puis relance.');

            return self::FAILURE;
        }

        $byRoute = [];
        foreach ($files as $file) {
            $row = $this->parseCapture($file);
            if ($row === null) {
                continue;
            }
            if (! (bool) $this->option('all') && ! str_starts_with($row['path'], '/app')) {
                continue;
            }

            $key = $row['method'].' '.$row['path'].$row['query'];
            // Keep the worst case (max queries) per route; count captures.
            if (! isset($byRoute[$key]) || $row['nb'] > $byRoute[$key]['nb']) {
                $captures = ($byRoute[$key]['captures'] ?? 0) + 1;
                $byRoute[$key] = $row + ['captures' => $captures];
            } else {
                $byRoute[$key]['captures']++;
            }
        }

        if ($byRoute === []) {
            $this->warn('Captures trouvées mais aucune ne correspond au filtre. Essaie --all.');

            return self::SUCCESS;
        }

        uasort($byRoute, static fn (array $a, array $b): int => $b['nb'] <=> $a['nb']);

        $this->table(
            ['Méthode', 'Page', 'Requêtes (max)', 'Doublons', 'Captures'],
            array_map(static fn (array $r): array => [
                $r['method'],
                mb_substr($r['path'].$r['query'], 0, 52),
                $r['nb'],
                $r['exactDup'],
                $r['captures'],
            ], $byRoute),
        );

        $worst = array_values($byRoute)[0];
        $this->line('');
        $this->info(count($byRoute)." routes analysées. Pire page : {$worst['path']}{$worst['query']} ({$worst['nb']} req, {$worst['exactDup']} doublons).");

        if ($this->option('details')) {
            foreach ($byRoute as $r) {
                if ($r['topShapes'] === []) {
                    continue;
                }
                $this->line('');
                $this->line("<comment># {$r['method']} {$r['path']}{$r['query']}  (nb={$r['nb']}, dup={$r['exactDup']})</comment>");
                foreach ($r['topShapes'] as $sql => $count) {
                    if ($count < 2) {
                        continue;
                    }
                    $this->line(sprintf('  x%-3d %s', $count, mb_substr((string) preg_replace('/\s+/', ' ', $sql), 0, 140)));
                }
            }
        }

        return self::SUCCESS;
    }

    /**
     * @return array{method: string, path: string, query: string, nb: int, exactDup: int, topShapes: array<string, int>}|null
     */
    private function parseCapture(string $file): ?array
    {
        try {
            $json = json_decode((string) file_get_contents($file), true);
        } catch (Throwable) {
            return null;
        }
        if (! is_array($json)) {
            return null;
        }

        $uri = (string) ($json['__meta']['uri'] ?? '');
        $method = (string) ($json['__meta']['method'] ?? '?');
        $queries = $json['queries'] ?? null;
        if ($uri === '' || ! is_array($queries)) {
            return null;
        }

        $parts = parse_url($uri);
        $path = $parts['path'] ?? $uri;
        // Normalise numeric ids so the same logical page groups together.
        $path = (string) preg_replace('#/\d+#', '/{id}', $path);
        $query = isset($parts['query']) && $parts['query'] !== '' ? '?'.$parts['query'] : '';

        $statements = $queries['statements'] ?? [];
        $shapes = [];
        $exact = [];
        foreach ($statements as $s) {
            $sql = trim((string) ($s['sql'] ?? ''));
            if ($sql === '') {
                continue;
            }
            $shapes[$sql] = ($shapes[$sql] ?? 0) + 1;
            $bindings = json_encode($s['params'] ?? ($s['bindings'] ?? []));
            $exact[$sql.'|'.$bindings] = ($exact[$sql.'|'.$bindings] ?? 0) + 1;
        }

        $exactDup = 0;
        foreach ($exact as $count) {
            if ($count > 1) {
                $exactDup += $count - 1;
            }
        }

        arsort($shapes);

        return [
            'method' => $method,
            'path' => $path,
            'query' => $query,
            'nb' => (int) ($queries['nb_statements'] ?? count($statements)),
            'exactDup' => $exactDup,
            'topShapes' => array_slice($shapes, 0, 6, true),
        ];
    }
}
