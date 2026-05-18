/**
 * Onglets de la fiche Show Company · sync `?tab=` URL + chargement
 * lazy + cumulatif des props par-onglet (D5.10.V).
 *
 * **Doctrine** · au mount initial, seul l'onglet actif a ses props
 * eager (cf. `CompanyController::show`). Les autres onglets sont en
 * `Inertia::optional()` côté backend · leur SQL ne s'exécute QUE
 * lors d'un partial reload déclenché par `setTab(...)` la première
 * fois que l'utilisateur clique l'onglet. Un `Set<TabKey>` tracke
 * les onglets déjà visités · ils sont instant au retour (preserveState
 * conserve les props en mémoire client).
 *
 * Les sélecteurs d'année (cf. `useCompanyFiscalSelectedYear` et
 * `CompanyBillingTab::selectYear`) appellent `markStale(...)` sur les
 * autres onglets year-dépendants après changement d'année · ces
 * onglets se rechargeront automatiquement au prochain clic.
 */

import { router } from '@inertiajs/vue3';
import type { InjectionKey, Ref } from 'vue';
import { inject, onMounted, provide, ref, watch } from 'vue';

export type CompanyTabKey =
    | 'overview'
    | 'contracts'
    | 'drivers'
    | 'fiscal'
    | 'billing';

const VALID_TABS: readonly CompanyTabKey[] = [
    'overview',
    'contracts',
    'drivers',
    'fiscal',
    'billing',
];

const DEFAULT_TAB: CompanyTabKey = 'overview';

/**
 * Compatibilité bookmarks : l'ancien tab key `infos` (avant chantier K,
 * cf. ADR-0020 D3) est silencieusement remappé vers `overview` à
 * l'arrivée pour ne pas casser les liens partagés.
 */
const LEGACY_TAB_ALIASES: Readonly<Record<string, CompanyTabKey>> = {
    infos: 'overview',
};

/**
 * Props Inertia à charger pour chaque onglet · keys de `Inertia::
 * optional()` côté `CompanyController::show`. Si la liste est vide
 * (`overview`), l'onglet n'a pas de prop spécifique · ses données
 * sont dans les props eager partagées.
 */
/**
 * `billingYear` (eager) sert à highlighter la pill active sur les
 * onglets Fiscalité et Facturation · doit être inclus dans le partial
 * reload sinon le client garde la valeur du mount initial alors que les
 * props year-dép sont à jour (mismatch entre titre « Facturation 2024 »
 * et pill highlightée 2026).
 */
const TAB_PROPS: Readonly<Record<CompanyTabKey, readonly string[]>> = {
    overview: [],
    contracts: ['contracts', 'contractsStats', 'contractsQuery', 'billingYear'],
    drivers: ['options'],
    fiscal: ['companyFiscal', 'declarationLifecycle', 'billingYear'],
    billing: ['companyBilling', 'companyRentalDiscounts', 'billingYear'],
};

export interface CompanyTabsState {
    activeTab: Ref<CompanyTabKey>;
    setTab: (tab: CompanyTabKey) => void;
    isActive: (tab: CompanyTabKey) => boolean;
    /** Tab actuellement en cours de partial reload (null si aucun). */
    loadingTab: Ref<CompanyTabKey | null>;
    /**
     * Invalide les onglets passés · ils repasseront par un partial
     * reload au prochain `setTab(...)` qui les ciblera. Appelé par les
     * sélecteurs d'année quand la donnée d'un onglet non-actif devient
     * obsolète (ex. changement de year sur Fiscal invalide Billing).
     */
    markStale: (tabs: readonly CompanyTabKey[]) => void;
}

const COMPANY_TABS_KEY: InjectionKey<CompanyTabsState> = Symbol('companyTabs');

export function useCompanyTabs(): CompanyTabsState {
    const activeTab = ref<CompanyTabKey>(DEFAULT_TAB);
    const visitedTabs = ref<Set<CompanyTabKey>>(new Set());
    const loadingTab = ref<CompanyTabKey | null>(null);

    function readFromUrl(): CompanyTabKey {
        if (typeof window === 'undefined') {
            return DEFAULT_TAB;
        }

        const params = new URLSearchParams(window.location.search);
        const value = params.get('tab');

        if (value === null) {
            return DEFAULT_TAB;
        }

        if (value in LEGACY_TAB_ALIASES) {
            return LEGACY_TAB_ALIASES[value]!;
        }

        if ((VALID_TABS as readonly string[]).includes(value)) {
            return value as CompanyTabKey;
        }

        return DEFAULT_TAB;
    }

    function writeToUrl(tab: CompanyTabKey): void {
        if (typeof window === 'undefined') {
            return;
        }

        const url = new URL(window.location.href);

        if (tab === DEFAULT_TAB) {
            url.searchParams.delete('tab');
        } else {
            url.searchParams.set('tab', tab);
        }

        window.history.replaceState({}, '', url.toString());
    }

    function ensureTabLoaded(tab: CompanyTabKey): void {
        if (visitedTabs.value.has(tab)) {
            return;
        }

        const propsToLoad = TAB_PROPS[tab];

        if (propsToLoad.length === 0) {
            visitedTabs.value.add(tab);

            return;
        }

        loadingTab.value = tab;
        const url = new URL(window.location.href);

        router.get(
            url.pathname + url.search,
            {},
            {
                only: [...propsToLoad],
                preserveState: true,
                preserveScroll: true,
                replace: true,
                onSuccess: () => {
                    visitedTabs.value.add(tab);
                },
                onFinish: () => {
                    if (loadingTab.value === tab) {
                        loadingTab.value = null;
                    }
                },
            },
        );
    }

    function setTab(tab: CompanyTabKey): void {
        activeTab.value = tab;
    }

    function isActive(tab: CompanyTabKey): boolean {
        return activeTab.value === tab;
    }

    function markStale(tabs: readonly CompanyTabKey[]): void {
        tabs.forEach((t) => visitedTabs.value.delete(t));
    }

    onMounted(() => {
        const initialTab = readFromUrl();
        activeTab.value = initialTab;
        // L'onglet initial est rendu eager côté backend · pas besoin
        // de partial reload pour lui.
        visitedTabs.value.add(initialTab);
    });

    watch(activeTab, (value) => {
        writeToUrl(value);
        ensureTabLoaded(value);
    });

    const state: CompanyTabsState = {
        activeTab,
        setTab,
        isActive,
        loadingTab,
        markStale,
    };

    provide(COMPANY_TABS_KEY, state);

    return state;
}

/**
 * Injecte l'état partagé depuis n'importe quel descendant de la page
 * Show Company · utilisé par les sélecteurs d'année pour appeler
 * `markStale(...)` sur les onglets year-dépendants après changement.
 *
 * Retourne `null` si appelé hors arbre Show Company (ex. tests
 * unitaires) · le caller doit gérer ce cas gracieusement.
 */
export function injectCompanyTabsState(): CompanyTabsState | null {
    return inject(COMPANY_TABS_KEY, null);
}
