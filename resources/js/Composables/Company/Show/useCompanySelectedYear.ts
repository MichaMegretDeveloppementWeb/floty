import { computed, onMounted, ref, watch } from 'vue';
import type { ComputedRef, Ref } from 'vue';

type ActivityYear = App.Data.User.Company.CompanyActivityYearData;

/**
 * Sélecteur d'année **local** de la section Activité de la fiche
 * entreprise (chantier K L2, ADR-0020 D3).
 *
 * Pattern :
 *   - Au mount, lit `?year=YYYY` du query param ; fallback à
 *     `currentRealYear` (passé par le backend).
 *   - Toute mutation de `selectedYear` est sérialisée dans l'URL via
 *     `window.history.replaceState` (cohérent avec
 *     `useCompanyTabs`).
 *   - `byYear` est dérivé localement de `activityByYear` côté client
 *     (le backend pré-calcule pour toutes les `availableYears`, pas
 *     de reload Inertia). Si l'année sélectionnée n'est pas dans
 *     `availableYears` (ex. `?year=2030`), on retourne une activité
 *     vide (12 cases à 0 + topVehicles vide) pour permettre à l'UI
 *     d'afficher un état vide cohérent.
 *
 * Pas de dépendance au sélecteur d'année global (cf. ADR-0020 D3 —
 * la fiche entreprise est par essence intemporelle ; seule la section
 * Activité est filtrable localement par année).
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
            // L'URL « propre » de la fiche est l'année réelle ; on omet
            // le query param dans ce cas pour rester économe.
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
