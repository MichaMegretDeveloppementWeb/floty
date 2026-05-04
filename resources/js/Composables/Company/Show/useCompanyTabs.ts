import type { Ref } from 'vue';
import { computed, onMounted, watch } from 'vue';
import { ref } from 'vue';

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
 * Sync de l'onglet actif sur Show Company avec le query param `?tab=...`
 * pour permettre le deep-link (Phase 06 L4 - Q8).
 */
export function useCompanyTabs(): {
    activeTab: Ref<CompanyTabKey>;
    setTab: (tab: CompanyTabKey) => void;
    isActive: (tab: CompanyTabKey) => boolean;
} {
    const activeTab = ref<CompanyTabKey>(DEFAULT_TAB);

    function readFromUrl(): CompanyTabKey {
        if (typeof window === 'undefined') {
            return DEFAULT_TAB;
        }

        const params = new URLSearchParams(window.location.search);
        const value = params.get('tab');

        if (value === null) {
            return DEFAULT_TAB;
        }

        // Remap silencieux des anciens noms d'onglets pour compat
        // bookmarks (ex. `?tab=infos` post-chantier K).
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

    onMounted(() => {
        activeTab.value = readFromUrl();
    });

    watch(activeTab, (value) => {
        writeToUrl(value);
    });

    function setTab(tab: CompanyTabKey): void {
        activeTab.value = tab;
    }

    function isActive(tab: CompanyTabKey): boolean {
        return activeTab.value === tab;
    }

    return {
        activeTab: computed({
            get: () => activeTab.value,
            set: (v: CompanyTabKey) => (activeTab.value = v),
        }),
        setTab,
        isActive,
    };
}
