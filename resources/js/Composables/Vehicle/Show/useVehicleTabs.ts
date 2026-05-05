/**
 * Sync de l'onglet actif sur Show Véhicule avec le query param
 * `?tab=...` pour permettre le deep-link et préserver F5 (chantier η
 * Phase 2 — refonte fiche véhicule en onglets).
 *
 * Pattern symétrique à `useCompanyTabs` (Phase 06 L4 Q8).
 */

import { computed, onMounted, ref, watch } from 'vue';
import type { Ref } from 'vue';

export type VehicleTabKey = 'overview' | 'fiscal' | 'billing';

const VALID_TABS: readonly VehicleTabKey[] = ['overview', 'fiscal', 'billing'];

const DEFAULT_TAB: VehicleTabKey = 'overview';

export function useVehicleTabs(): {
    activeTab: Ref<VehicleTabKey>;
    setTab: (tab: VehicleTabKey) => void;
    isActive: (tab: VehicleTabKey) => boolean;
} {
    const activeTab = ref<VehicleTabKey>(DEFAULT_TAB);

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

    onMounted(() => {
        activeTab.value = readFromUrl();
    });

    watch(activeTab, (value) => {
        writeToUrl(value);
    });

    function setTab(tab: VehicleTabKey): void {
        activeTab.value = tab;
    }

    function isActive(tab: VehicleTabKey): boolean {
        return activeTab.value === tab;
    }

    return {
        activeTab: computed({
            get: () => activeTab.value,
            set: (v: VehicleTabKey) => (activeTab.value = v),
        }),
        setTab,
        isActive,
    };
}
