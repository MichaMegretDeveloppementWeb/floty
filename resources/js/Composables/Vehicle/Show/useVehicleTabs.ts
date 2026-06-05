/**
 * Tabs for the Vehicle Show page: syncs `?tab=` to the URL and loads tab props lazily and cumulatively.
 *
 * Symmetric to `useCompanyTabs`: see that composable's doctrine for the detail
 * (Inertia::optional backend-side, visitedTabs Set front-side, markStale via year selectors).
 */

import { router } from '@inertiajs/vue3';
import type { InjectionKey, Ref } from 'vue';
import { inject, onMounted, provide, ref, watch } from 'vue';

export type VehicleTabKey = 'overview' | 'events' | 'fiscal' | 'billing' | 'controls';

const VALID_TABS: readonly VehicleTabKey[] = ['overview', 'events', 'fiscal', 'billing', 'controls'];

const DEFAULT_TAB: VehicleTabKey = 'overview';

/**
 * `billingYear` / `fiscalYear` (eager) drive the active pill highlight.
 * Must be included in the partial reload, otherwise the rendered title and the active pill mismatch after a tab switch.
 *
 * `overview` carries the heavy usage/KPI/history payload (`vehicleOverview`),
 * eager only on a direct `?tab=overview` load and lazy on first visit from
 * another tab (tab-gating). The former `history` defer is folded into it.
 *
 * `events` has no lazy prop: the events list lives in `vehicle.vehicleEvents`,
 * already eager-loaded by VehicleDetailService.
 */
const TAB_PROPS: Readonly<Record<VehicleTabKey, readonly string[]>> = {
    overview: ['vehicleOverview'],
    events: [],
    fiscal: ['fiscalYearBreakdown', 'fiscalYear', 'billingYear'],
    billing: ['vehicleBilling', 'billingYear', 'fiscalYear'],
    controls: ['vehicleControls'],
};

export interface VehicleTabsState {
    activeTab: Ref<VehicleTabKey>;
    setTab: (tab: VehicleTabKey) => void;
    isActive: (tab: VehicleTabKey) => boolean;
    loadingTab: Ref<VehicleTabKey | null>;
    markStale: (tabs: readonly VehicleTabKey[]) => void;
}

const VEHICLE_TABS_KEY: InjectionKey<VehicleTabsState> = Symbol('vehicleTabs');

export function useVehicleTabs(): VehicleTabsState {
    const activeTab = ref<VehicleTabKey>(DEFAULT_TAB);
    const visitedTabs = ref<Set<VehicleTabKey>>(new Set());
    const loadingTab = ref<VehicleTabKey | null>(null);

    function readFromUrl(): VehicleTabKey {
        if (typeof window === 'undefined') {
            return DEFAULT_TAB;
        }

        const params = new URLSearchParams(window.location.search);
        const value = params.get('tab');

        if (value === null) {
            return DEFAULT_TAB;
        }

        if ((VALID_TABS as readonly string[]).includes(value)) {
            return value as VehicleTabKey;
        }

        return DEFAULT_TAB;
    }

    function writeToUrl(tab: VehicleTabKey): void {
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

    function ensureTabLoaded(tab: VehicleTabKey): void {
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

    function setTab(tab: VehicleTabKey): void {
        activeTab.value = tab;
    }

    function isActive(tab: VehicleTabKey): boolean {
        return activeTab.value === tab;
    }

    function markStale(tabs: readonly VehicleTabKey[]): void {
        tabs.forEach((t) => visitedTabs.value.delete(t));
    }

    onMounted(() => {
        const initialTab = readFromUrl();
        activeTab.value = initialTab;
        visitedTabs.value.add(initialTab);
    });

    watch(activeTab, (value) => {
        writeToUrl(value);
        ensureTabLoaded(value);
    });

    const state: VehicleTabsState = {
        activeTab,
        setTab,
        isActive,
        loadingTab,
        markStale,
    };

    provide(VEHICLE_TABS_KEY, state);

    return state;
}

export function injectVehicleTabsState(): VehicleTabsState | null {
    return inject(VEHICLE_TABS_KEY, null);
}
