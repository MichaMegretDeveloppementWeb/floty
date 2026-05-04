<script setup lang="ts">
/**
 * Composant pagination numéroté avec ellipsis pour les Index server-side
 * (cf. ADR-0020). Présentationnel : émet `page-change` et `per-page-change`,
 * ne contient aucune logique de reload (orchestrée par `useServerTableState`).
 *
 * Algorithme de pagination :
 *  - lastPage ≤ 7 : tous les numéros affichés (`1 2 3 4 5 6 7`)
 *  - sinon : `1` + (ellipsis si gap) + `current-1, current, current+1`
 *    + (ellipsis si gap) + `lastPage`
 *
 * UI : « Affichage X–Y sur Z » à gauche, boutons pages au centre,
 * sélecteur perPage à droite. Strings FR hardcodées.
 */

import { ChevronLeft, ChevronRight } from 'lucide-vue-next';
import { computed } from 'vue';

type PageItem = number | 'ellipsis';

type PerPageOption = 10 | 20 | 50 | 100;

const props = withDefaults(
    defineProps<{
        meta: App.Data.Shared.Listing.PaginationMetaData;
        perPageOptions?: readonly PerPageOption[];
    }>(),
    {
        perPageOptions: () => [10, 20, 50, 100] as const,
    },
);

const emit = defineEmits<{
    'page-change': [page: number];
    'per-page-change': [perPage: PerPageOption];
}>();

const pageItems = computed<PageItem[]>(() => {
    const last = props.meta.lastPage;
    const current = props.meta.currentPage;

    if (last <= 7) {
        return Array.from({ length: last }, (_, i) => i + 1);
    }

    const items: PageItem[] = [1];
    const windowStart = Math.max(2, current - 1);
    const windowEnd = Math.min(last - 1, current + 1);

    if (windowStart > 2) {
        items.push('ellipsis');
    }

    for (let i = windowStart; i <= windowEnd; i++) {
        items.push(i);
    }

    if (windowEnd < last - 1) {
        items.push('ellipsis');
    }

    items.push(last);

    return items;
});

const canGoPrev = computed<boolean>(() => props.meta.currentPage > 1);
const canGoNext = computed<boolean>(
    () => props.meta.currentPage < props.meta.lastPage,
);

const rangeLabel = computed<string>(() => {
    if (props.meta.total === 0) {
        return 'Aucun résultat';
    }

    const from = props.meta.from ?? 0;
    const to = props.meta.to ?? 0;

    return `Affichage ${from}–${to} sur ${props.meta.total}`;
});

function goPrev(): void {
    if (canGoPrev.value) {
        emit('page-change', props.meta.currentPage - 1);
    }
}

function goNext(): void {
    if (canGoNext.value) {
        emit('page-change', props.meta.currentPage + 1);
    }
}

function goPage(page: number): void {
    if (page !== props.meta.currentPage) {
        emit('page-change', page);
    }
}

function onPerPageChange(event: Event): void {
    const value = Number((event.target as HTMLSelectElement).value);

    if (props.perPageOptions.includes(value as PerPageOption)) {
        emit('per-page-change', value as PerPageOption);
    }
}
</script>

<template>
    <div
        class="flex flex-wrap items-center justify-between gap-3 text-sm text-slate-600"
    >
        <p class="text-xs">
            {{ rangeLabel }}
        </p>

        <nav
            v-if="meta.lastPage > 1"
            class="flex items-center gap-1"
            aria-label="Pagination"
        >
            <button
                type="button"
                :disabled="!canGoPrev"
                :class="[
                    'inline-flex h-8 w-8 items-center justify-center rounded-md border transition-colors duration-[120ms] ease-out',
                    canGoPrev
                        ? 'cursor-pointer border-slate-200 bg-white text-slate-700 hover:border-slate-300 hover:bg-slate-50'
                        : 'cursor-not-allowed border-slate-100 bg-slate-50 text-slate-300',
                ]"
                aria-label="Page précédente"
                @click="goPrev"
            >
                <ChevronLeft :size="14" :stroke-width="1.75" />
            </button>

            <template v-for="(item, index) in pageItems" :key="`p-${index}`">
                <span
                    v-if="item === 'ellipsis'"
                    class="inline-flex h-8 w-8 items-center justify-center text-slate-400"
                    aria-hidden="true"
                >
                    …
                </span>
                <button
                    v-else
                    type="button"
                    :class="[
                        'inline-flex h-8 min-w-8 items-center justify-center rounded-md border px-2 text-sm transition-colors duration-[120ms] ease-out',
                        item === meta.currentPage
                            ? 'cursor-default border-blue-600 bg-blue-600 font-semibold text-white'
                            : 'cursor-pointer border-slate-200 bg-white text-slate-700 hover:border-slate-300 hover:bg-slate-50',
                    ]"
                    :aria-current="item === meta.currentPage ? 'page' : undefined"
                    :aria-label="`Page ${item}`"
                    @click="goPage(item)"
                >
                    {{ item }}
                </button>
            </template>

            <button
                type="button"
                :disabled="!canGoNext"
                :class="[
                    'inline-flex h-8 w-8 items-center justify-center rounded-md border transition-colors duration-[120ms] ease-out',
                    canGoNext
                        ? 'cursor-pointer border-slate-200 bg-white text-slate-700 hover:border-slate-300 hover:bg-slate-50'
                        : 'cursor-not-allowed border-slate-100 bg-slate-50 text-slate-300',
                ]"
                aria-label="Page suivante"
                @click="goNext"
            >
                <ChevronRight :size="14" :stroke-width="1.75" />
            </button>
        </nav>

        <label class="flex items-center gap-2 text-xs">
            <span class="text-slate-500">Lignes par page</span>
            <select
                :value="meta.perPage"
                class="cursor-pointer rounded-md border border-slate-200 bg-white px-2 py-1 text-sm text-slate-900 transition-colors duration-[120ms] ease-out hover:border-slate-300 focus:outline-none focus-visible:border-slate-400 focus-visible:shadow-[0_0_0_3px_var(--color-slate-100)]"
                aria-label="Nombre de lignes par page"
                @change="onPerPageChange"
            >
                <option
                    v-for="option in perPageOptions"
                    :key="option"
                    :value="option"
                >
                    {{ option }}
                </option>
            </select>
        </label>
    </div>
</template>
