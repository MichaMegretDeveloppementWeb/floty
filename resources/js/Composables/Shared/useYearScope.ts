/**
 * Façade haut-niveau pour piloter un sélecteur d'année local à une page,
 * fondation de la doctrine temporelle (chantier η Phase 0.4).
 *
 * **Responsabilités** :
 *   - Expose le scope d'années (`currentYear`, `minYear`, `availableYears`)
 *     depuis le DTO {@link App.Data.Shared.YearScopeData} reçu en prop.
 *   - Détient l'état mutable `selectedYear` initialisé sur `currentYear`
 *     (ou override via `opts.initialYear`).
 *   - Mute via `selectYear()` avec validation contre `availableYears`.
 *
 * **Deux modes d'usage** suivant la structure de la page consommatrice :
 *
 *   1. **Mode reload** (`opts.reloadKeys` défini) — typique pour pages
 *      Index / Show qui doivent recharger les données depuis le backend
 *      (Vehicles Index, Vehicle Show, Planning, FiscalRules…). Délègue
 *      à `useLocalYearSelector` qui appelle `router.get()` avec partial
 *      reload.
 *
 *   2. **Mode local** (`opts` omis ou `reloadKeys` vide) — typique pour
 *      sections où toutes les années sont déjà pré-calculées côté front
 *      (ex. section Activité fiche Entreprise — l'array `activityByYear`
 *      contient déjà toutes les années). Sync URL via
 *      `window.history.replaceState`, pas de reload Inertia.
 *
 * **Composant compagnon** : {@link YearSelector} (présentationnel pur).
 *
 * **Exemple — mode reload** :
 *   const scope = useYearScope(props.yearScope, {
 *     reloadKeys: ['vehicles', 'query'],
 *   });
 *
 * **Exemple — mode local** :
 *   const scope = useYearScope(props.yearScope);
 */

import { computed, ref } from 'vue';
import type { ComputedRef, Ref, WritableComputedRef } from 'vue';
import { useLocalYearSelector } from './useLocalYearSelector';

type YearScope = App.Data.Shared.YearScopeData;

export type UseYearScopeReturn = {
    /** Année calendaire courante (Présent). Non mutable depuis le UI. */
    currentYear: ComputedRef<number>;
    /** Borne min globale du sélecteur. */
    minYear: ComputedRef<number>;
    /** Range continu `[minYear, …, max]`. */
    availableYears: ComputedRef<readonly number[]>;
    /**
     * Année actuellement sélectionnée. Lecture seule conseillée — pour
     * un binding `v-model` côté composant, utiliser plutôt
     * {@see selectedYearModel} qui passe systématiquement par
     * {@see selectYear} (validation + sync URL/reload).
     */
    selectedYear: Ref<number>;
    /**
     * Wrapper `v-model` autour de `selectedYear`. Le setter appelle
     * `selectYear()` plutôt que de muter `selectedYear.value`
     * directement — garantit que la sync URL (mode local) ou le partial
     * reload (mode reload) est toujours déclenché, même quand la
     * mutation vient d'un binding `v-model`.
     */
    selectedYearModel: WritableComputedRef<number>;
    /** `true` ssi `availableYears.length > 1` (sinon sélecteur figé). */
    canSelect: ComputedRef<boolean>;
    /** Vrai ssi `year` ∈ `availableYears`. */
    isInScope: (year: number) => boolean;
    /**
     * Mutate `selectedYear`. No-op si l'année est hors scope ou
     * identique à la valeur courante. Selon le mode, déclenche un
     * partial reload Inertia (mode reload) ou un replace URL silencieux
     * (mode local).
     */
    selectYear: (year: number) => void;
};

export type UseYearScopeOptions = {
    /**
     * Clés Inertia à recharger au changement d'année (passées à
     * `router.get(..., { only: [...] })`). Si omis ou vide, le mode
     * local est utilisé : URL replace silencieux sans reload.
     */
    reloadKeys?: readonly string[];
    /**
     * Année initiale à utiliser au montage. Défaut : `scope.currentYear`.
     * Doit être dans `scope.availableYears` (sinon fallback `currentYear`).
     */
    initialYear?: number;
};

export function useYearScope(
    scope: YearScope,
    opts: UseYearScopeOptions = {},
): UseYearScopeReturn {
    const currentYear = computed<number>(() => scope.currentYear);
    const minYear = computed<number>(() => scope.minYear);
    const availableYears = computed<readonly number[]>(() => scope.availableYears);

    const isInScope = (year: number): boolean =>
        scope.availableYears.includes(year);

    // Préserve le deep-link / refresh F5 : si la page est ouverte avec
    // `?year=YYYY` dans l'URL (cas d'un partage de lien ou d'un F5 après
    // bascule), on initialise sur cette valeur. La priorité explicite
    // `opts.initialYear` reste prioritaire (cas usage avancé), et on
    // retombe sur `scope.currentYear` si rien n'est exploitable.
    function readYearFromUrl(): number | undefined {
        if (typeof window === 'undefined') {
            return undefined;
        }

        const raw = new URL(window.location.href).searchParams.get('year');

        if (raw === null) {
            return undefined;
        }

        const parsed = Number(raw);

        return Number.isFinite(parsed) ? parsed : undefined;
    }

    const candidate = opts.initialYear ?? readYearFromUrl();

    const initial =
        candidate !== undefined && isInScope(candidate)
            ? candidate
            : scope.currentYear;

    const useReload = (opts.reloadKeys?.length ?? 0) > 0;

    // Mode reload : on délègue à useLocalYearSelector (partial reload + URL).
    // Mode local : on gère soi-même le replace URL silencieux.
    const reloadDelegate = useReload
        ? useLocalYearSelector(initial, opts.reloadKeys ?? [])
        : null;

    const selectedYear: Ref<number> =
        reloadDelegate?.selectedYear ?? ref<number>(initial);

    const canSelect = computed<boolean>(() => scope.availableYears.length > 1);

    function selectYear(year: number): void {
        if (year === selectedYear.value) {
            return;
        }

        if (!isInScope(year)) {
            return;
        }

        if (reloadDelegate !== null) {
            reloadDelegate.selectYear(year);

            return;
        }

        // Mode local : mutation + replace URL silencieux (cohérent
        // avec le pattern useCompanySelectedYear, sans reload Inertia).
        selectedYear.value = year;

        if (typeof window !== 'undefined') {
            const url = new URL(window.location.href);
            url.searchParams.set('year', String(year));
            window.history.replaceState({}, '', url.toString());
        }
    }

    // v-model wrapper : passe systématiquement par selectYear() pour
    // garantir validation + sync URL/reload, même quand la mutation
    // vient d'un binding `v-model` côté composant Vue (le set direct
    // de selectedYear.value bypasserait selectYear sinon).
    const selectedYearModel = computed<number>({
        get: () => selectedYear.value,
        set: (value: number) => selectYear(value),
    });

    return {
        currentYear,
        minYear,
        availableYears,
        selectedYear,
        selectedYearModel,
        canSelect,
        isInScope,
        selectYear,
    };
}
