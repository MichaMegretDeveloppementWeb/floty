/**
 * Onglets de la fiche Show Véhicule · sync `?tab=` URL + chargement
 * lazy + cumulatif des props par-onglet (D5.10.V).
 *
 * Pattern symétrique à `useCompanyTabs` · cf. cette doctrine pour le
 * détail (Inertia::optional côté backend, visitedTabs Set côté front,
 * markStale par les sélecteurs d'année).
 */

import { router } from '@inertiajs/vue3';
import type { InjectionKey, Ref } from 'vue';
import { computed, inject, onMounted, provide, ref, watch } from 'vue';

export type VehicleTabKey = 'overview' | 'fiscal' | 'billing';

const VALID_TABS: readonly VehicleTabKey[] = ['overview', 'fiscal', 'billing'];

const DEFAULT_TAB: VehicleTabKey = 'overview';

/**
 * `billingYear` / `fiscalYear` (eager) servent au highlight pill ·
 * doivent être inclus dans le partial reload sinon mismatch entre
 * titre rendu et pill active après tab switch (cf. `useCompanyTabs`).
 */
const TAB_PROPS: Readonly<Record<VehicleTabKey, readonly string[]>> = {
    overview: [],
    fiscal: ['fiscalYearBreakdown', 'fiscalYear', 'billingYear'],
    billing: ['vehicleBilling', 'billingYear', 'fiscalYear'],
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
        activeTab: computed({
            get: () => activeTab.value,
            set: (v: VehicleTabKey) => (activeTab.value = v),
        }) as unknown as Ref<VehicleTabKey>,
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
