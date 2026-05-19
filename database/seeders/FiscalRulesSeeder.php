<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Fiscal\Contracts\FiscalRule as FiscalRuleContract;
use App\Fiscal\Contracts\FiscalYearBoot;
use App\Fiscal\Registry\FiscalRuleRegistry;
use App\Models\FiscalRule;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Mirrors the fiscal_rules index from the PHP rule classes registered in floty.fiscal.year_boots.
 * Rows for a year not produced by the current registry are deleted (idempotent mirror).
 */
final class FiscalRulesSeeder extends Seeder
{
    public function __construct(
        private readonly Container $resolver,
    ) {}

    public function run(FiscalRuleRegistry $registry): void
    {
        $bootClasses = (array) config('floty.fiscal.year_boots', []);
        $bootsByYear = [];
        foreach ($bootClasses as $bootClass) {
            if (! is_string($bootClass) || ! is_subclass_of($bootClass, FiscalYearBoot::class)) {
                continue;
            }
            $boot = $this->resolver->make($bootClass);
            $bootsByYear[$boot->year()] = $boot;
        }

        foreach ($registry->registeredYears() as $year) {
            $boot = $bootsByYear[$year] ?? null;

            DB::transaction(function () use ($registry, $boot, $year): void {
                $syncedCodes = [];

                foreach ($registry->rulesForYear($year) as $rule) {
                    $row = $this->rowFromPhpClass($rule, $year);
                    FiscalRule::updateOrCreate(
                        ['rule_code' => $row['rule_code'], 'fiscal_year' => $row['fiscal_year']],
                        $row,
                    );
                    $syncedCodes[] = $row['rule_code'];
                }

                if ($boot !== null) {
                    foreach ($boot->informativeRules() as $informativeClass) {
                        $informative = $this->resolver->make($informativeClass);
                        $row = $this->rowFromPhpClass($informative, $year);
                        FiscalRule::updateOrCreate(
                            ['rule_code' => $row['rule_code'], 'fiscal_year' => $row['fiscal_year']],
                            $row,
                        );
                        $syncedCodes[] = $row['rule_code'];
                    }
                }

                FiscalRule::query()
                    ->where('fiscal_year', $year)
                    ->whereNotIn('rule_code', $syncedCodes)
                    ->delete();
            });
        }
    }

    /**
     * Build the index row from a rule instance. A rule may override codeReference()
     * when its effective implementation lives outside PHP.
     *
     * @return array<string, mixed>
     */
    private function rowFromPhpClass(FiscalRuleContract $rule, int $year): array
    {
        return [
            'rule_code' => $rule->ruleCode(),
            'fiscal_year' => $year,
            'code_reference' => $this->codeReferenceFor($rule),
        ];
    }

    private function codeReferenceFor(FiscalRuleContract $rule): string
    {
        if (method_exists($rule, 'codeReference')) {
            return $rule->codeReference();
        }

        $relativePath = str_replace('\\', '/', $rule::class).'.php';

        return str_starts_with($relativePath, 'App/')
            ? 'app/'.substr($relativePath, 4)
            : $relativePath;
    }
}
