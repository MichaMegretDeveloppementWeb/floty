import { computed, ref, watch } from 'vue';
import type { ComputedRef, Ref } from 'vue';

export type DateRange = {
    startDate: string | null;
    endDate: string | null;
};

export type DayCell = {
    iso: string;
    day: number;
    inMonth: boolean;
    disabled: boolean;
    isStart: boolean;
    isEnd: boolean;
    isInRange: boolean;
    inCurrentWeek: boolean;
};

export type SelectOption<T extends string | number> = {
    value: T;
    label: string;
};

const FRENCH_MONTH_FORMATTER = new Intl.DateTimeFormat('fr-FR', {
    month: 'long',
});
const DAYS_IN_WEEK = 7;
const CALENDAR_ROWS = 6;
const YEAR_RANGE_HALF = 5;

/**
 * Convertit une `Date` JS en ISO `YYYY-MM-DD` local (pas UTC, pour éviter
 * les décalages timezone qui décaleraient le jour affiché).
 */
export function formatIso(d: Date): string {
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');

    return `${y}-${m}-${day}`;
}

/**
 * `2024-05-15` → `15/05/2024` pour affichage utilisateur français.
 */
export function formatFr(iso: string): string {
    const [y, m, d] = iso.split('-');

    return `${d}/${m}/${y}`;
}

/**
 * Validation stricte d'une chaîne ISO `YYYY-MM-DD` : format exact, date
 * réelle, round-trip identique (rejette `2024-02-30`).
 */
export function isValidIsoDate(s: string): boolean {
    if (!/^\d{4}-\d{2}-\d{2}$/.test(s)) {
        return false;
    }

    const d = new Date(`${s}T00:00:00`);

    if (Number.isNaN(d.getTime())) {
        return false;
    }

    return formatIso(d) === s;
}

/**
 * Trie deux dates ISO et retourne `[min, max]`. Auto-normalize utilisé
 * partout où l'ordre des bornes peut être inversé (2ᵉ clic antérieur,
 * saisie input end < start).
 */
export function normalizeRange(a: string, b: string): [string, string] {
    return a <= b ? [a, b] : [b, a];
}

/**
 * Liste les dates de `[start, end]` (bornes incluses) présentes dans
 * `disabledSet`. Vide = aucun conflit, plage acceptable.
 */
export function rangeConflicts(
    start: string,
    end: string,
    disabledSet: ReadonlySet<string>,
): string[] {
    const conflicts: string[] = [];
    const a = new Date(`${start}T00:00:00`);
    const b = new Date(`${end}T00:00:00`);
    const cur = new Date(a);

    while (cur <= b) {
        const iso = formatIso(cur);

        if (disabledSet.has(iso)) {
            conflicts.push(iso);
        }

        cur.setDate(cur.getDate() + 1);
    }

    return conflicts;
}

/**
 * Retourne la **plus longue sous-plage libre** (consécutive) à
 * l'intérieur de `[start, end]` qui ne contient aucune date de
 * `disabledSet`. Retourne `null` si aucune date n'est libre dans la
 * plage demandée.
 *
 * Cas de figure couverts (cf. ContractFormFields, watcher au changement
 * de véhicule) :
 *   - aucune intersection → la plage entière `[start, end]` est rendue
 *   - intersection au milieu (`12-20` libre, sauf `17-19` pris) →
 *     `12-16` (5 j) > `20-20` (1 j) → on garde `12-16`
 *   - plusieurs trous de longueurs égales → on garde le premier
 *     rencontré (déterministe)
 *   - aucune date libre → `null`
 *
 * En cas d'égalité de longueur, on conserve la première sous-plage
 * rencontrée chronologiquement (sémantique « commencer le plus tôt
 * possible » par défaut).
 */
export function findLongestFreeSubrange(
    start: string,
    end: string,
    disabledSet: ReadonlySet<string>,
): { start: string; end: string } | null {
    let bestStart: string | null = null;
    let bestEnd: string | null = null;
    let bestLen = 0;

    let curStart: string | null = null;
    let curEnd: string | null = null;
    let curLen = 0;

    const finalize = (): void => {
        if (curStart !== null && curEnd !== null && curLen > bestLen) {
            bestStart = curStart;
            bestEnd = curEnd;
            bestLen = curLen;
        }
    };

    const a = new Date(`${start}T00:00:00`);
    const b = new Date(`${end}T00:00:00`);
    const cur = new Date(a);

    while (cur <= b) {
        const iso = formatIso(cur);

        if (disabledSet.has(iso)) {
            finalize();
            curStart = null;
            curEnd = null;
            curLen = 0;
        } else {
            if (curStart === null) {
                curStart = iso;
            }

            curEnd = iso;
            curLen += 1;
        }

        cur.setDate(cur.getDate() + 1);
    }

    finalize();

    return bestStart !== null && bestEnd !== null
        ? { start: bestStart, end: bestEnd }
        : null;
}

/**
 * État + handlers du `DateRangePicker`. Toute la logique vit ici pour
 * respecter la convention « strict minimum dans les .vue ». Le template
 * du composant délègue intégralement.
 */
export function useDateRangePicker(
    yearProp: Readonly<Ref<number>>,
    startMonthProp: Readonly<Ref<number>>,
    disabledDatesProp: Readonly<Ref<readonly string[]>>,
    range: Ref<DateRange>,
    ongoing: Ref<boolean>,
    highlightDatesProp: Readonly<Ref<readonly string[]>> = ref<readonly string[]>([]),
): {
    currentYear: Ref<number>;
    currentMonth: Ref<number>;
    errorMessage: Ref<string | null>;
    monthLabel: ComputedRef<string>;
    monthOptions: ComputedRef<SelectOption<number>[]>;
    yearOptions: ComputedRef<SelectOption<number>[]>;
    weeks: ComputedRef<DayCell[][]>;
    summary: ComputedRef<string>;
    disabledSet: ComputedRef<Set<string>>;
    gotoPrevMonth: () => void;
    gotoNextMonth: () => void;
    setMonth: (month: number) => void;
    setYear: (year: number) => void;
    onDayClick: (cell: DayCell) => void;
    onStartDateInput: (iso: string) => void;
    onEndDateInput: (iso: string) => void;
    clearSelection: () => void;
} {
    const currentYear = ref<number>(yearProp.value);
    const currentMonth = ref<number>(startMonthProp.value);
    const errorMessage = ref<string | null>(null);

    const disabledSet = computed<Set<string>>(
        () => new Set(disabledDatesProp.value),
    );

    const highlightSet = computed<Set<string>>(
        () => new Set(highlightDatesProp.value),
    );

    // Ongoing activé → on retire la date de fin si présente.
    watch(ongoing, (value) => {
        if (value) {
            range.value = { startDate: range.value.startDate, endDate: null };
            errorMessage.value = null;
        }
    });

    // Le parent peut piloter dynamiquement la fenêtre du calendrier
    // (ex. : ouverture de la modale d'édition d'une indispo en mai →
    // le picker doit s'ouvrir en mai et non en janvier). Sans ce
    // watcher, `currentYear` / `currentMonth` étaient figés à la
    // valeur du premier mount et ignoraient les changements ultérieurs.
    watch(yearProp, (value) => {
        currentYear.value = value;
    });
    watch(startMonthProp, (value) => {
        currentMonth.value = value;
    });

    const monthLabel = computed<string>(() => {
        const date = new Date(currentYear.value, currentMonth.value - 1, 1);

        return date.toLocaleDateString('fr-FR', {
            month: 'long',
            year: 'numeric',
        });
    });

    const monthOptions = computed<SelectOption<number>[]>(() => {
        const opts: SelectOption<number>[] = [];

        for (let m = 1; m <= 12; m++) {
            const d = new Date(2024, m - 1, 1);
            const label = FRENCH_MONTH_FORMATTER.format(d);
            opts.push({
                value: m,
                label: label.charAt(0).toUpperCase() + label.slice(1),
            });
        }

        return opts;
    });

    // ±5 ans glissants autour de la prop year (10 années pour V2).
    const yearOptions = computed<SelectOption<number>[]>(() => {
        const center = yearProp.value;
        const opts: SelectOption<number>[] = [];

        for (let y = center - YEAR_RANGE_HALF; y <= center + YEAR_RANGE_HALF; y++) {
            opts.push({ value: y, label: String(y) });
        }

        return opts;
    });

    const weeks = computed<DayCell[][]>(() => {
        const year = currentYear.value;
        const monthIdx = currentMonth.value - 1;

        const firstOfMonth = new Date(year, monthIdx, 1);
        const jsDayOfWeek = firstOfMonth.getDay(); // 0=Dim
        const leading = (jsDayOfWeek + 6) % 7; // Lundi = 0

        const gridStart = new Date(year, monthIdx, 1 - leading);
        const rows: DayCell[][] = [];

        const start = range.value.startDate;
        const end = range.value.endDate;

        for (let row = 0; row < CALENDAR_ROWS; row++) {
            const week: DayCell[] = [];

            for (let col = 0; col < DAYS_IN_WEEK; col++) {
                const d = new Date(
                    gridStart.getFullYear(),
                    gridStart.getMonth(),
                    gridStart.getDate() + row * DAYS_IN_WEEK + col,
                );
                const iso = formatIso(d);
                const isStart = start !== null && iso === start;
                const isEnd = end !== null && iso === end;
                const isInRange =
                    start !== null
                    && end !== null
                    && iso > start
                    && iso < end;

                week.push({
                    iso,
                    day: d.getDate(),
                    inMonth: d.getMonth() === monthIdx,
                    disabled: disabledSet.value.has(iso),
                    isStart,
                    isEnd,
                    isInRange,
                    inCurrentWeek: highlightSet.value.has(iso),
                });
            }

            rows.push(week);

            const last = week[6]!.iso;
            const lastDate = new Date(`${last}T00:00:00`);

            if (lastDate.getMonth() !== monthIdx && row >= 4) {
                break;
            }
        }

        return rows;
    });

    const summary = computed<string>(() => {
        const start = range.value.startDate;
        const end = range.value.endDate;

        if (start === null) {
            return 'Aucune sélection';
        }

        if (ongoing.value) {
            return `Depuis le ${formatFr(start)} (en cours)`;
        }

        if (end === null) {
            return `Début : ${formatFr(start)}, sélectionnez la fin`;
        }

        return `Du ${formatFr(start)} au ${formatFr(end)}`;
    });

    function gotoPrevMonth(): void {
        if (currentMonth.value > 1) {
            currentMonth.value -= 1;

            return;
        }

        currentMonth.value = 12;
        currentYear.value -= 1;
    }

    function gotoNextMonth(): void {
        if (currentMonth.value < 12) {
            currentMonth.value += 1;

            return;
        }

        currentMonth.value = 1;
        currentYear.value += 1;
    }

    function setMonth(month: number): void {
        currentMonth.value = month;
    }

    function setYear(year: number): void {
        currentYear.value = year;
    }

    function jumpToIsoMonth(iso: string): void {
        const parts = iso.split('-').map(Number);
        const [y, m] = parts;

        if (
            y !== undefined
            && m !== undefined
            && Number.isFinite(y)
            && Number.isFinite(m)
        ) {
            currentYear.value = y;
            currentMonth.value = m;
        }
    }

    /**
     * Tente d'appliquer la plage `[start, end]` au modèle ; si conflit
     * `disabledDates`, pose `errorMessage` et laisse la plage inchangée.
     */
    function tryApplyRange(start: string, end: string): boolean {
        const conflicts = rangeConflicts(start, end, disabledSet.value);

        if (conflicts.length > 0) {
            errorMessage.value = 'La plage choisie chevauche des dates déjà attribuées.';

            return false;
        }

        range.value = { startDate: start, endDate: end };
        errorMessage.value = null;

        return true;
    }

    function onDayClick(cell: DayCell): void {
        if (cell.disabled) {
            return;
        }

        const iso = cell.iso;

        // Pas de startDate ou range complète → 1er clic = nouveau start.
        if (
            range.value.startDate === null
            || (range.value.startDate !== null && range.value.endDate !== null)
        ) {
            range.value = { startDate: iso, endDate: null };
            errorMessage.value = null;

            return;
        }

        // Mode ongoing : tout clic ré-ancre juste le start.
        if (ongoing.value) {
            range.value = { startDate: iso, endDate: null };
            errorMessage.value = null;

            return;
        }

        // 2ᵉ clic en mode plage : auto-normalize.
        const start = range.value.startDate;

        if (iso === start) {
            range.value = { startDate: start, endDate: start };
            errorMessage.value = null;

            return;
        }

        const [normStart, normEnd] = normalizeRange(start, iso);
        tryApplyRange(normStart, normEnd);
    }

    function onStartDateInput(iso: string): void {
        if (!isValidIsoDate(iso)) {
            return;
        }

        const currentEnd = range.value.endDate;

        // Pas d'endDate : on pose juste startDate (vérifie qu'iso n'est
        // pas dans `disabledDates` car aucune plage à valider).
        if (currentEnd === null) {
            if (disabledSet.value.has(iso)) {
                errorMessage.value = `Date refusée : ${formatFr(iso)} est déjà attribuée.`;

                return;
            }

            range.value = { startDate: iso, endDate: null };
            errorMessage.value = null;
            jumpToIsoMonth(iso);

            return;
        }

        // endDate présent : on auto-normalize si iso > endDate.
        const [normStart, normEnd] = normalizeRange(iso, currentEnd);
        const ok = tryApplyRange(normStart, normEnd);

        if (ok) {
            jumpToIsoMonth(normStart);
        }
    }

    function onEndDateInput(iso: string): void {
        if (!isValidIsoDate(iso)) {
            return;
        }

        const currentStart = range.value.startDate;

        // Pas de startDate : input Fin agit comme Début (UX permissive).
        if (currentStart === null) {
            if (disabledSet.value.has(iso)) {
                errorMessage.value = `Date refusée : ${formatFr(iso)} est déjà attribuée.`;

                return;
            }

            range.value = { startDate: iso, endDate: null };
            errorMessage.value = null;
            jumpToIsoMonth(iso);

            return;
        }

        // Auto-normalize (swap si iso < currentStart).
        const [normStart, normEnd] = normalizeRange(currentStart, iso);
        const ok = tryApplyRange(normStart, normEnd);

        if (ok) {
            jumpToIsoMonth(normEnd);
        }
    }

    function clearSelection(): void {
        range.value = { startDate: null, endDate: null };
        errorMessage.value = null;
    }

    return {
        currentYear,
        currentMonth,
        errorMessage,
        monthLabel,
        monthOptions,
        yearOptions,
        weeks,
        summary,
        disabledSet,
        gotoPrevMonth,
        gotoNextMonth,
        setMonth,
        setYear,
        onDayClick,
        onStartDateInput,
        onEndDateInput,
        clearSelection,
    };
}
