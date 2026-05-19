/**
 * Tabs for the Company Show page: syncs `?tab=` to the URL and loads tab props
 * lazily and cumulatively.
 *
 * On the initial mount only the active tab has eager props (`CompanyController::show`).
 * Other tabs use `Inertia::optional()` backend-side: their SQL runs ONLY on the partial
 * reload triggered by `setTab(...)` the first time the user clicks the tab. A
 * `Set<TabKey>` tracks visited tabs so subsequent visits are instant
 * (preserveState keeps props in client memory).
 *
 * Year selectors call `markStale(...)` on other year-dependent tabs after a year change;
 * those tabs auto-reload on the next click.
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
 * Bookmark compatibility: legacy `infos` tab key is silently remapped to `overview`
 * to keep shared links working.
 */
const LEGACY_TAB_ALIASES: Readonly<Record<string, CompanyTabKey>> = {
    infos: 'overview',
};

/**
 * Inertia props to load per tab: keys from `Inertia::optional()` in `CompanyController::show`.
 * An empty list (`overview`) means the tab has no specific prop; its data lives in shared eager props.
 *
 * `billingYear` (eager) is needed in the Fiscal and Facturation reloads to highlight the active pill;
 * without it the client keeps the mount-time value while year-dependent props are fresh,
 * causing a mismatch (e.g. title "Facturation 2024" with pill 2026 highlighted).
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
    /** Tab currently being partially reloaded (null if none). */
    loadingTab: Ref<CompanyTabKey | null>;
    /**
     * Invalidates the given tabs so they redo a partial reload on the next `setTab(...)`.
     * Called by year selectors when an inactive tab's data becomes stale.
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
        // Initial tab is eager on the backend, no partial reload needed.
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
 * Injects the shared state from any descendant of the Company Show page.
 * Used by year selectors to call `markStale(...)` on year-dependent tabs after a change.
 *
 * Returns `null` when called outside the Company Show tree (e.g. unit tests); the caller must handle gracefully.
 */
export function injectCompanyTabsState(): CompanyTabsState | null {
    return inject(COMPANY_TABS_KEY, null);
}
