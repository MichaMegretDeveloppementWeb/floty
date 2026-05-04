import { computed, onMounted, ref, watch } from 'vue';
import type { ComputedRef, Ref } from 'vue';

type YearStats = App.Data.User.Company.CompanyYearStatsData;

/**
 * Sélecteur d'année **local** de la fiche entreprise (chantier K,
 * ADR-0020 D3).
 *
 * Pattern :
 *   - Au mount, lit `?year=YYYY` du query param ; fallback à
 *     `currentRealYear` (passé par le backend).
 *   - Toute mutation de `selectedYear` est sérialisée dans l'URL via
 *     `window.history.replaceState` (cohérent avec le pattern
 *     `useCompanyTabs`).
 *   - `byYear` est dérivé localement de `history` côté client — pas de
 *     reload Inertia. Si l'année sélectionnée n'est pas dans
 *     `availableYears` (cas typique : utilisateur tape l'URL avec une
 *     année future), on retourne des stats vides (cohérent avec
 *     `CompanyQueryService::emptyYearStats` côté PHP).
 *
 * Note : aucune dépendance au sélecteur d'année global (`useFiscalYear`)
 * — la fiche entreprise est par essence intemporelle, seules certaines
 * sections sont filtrables localement par année (cf. ADR-0020 D3).
 */
export function useCompanySelectedYear(opts: {
    history: Readonly<Ref<readonly YearStats[]>>;
    availableYears: Readonly<Ref<readonly number[]>>;
    currentRealYear: Readonly<Ref<number>>;
}): {
    selectedYear: Ref<number>;
    byYear: ComputedRef<YearStats>;
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
            // L'URL « propre » de la fiche est l'année réelle ; on omet
            // alors le query param pour rester économe.
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

    function emptyStats(year: number): YearStats {
        return {
            year,
            daysUsed: 0,
            contractsCount: 0,
            lcdCount: 0,
            lldCount: 0,
            annualTaxDue: 0,
            rent: null,
        };
    }

    const byYear = computed<YearStats>(() => {
        const found = opts.history.value.find(
            (entry) => entry.year === selectedYear.value,
        );

        return found ?? emptyStats(selectedYear.value);
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
