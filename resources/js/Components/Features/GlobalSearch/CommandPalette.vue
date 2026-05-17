<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { useScrollLock } from '@vueuse/core';
import {
    Building2,
    Car,
    FileText,
    History,
    Receipt,
    Search,
    User,
    X,
} from 'lucide-vue-next';
import type { Component } from 'vue';
import { computed, nextTick, ref, useTemplateRef, watch } from 'vue';
import Kbd from '@/Components/Ui/Kbd/Kbd.vue';
import Skeleton from '@/Components/Ui/Skeleton/Skeleton.vue';
import {
    useCommandPalette,
    useCommandPaletteShortcut,
} from '@/Composables/GlobalSearch/useCommandPalette';
import type { CommandPaletteRecentType } from '@/Composables/GlobalSearch/useCommandPalette';
import { useGlobalSearch } from '@/Composables/GlobalSearch/useGlobalSearch';
import ResultGroup from './partials/ResultGroup.vue';
import ResultItem from './partials/ResultItem.vue';

/**
 * Palette de recherche globale ⌘K (V1.1) · monté **une seule fois**
 * dans `UserLayout`. Couvre les 5 entités définies dans
 * {@see GlobalSearchService} ·
 *
 *  - Véhicules, Entreprises, Conducteurs (toujours)
 *  - Raccourcis contrats (≥ 2 tokens croisés)
 *  - Déclarations (année + entreprise dans la query)
 *
 * Comportement clavier ·
 *  - ⌘K / Ctrl+K · toggle ouverture (cf. {@see useCommandPaletteShortcut})
 *  - Escape · ferme
 *  - ↑ / ↓ · navigation flat (tous groupes confondus, ordre d'affichage)
 *  - Enter · visite l'item actif + ajoute aux recents + ferme
 *
 * État vide (query < 2 caractères) · affiche les 5 derniers items
 * cliqués (localStorage `floty.search.recents`).
 */

const palette = useCommandPalette();

useCommandPaletteShortcut();

const query = ref<string>('');
const activeIndex = ref<number>(0);
const inputRef = useTemplateRef<HTMLInputElement>('inputRef');
const bodyScrollLock = useScrollLock(
    typeof document !== 'undefined' ? document.body : null,
);

const { result, loading, reset: resetSearch } = useGlobalSearch(query);

type FlatItem = {
    icon: Component;
    label: string;
    sublabel: string | null;
    href: string;
    type: CommandPaletteRecentType;
};

const iconByType: Record<CommandPaletteRecentType, Component> = {
    vehicle: Car,
    company: Building2,
    driver: User,
    'contract-shortcut': FileText,
    declaration: Receipt,
};

const showRecents = computed<boolean>(() => {
    return query.value.trim().length < 2;
});

const flatItems = computed<FlatItem[]>(() => {
    if (showRecents.value) {
        return palette.recents.map((r) => ({
            icon: iconByType[r.type as CommandPaletteRecentType],
            label: r.label,
            sublabel: r.sublabel,
            href: r.href,
            type: r.type as CommandPaletteRecentType,
        }));
    }

    if (result.value === null) {
        return [];
    }

    const items: FlatItem[] = [];

    for (const v of result.value.vehicles) {
        items.push({
            icon: Car,
            label: v.label,
            sublabel: v.sublabel,
            href: v.href,
            type: 'vehicle',
        });
    }

    for (const c of result.value.companies) {
        items.push({
            icon: Building2,
            label: c.label,
            sublabel: c.sublabel,
            href: c.href,
            type: 'company',
        });
    }

    for (const d of result.value.drivers) {
        items.push({
            icon: User,
            label: d.label,
            sublabel: d.sublabel,
            href: d.href,
            type: 'driver',
        });
    }

    for (const cs of result.value.contractShortcuts) {
        items.push({
            icon: FileText,
            label: cs.label,
            sublabel: cs.sublabel,
            href: cs.href,
            type: 'contract-shortcut',
        });
    }

    for (const d of result.value.declarations) {
        items.push({
            icon: Receipt,
            label: d.label,
            sublabel: d.sublabel,
            href: d.href,
            type: 'declaration',
        });
    }

    return items;
});

const hasAnyResult = computed<boolean>(() => {
    if (result.value === null) {
        return false;
    }

    return (
        result.value.vehicles.length > 0
        || result.value.companies.length > 0
        || result.value.drivers.length > 0
        || result.value.contractShortcuts.length > 0
        || result.value.declarations.length > 0
    );
});

const showEmptyState = computed<boolean>(() => {
    return (
        !showRecents.value
        && !loading.value
        && result.value !== null
        && !hasAnyResult.value
    );
});

function selectItem(item: FlatItem): void {
    palette.addRecent({
        label: item.label,
        sublabel: item.sublabel,
        href: item.href,
        type: item.type,
    });
    closeAndReset();
    router.visit(item.href);
}

function closeAndReset(): void {
    palette.close();
    query.value = '';
    activeIndex.value = 0;
    resetSearch();
}

function navigate(direction: 1 | -1): void {
    const count = flatItems.value.length;

    if (count === 0) {
        return;
    }

    activeIndex.value = (activeIndex.value + direction + count) % count;
}

function handleKeydown(event: KeyboardEvent): void {
    if (event.key === 'ArrowDown') {
        event.preventDefault();
        navigate(1);

        return;
    }

    if (event.key === 'ArrowUp') {
        event.preventDefault();
        navigate(-1);

        return;
    }

    if (event.key === 'Enter') {
        event.preventDefault();
        const item = flatItems.value[activeIndex.value];

        if (item !== undefined) {
            selectItem(item);
        }
    }
}

watch(palette.isOpen, async (open) => {
        bodyScrollLock.value = open;

        if (open) {
            await nextTick();
            inputRef.value?.focus();
        } else {
            // Reset différé pour éviter un flicker visuel pendant la
            // transition de fermeture (si transition ajoutée plus tard).
            query.value = '';
            activeIndex.value = 0;
            resetSearch();
        }
    });

// Reset activeIndex sur changement de résultat (sinon hors-bornes)
watch(flatItems, () => {
    activeIndex.value = 0;
});
</script>

<template>
    <Teleport v-if="palette.isOpen.value" to="body">
        <div
            class="fixed inset-0 z-50 flex md:items-start md:justify-center md:p-4 md:pt-[12vh]"
            role="dialog"
            aria-modal="true"
            aria-label="Recherche globale"
        >
            <div
                class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm"
                @click="closeAndReset"
            />

            <div
                class="relative flex h-full w-full flex-col overflow-hidden bg-white shadow-3xl md:h-auto md:max-h-[70vh] md:w-full md:max-w-2xl md:rounded-2xl"
            >
                <header
                    class="flex items-center gap-3 border-b border-slate-200 px-4 py-3"
                >
                    <Search
                        :size="18"
                        :stroke-width="1.75"
                        class="shrink-0 text-slate-400"
                        aria-hidden="true"
                    />
                    <input
                        ref="inputRef"
                        v-model="query"
                        type="search"
                        placeholder="Rechercher un véhicule, une entreprise, un conducteur..."
                        aria-label="Recherche globale"
                        class="min-w-0 flex-1 border-0 bg-transparent text-base leading-tight text-slate-900 placeholder:text-slate-400 focus-visible:outline-none"
                        @keydown="handleKeydown"
                    />
                    <button
                        type="button"
                        aria-label="Fermer"
                        class="shrink-0 rounded-md p-1.5 text-slate-400 transition-colors duration-[120ms] ease-out hover:bg-slate-100 hover:text-slate-700 focus-visible:ring-2 focus-visible:ring-slate-100 focus-visible:outline-none md:hidden"
                        @click="closeAndReset"
                    >
                        <X :size="18" :stroke-width="1.75" />
                    </button>
                </header>

                <div class="flex-1 overflow-y-auto p-2">
                    <!-- État loading · skeleton 3 lignes -->
                    <div v-if="loading" class="flex flex-col gap-2 p-2">
                        <Skeleton
                            v-for="i in 3"
                            :key="i"
                            class="h-11 rounded-lg"
                        />
                    </div>

                    <!-- État vide initial · recents localStorage -->
                    <div v-else-if="showRecents">
                        <div
                            v-if="palette.recents.length === 0"
                            class="flex flex-col items-center gap-2 px-3 py-12 text-center"
                        >
                            <Search
                                :size="32"
                                :stroke-width="1.5"
                                class="text-slate-300"
                                aria-hidden="true"
                            />
                            <p class="text-sm text-slate-500">
                                Tapez au moins 2 caractères pour rechercher
                            </p>
                            <p class="text-xs text-slate-400">
                                Véhicules, entreprises, conducteurs, déclarations
                            </p>
                        </div>
                        <ResultGroup v-else title="Récents">
                            <ResultItem
                                v-for="(item, index) in flatItems"
                                :key="`recent-${item.href}`"
                                :icon="History"
                                :label="item.label"
                                :sublabel="item.sublabel"
                                :active="index === activeIndex"
                                @select="selectItem(item)"
                                @hover="activeIndex = index"
                            />
                        </ResultGroup>
                    </div>

                    <!-- État no result -->
                    <div
                        v-else-if="showEmptyState"
                        class="flex flex-col items-center gap-2 px-3 py-12 text-center"
                    >
                        <p class="text-sm text-slate-700">
                            Aucun résultat pour « {{ query }} »
                        </p>
                        <p class="text-xs text-slate-400">
                            Essayez avec moins de mots-clés ou vérifiez
                            l'orthographe
                        </p>
                    </div>

                    <!-- État avec résultats · groupes -->
                    <div
                        v-else-if="result !== null && hasAnyResult"
                        class="flex flex-col gap-1"
                    >
                        <ResultGroup
                            v-if="result.vehicles.length > 0"
                            title="Véhicules"
                        >
                            <ResultItem
                                v-for="v in result.vehicles"
                                :key="`v-${v.id}`"
                                :icon="Car"
                                :label="v.label"
                                :sublabel="v.sublabel"
                                :active="
                                    flatItems[activeIndex]?.href === v.href
                                "
                                @select="
                                    selectItem({
                                        icon: Car,
                                        label: v.label,
                                        sublabel: v.sublabel,
                                        href: v.href,
                                        type: 'vehicle',
                                    })
                                "
                                @hover="
                                    activeIndex = flatItems.findIndex(
                                        (it) => it.href === v.href,
                                    )
                                "
                            />
                        </ResultGroup>
                        <ResultGroup
                            v-if="result.companies.length > 0"
                            title="Entreprises"
                        >
                            <ResultItem
                                v-for="c in result.companies"
                                :key="`c-${c.id}`"
                                :icon="Building2"
                                :label="c.label"
                                :sublabel="c.sublabel"
                                :active="
                                    flatItems[activeIndex]?.href === c.href
                                "
                                @select="
                                    selectItem({
                                        icon: Building2,
                                        label: c.label,
                                        sublabel: c.sublabel,
                                        href: c.href,
                                        type: 'company',
                                    })
                                "
                                @hover="
                                    activeIndex = flatItems.findIndex(
                                        (it) => it.href === c.href,
                                    )
                                "
                            />
                        </ResultGroup>
                        <ResultGroup
                            v-if="result.drivers.length > 0"
                            title="Conducteurs"
                        >
                            <ResultItem
                                v-for="d in result.drivers"
                                :key="`d-${d.id}`"
                                :icon="User"
                                :label="d.label"
                                :sublabel="d.sublabel"
                                :active="
                                    flatItems[activeIndex]?.href === d.href
                                "
                                @select="
                                    selectItem({
                                        icon: User,
                                        label: d.label,
                                        sublabel: d.sublabel,
                                        href: d.href,
                                        type: 'driver',
                                    })
                                "
                                @hover="
                                    activeIndex = flatItems.findIndex(
                                        (it) => it.href === d.href,
                                    )
                                "
                            />
                        </ResultGroup>
                        <ResultGroup
                            v-if="result.contractShortcuts.length > 0"
                            title="Contrats correspondants"
                        >
                            <ResultItem
                                v-for="cs in result.contractShortcuts"
                                :key="`cs-${cs.vehicleId}-${cs.companyId}`"
                                :icon="FileText"
                                :label="cs.label"
                                :sublabel="cs.sublabel"
                                :active="
                                    flatItems[activeIndex]?.href === cs.href
                                "
                                @select="
                                    selectItem({
                                        icon: FileText,
                                        label: cs.label,
                                        sublabel: cs.sublabel,
                                        href: cs.href,
                                        type: 'contract-shortcut',
                                    })
                                "
                                @hover="
                                    activeIndex = flatItems.findIndex(
                                        (it) => it.href === cs.href,
                                    )
                                "
                            />
                        </ResultGroup>
                        <ResultGroup
                            v-if="result.declarations.length > 0"
                            title="Déclarations"
                        >
                            <ResultItem
                                v-for="d in result.declarations"
                                :key="`decl-${d.id}`"
                                :icon="Receipt"
                                :label="d.label"
                                :sublabel="d.sublabel"
                                :active="
                                    flatItems[activeIndex]?.href === d.href
                                "
                                @select="
                                    selectItem({
                                        icon: Receipt,
                                        label: d.label,
                                        sublabel: d.sublabel,
                                        href: d.href,
                                        type: 'declaration',
                                    })
                                "
                                @hover="
                                    activeIndex = flatItems.findIndex(
                                        (it) => it.href === d.href,
                                    )
                                "
                            />
                        </ResultGroup>
                    </div>
                </div>

                <!-- Footer raccourcis (desktop uniquement) -->
                <footer
                    class="hidden items-center gap-4 border-t border-slate-200 bg-slate-50/60 px-4 py-2.5 text-xs text-slate-500 md:flex"
                >
                    <span class="flex items-center gap-1.5">
                        <Kbd>↑</Kbd>
                        <Kbd>↓</Kbd>
                        Naviguer
                    </span>
                    <span class="flex items-center gap-1.5">
                        <Kbd>↵</Kbd>
                        Sélectionner
                    </span>
                    <span class="flex items-center gap-1.5">
                        <Kbd>Esc</Kbd>
                        Fermer
                    </span>
                </footer>
            </div>
        </div>
    </Teleport>
</template>
