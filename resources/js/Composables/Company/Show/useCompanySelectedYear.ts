import { computed, onMounted, ref, watch } from 'vue';
import type { ComputedRef, Ref } from 'vue';

type ActivityYear = App.Data.User.Company.CompanyActivityYearData;

/**
 * Local year selector for the Activity section of the company page (ADR-0020 D3).
 *
 * - On mount reads `?year=YYYY` from the query string, falling back to `currentRealYear`.
 * - Every mutation of `selectedYear` is mirrored to the URL via `window.history.replaceState`.
 * - `byYear` is derived locally from `activityByYear` (backend pre-computes all `availableYears`,
 *   no Inertia reload). If the selected year is not in `availableYears` (e.g. `?year=2030`),
 *   an empty activity (12 zeros + empty topVehicles) is returned for a coherent empty state.
 *
 * No dependency on any global year selector: the company page is timeless by nature;
 * only the Activity section is locally year-filterable.
 */
export function useCompanySelectedYear(opts: {
    activityByYear: Readonly<Ref<readonly ActivityYear[]>>;
    availableYears: Readonly<Ref<readonly number[]>>;
    currentRealYear: Readonly<Ref<number>>;
}): {
    selectedYear: Ref<number>;
    byYear: ComputedRef<ActivityYear>;
    setSelectedYear: (year: number) => void;
} {
    const selectedYear = ref<number>(opts.currentRealYear.value);

    function readFromUrl(): number {
        if (typeof window === 'undefined') {
            return opts.currentRealYear.value;
        }

        const params = new URLSearchParams(window.location.search);
        const raw = params.get('year');

        if (raw === null || raw === '') {
            return opts.currentRealYear.value;
        }

        const parsed = Number.parseInt(raw, 10);

        if (Number.isNaN(parsed) || parsed < 1900 || parsed > 2100) {
            return opts.currentRealYear.value;
        }

        return parsed;
    }

    function writeToUrl(year: number): void {
        if (typeof window === 'undefined') {
            return;
        }

        const url = new URL(window.location.href);

        if (year === opts.currentRealYear.value) {
            // Clean URL omits the query param when on the real year.
            url.searchParams.delete('year');
        } else {
            url.searchParams.set('year', String(year));
        }

        window.history.replaceState({}, '', url.toString());
    }

    onMounted(() => {
        selectedYear.value = readFromUrl();
    });

    watch(selectedYear, (value) => {
        writeToUrl(value);
    });

    function emptyActivity(year: number): ActivityYear {
        return {
            year,
            daysByMonth: [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0],
            topVehicles: [],
        };
    }

    const byYear = computed<ActivityYear>(() => {
        const found = opts.activityByYear.value.find(
            (entry) => entry.year === selectedYear.value,
        );

        return found ?? emptyActivity(selectedYear.value);
    });

    function setSelectedYear(year: number): void {
        selectedYear.value = year;
    }

    return {
        selectedYear,
        byYear,
        setSelectedYear,
    };
}
