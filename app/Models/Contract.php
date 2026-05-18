<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Contract\ContractType;
use App\Fiscal\Year2024\Exemption\R2024_021_ShortTermRental;
use App\Observers\ContractObserver;
use App\Services\Contract\ContractQueryService;
use App\Support\Date\IsoWeeks;
use Carbon\CarbonImmutable;
use Database\Factories\ContractFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Contrat de location véhicule × entreprise sur une plage temporelle
 * inclusive `[start_date, end_date]`. Entité pivot du domaine fiscal
 * post-refonte (cf. ADR-0014 « Modèle Contract et règle LCD par
 * contrat individuel »).
 *
 * Cf. `taxes-rules/2024.md` v2.0 R-2024-021 pour la mécanique
 * d'exonération LCD et `database/migrations/2026_04_29_140000_create_contracts_table.php`
 * pour la structure DB.
 *
 * **Invariants critiques** (matérialisés en DB) :
 *   - `end_date >= start_date` (CHECK SQL)
 *   - Pas deux contrats actifs chevauchants sur le même véhicule
 *     (triggers MySQL `contracts_no_overlap_*`)
 *
 * @property int $id
 * @property int $vehicle_id
 * @property int $company_id
 * @property Carbon $start_date
 * @property Carbon $end_date
 * @property string|null $contract_reference
 * @property ContractType $contract_type
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable([
    'vehicle_id',
    'company_id',
    'start_date',
    'end_date',
    'contract_reference',
    'contract_type',
    'notes',
])]
#[ObservedBy([ContractObserver::class])]
final class Contract extends Model
{
    /** @use HasFactory<ContractFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'contract_type' => ContractType::class,
        ];
    }

    /**
     * @return BelongsTo<Vehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Conducteurs désignés sur ce contrat (0, 1 ou plusieurs). Pivot
     * pur égalitaire `contract_drivers` · pas de notion de conducteur
     * principal/secondaire, tous les conducteurs sont équivalents.
     *
     * Optionnel à la création (un contrat peut être créé sans conducteur
     * et complété ensuite).
     *
     * @return BelongsToMany<Driver, $this>
     */
    public function drivers(): BelongsToMany
    {
        return $this->belongsToMany(Driver::class, 'contract_drivers')
            ->withTimestamps();
    }

    /**
     * Dérive le `contract_type` à partir d'une plage `[start, end]`.
     *
     * Convention BOFiP § 180-190 (« éternelle ») :
     *   - durée ≤ 30 jours consécutifs → `Lcd`
     *   - **OU** plage couvrant exactement un mois civil entier
     *     (1er → dernier jour du même mois) → `Lcd`
     *   - sinon → `Lld`
     *
     * Méthode statique pure : pas d'IO, pas d'état. Réutilisable côté
     * Actions (Store/Update/BulkCreate) qui posent automatiquement le
     * type avant persistance.
     *
     * **Note architecture** : cette dérivation est distincte de la
     * qualification fiscale annuelle portée par
     * {@see R2024_021_ShortTermRental::isShortTermRental()}.
     * Le `contract_type` persisté est un **libellé indicatif** figé à
     * la création/édition ; la qualification fiscale réelle s'évalue
     * dans le pipeline avec la règle de l'année concernée.
     */
    public static function deriveTypeFromDates(string $startDate, string $endDate): ContractType
    {
        $start = CarbonImmutable::parse($startDate);
        $end = CarbonImmutable::parse($endDate);

        $duration = (int) $start->diffInDays($end) + 1;

        if ($duration <= 30) {
            return ContractType::Lcd;
        }

        $isFullCalendarMonth = $start->day === 1
            && $end->day === $end->daysInMonth
            && $start->month === $end->month
            && $start->year === $end->year;

        if ($isFullCalendarMonth) {
            return ContractType::Lcd;
        }

        return ContractType::Lld;
    }

    /**
     * Expansion du contrat en liste de dates ISO (Y-m-d), bornée à
     * l'année passée en argument. Inclut les deux bornes du contrat.
     *
     * Helper réutilisé par les règles fiscales (R-2024-002 numérateur
     * du prorata, R-2024-021 qualification LCD per-contract,
     * R-2024-008 jours indispos réductrices ∩ contrats taxables) et
     * par {@see ContractQueryService}.
     *
     * @return list<string>
     */
    public function expandToDaysInYear(int $year): array
    {
        $yearStart = CarbonImmutable::create($year, 1, 1);
        $yearEnd = CarbonImmutable::create($year, 12, 31);

        $start = $this->start_date->toImmutable();
        $end = $this->end_date->toImmutable();

        $rangeStart = $start->isAfter($yearStart) ? $start : $yearStart;
        $rangeEnd = $end->isBefore($yearEnd) ? $end : $yearEnd;

        if ($rangeStart->isAfter($rangeEnd)) {
            return [];
        }

        $days = [];
        $cursor = $rangeStart;
        while (! $cursor->isAfter($rangeEnd)) {
            $days[] = $cursor->toDateString();
            $cursor = $cursor->addDay();
        }

        return $days;
    }

    /**
     * Compte le nombre de jours du contrat tombant dans l'année donnée
     * (bornes incluses, mêmes règles de clipping que
     * {@see expandToDaysInYear}).
     *
     * Variante perf · ne matérialise pas l'array de 365 strings · juste
     * une diff arithmétique. Doit retourner exactement
     * `count($this->expandToDaysInYear($year))` (équivalence prouvée par
     * `ContractCountVsExpandEquivalenceTest`). Préférer cette méthode
     * aux 11 sites qui ne consommaient que la cardinalité.
     *
     * Cf. plan-remédiation Vague 1 Lot 3 D02 (F-12-006 et consolidés).
     */
    public function countDaysInYear(int $year): int
    {
        $yearStart = CarbonImmutable::create($year, 1, 1);
        $yearEnd = CarbonImmutable::create($year, 12, 31);

        $start = $this->start_date->toImmutable();
        $end = $this->end_date->toImmutable();

        $rangeStart = $start->isAfter($yearStart) ? $start : $yearStart;
        $rangeEnd = $end->isBefore($yearEnd) ? $end : $yearEnd;

        if ($rangeStart->isAfter($rangeEnd)) {
            return 0;
        }

        // `diffInDays` exclut la borne d'arrivée · +1 pour matcher la
        // sémantique « bornes incluses » de `expandToDaysInYear`.
        return (int) $rangeStart->diffInDays($rangeEnd) + 1;
    }

    /**
     * Variante YTD de {@see countDaysInYear} avec plafond date supplémentaire ·
     * compte les jours du contrat dans `[1er janvier $year, min(31 déc $year,
     * $upToDate)]`. Pour `$upToDate >= 31 déc $year` (cas année passée vue
     * depuis aujourd'hui), équivaut à `countDaysInYear`.
     *
     * Doit retourner exactement
     * `count(array_filter(expandToDaysInYear($year), fn($d) => $d <= $upToDate))`
     * (équivalence prouvée par
     * {@see Tests\Unit\Models\ContractCountVsExpandEquivalenceTest::count_up_to_egal_count_filtre_de_expand}).
     *
     * Cf. chantier perf Dashboard 2026-05-17 · remplace l'allocation des
     * 365 strings + filtre `<= upToDate` dans `DashboardStatsService::computePeriodMetrics`
     * (8 ans × N pairs × 365 cmp string · gros consommateur CPU history).
     */
    public function countDaysInYearUpTo(int $year, string $upToDate): int
    {
        $yearStart = CarbonImmutable::create($year, 1, 1);
        $yearEnd = CarbonImmutable::create($year, 12, 31);
        $upToDateImmutable = CarbonImmutable::createFromFormat('Y-m-d', $upToDate);
        $effectiveEnd = $upToDateImmutable->isBefore($yearEnd) ? $upToDateImmutable : $yearEnd;

        $start = $this->start_date->toImmutable();
        $end = $this->end_date->toImmutable();

        $rangeStart = $start->isAfter($yearStart) ? $start : $yearStart;
        $rangeEnd = $end->isBefore($effectiveEnd) ? $end : $effectiveEnd;

        if ($rangeStart->isAfter($rangeEnd)) {
            return 0;
        }

        return (int) $rangeStart->diffInDays($rangeEnd) + 1;
    }

    /**
     * Distribution des jours du contrat par semaine ISO de l'année donnée.
     * Clé · numéro de semaine ISO (1..53) · valeur · nombre de jours du
     * contrat dans cette semaine. Bornes incluses, contrat clippé à
     * `[1er janvier $year, 31 décembre $year]`.
     *
     * Variante perf · ne matérialise pas l'array de 365 strings, itère
     * semaine par semaine (au plus 53 itérations) en arithmétique pure.
     * Doit retourner exactement le résultat de
     * `expandToDaysInYear → groupBy(format('W'))` (équivalence prouvée
     * par {@see ContractDaysByWeekVsExpandEquivalenceTest}).
     *
     * **Sémantique W = `format('W')`** · cohérente avec
     * `loadWeekDensity` historique · une journée appartient à la
     * semaine ISO contenant son jeudi (norme ISO 8601). Le 30 décembre
     * 2024 (lundi) est en `W01` (de 2025) selon ISO ; donc filtré pour
     * `year=2024` il atterrit dans le bucket `1` au lieu de `53`. C'est
     * le comportement strictement préservé de `loadWeekDensity`.
     *
     * Cf. plan optim perf 2026-05-15 S2.1 (cause C-6 ·
     * expandToDaysInYear day-by-day).
     *
     * @return array<int, int>
     */
    public function daysByWeekInYear(int $year): array
    {
        $yearStart = CarbonImmutable::create($year, 1, 1);
        $yearEnd = CarbonImmutable::create($year, 12, 31);

        $start = $this->start_date->toImmutable();
        $end = $this->end_date->toImmutable();

        $rangeStart = $start->isAfter($yearStart) ? $start : $yearStart;
        $rangeEnd = $end->isBefore($yearEnd) ? $end : $yearEnd;

        if ($rangeStart->isAfter($rangeEnd)) {
            return [];
        }

        // SC16 (2026-05-18) · indexation par position de CELLULE dans la
        // heatmap year (1..53) au lieu du numéro de semaine ISO (W01-W53).
        // Garantit que les 1-3 jours de fin Y tombant en sem 1 ISO de Y+1
        // (cas 2024/2025) atterrissent bien dans la dernière cellule de
        // la heatmap year, pas dans la cellule 1 par collision.
        $origin = IsoWeeks::cellOriginForYear($year);

        $byCell = [];
        $cursor = $rangeStart;
        while (! $cursor->isAfter($rangeEnd)) {
            // Jours restants jusqu'à la fin de la semaine ISO en cours
            // (dimanche). dayOfWeekIso · 1=lundi .. 7=dimanche.
            $daysToSunday = 7 - (int) $cursor->dayOfWeekIso;
            $weekEndCandidate = $cursor->addDays($daysToSunday);
            $segmentEnd = $weekEndCandidate->isAfter($rangeEnd) ? $rangeEnd : $weekEndCandidate;

            // Index de cellule dans la heatmap year (1..53)
            $daysSinceOrigin = (int) $origin->diffInDays($cursor);
            $cellIdx = (int) floor($daysSinceOrigin / 7) + 1;

            $daysInThisWeek = (int) $cursor->diffInDays($segmentEnd) + 1;
            $byCell[$cellIdx] = ($byCell[$cellIdx] ?? 0) + $daysInThisWeek;

            $cursor = $segmentEnd->addDay();
        }

        return $byCell;
    }
}
