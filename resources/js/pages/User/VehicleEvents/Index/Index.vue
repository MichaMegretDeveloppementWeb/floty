<script setup lang="ts">
/**
 * Global vehicle-events index (all vehicles). Server-side filtered (nature
 * multi-value, year), sorted and paginated, with the total COST of the
 * filtered set. Each row links to the event detail. Cf. plan « coûts
 * d'événements ».
 */
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import UserLayout from '@/Components/Layouts/UserLayout.vue';
import Badge from '@/Components/Ui/Badge/Badge.vue';
import DataTable from '@/Components/Ui/DataTable/DataTable.vue';
import InlineYearSelector from '@/Components/Ui/InlineYearSelector/InlineYearSelector.vue';
import MultiSelectFilter from '@/Components/Ui/MultiSelectFilter/MultiSelectFilter.vue';
import Paginator from '@/Components/Ui/Paginator/Paginator.vue';
import SearchInput from '@/Components/Ui/SearchInput/SearchInput.vue';
import FilterChips from '@/Components/Ui/Table/FilterChips.vue';
import FilterPopover from '@/Components/Ui/Table/FilterPopover.vue';
import SortableHeader from '@/Components/Ui/Table/SortableHeader.vue';
import { useVehicleEventsTable } from '@/Composables/VehicleEvent/Index/useVehicleEventsTable';
import { formatDateFr } from '@/Utils/format/formatDateFr';
import { formatEur } from '@/Utils/format/formatEur';

type VehicleEventRow = App.Data.User.VehicleEvent.VehicleEventListItemData;

const props = defineProps<{
    events: App.Data.User.VehicleEvent.PaginatedVehicleEventListData;
    totalAmountCents: number;
    query: App.Data.User.VehicleEvent.VehicleEventIndexQueryData;
    hasAnyVehicleEvent: boolean;
    options: {
        natureValues: string[];
        availableYears: number[];
    };
}>();

const tableState = useVehicleEventsTable({ query: props.query });
const filtersOpen = ref<boolean>(false);

const natureOptions = computed(() =>
    props.options.natureValues.map((c) => ({ value: c, label: c })),
);

const yearOptions = computed(() => [
    { value: 0, label: 'Toutes les années' },
    ...props.options.availableYears.map((y) => ({ value: y, label: String(y) })),
]);

const categoriesModel = computed<string[]>({
    get: () => tableState.state.filters.value.categories,
    set: (value) => tableState.state.setFilter('categories', value),
});

const yearModel = computed<number>({
    get: () => tableState.state.filters.value.year ?? 0,
    set: (value) => tableState.state.setFilter('year', value === 0 ? null : value),
});

const searchModel = computed<string>({
    get: () => tableState.state.search.value,
    set: (value) => {
        tableState.state.search.value = value;
    },
});

const totalLabel = computed<string>(() => formatEur(props.totalAmountCents / 100, 2));

function amountLabel(row: VehicleEventRow): string | null {
    return row.amountCents !== null ? formatEur(row.amountCents / 100, 2) : null;
}

function durationLabel(row: VehicleEventRow): string {
    return row.endDate === null
        ? 'En cours'
        : `${row.daysCount} jour${row.daysCount > 1 ? 's' : ''}`;
}
</script>

<template>
    <Head title="Événements" />

    <UserLayout>
        <div class="flex flex-col gap-6">
            <header>
                <p class="eyebrow mb-1">Données · Flotte</p>
                <h1 class="text-2xl font-semibold tracking-tight text-slate-900 md:text-3xl">
                    Événements
                </h1>
                <p class="mt-1 text-sm text-slate-500">
                    Tous les événements de la flotte, filtrables par nature et année,
                    avec leur coût.
                </p>
            </header>

            <p
                v-if="!props.hasAnyVehicleEvent"
                class="rounded-xl border border-slate-200 bg-white px-6 py-10 text-center text-sm text-slate-500 italic"
            >
                Aucun événement enregistré pour le moment.
            </p>

            <template v-else>
                <!-- Total des coûts du jeu filtré · KPI mis en avant -->
                <div class="flex flex-wrap items-stretch gap-px overflow-hidden rounded-xl border border-slate-200 bg-slate-200">
                    <div class="flex-1 bg-white px-5 py-4">
                        <p class="eyebrow mb-1">Total des coûts</p>
                        <p class="font-mono text-[26px] leading-none font-semibold text-slate-900">
                            {{ totalLabel }}
                        </p>
                    </div>
                    <div class="flex-1 bg-white px-5 py-4">
                        <p class="eyebrow mb-1">Événements</p>
                        <p class="text-[26px] leading-none font-semibold text-slate-900">
                            {{ events.meta.total }}
                        </p>
                    </div>
                </div>

                <!-- Barre de filtres · search + popover filtres + année -->
                <div class="flex flex-wrap items-center gap-3">
                    <div class="max-w-md grow">
                        <SearchInput
                            v-model="searchModel"
                            placeholder="Rechercher (intitulé, description, immatriculation)"
                            aria-label="Rechercher un événement"
                        />
                    </div>

                    <FilterPopover
                        v-model:open="filtersOpen"
                        :active-count="tableState.activeFiltersCount.value"
                        @reset="tableState.state.clearFilters"
                    >
                        <MultiSelectFilter
                            v-model="categoriesModel"
                            label="Nature"
                            :options="natureOptions"
                            allow-free-entry
                            placeholder="Filtrer par nature"
                        />
                    </FilterPopover>

                    <div class="ml-auto flex items-center gap-2">
                        <span class="text-sm font-medium text-slate-500">Année</span>
                        <InlineYearSelector
                            id="events-year"
                            v-model="yearModel"
                            :options="yearOptions"
                        />
                    </div>
                </div>

                <FilterChips
                    :chips="tableState.activeFilterChips.value"
                    @remove="tableState.removeFilterChip"
                />

                <DataTable
                    :columns="tableState.columns"
                    :rows="events.data"
                    :row-key="(row) => row.id"
                    clickable
                    @row-click="tableState.onRowClick"
                >
                    <template #header-vehicleLicensePlate="{ column }">
                        <SortableHeader
                            :label="column.label"
                            sort-key="vehicleLicensePlate"
                            :active-key="tableState.activeSortColumnKey.value"
                            :direction="tableState.state.sort.value.direction"
                            @click="tableState.onHeaderClick('vehicleLicensePlate')"
                        />
                    </template>
                    <template #header-startDate="{ column }">
                        <SortableHeader
                            :label="column.label"
                            sort-key="startDate"
                            :active-key="tableState.activeSortColumnKey.value"
                            :direction="tableState.state.sort.value.direction"
                            @click="tableState.onHeaderClick('startDate')"
                        />
                    </template>
                    <template #header-title="{ column }">
                        <SortableHeader
                            :label="column.label"
                            sort-key="title"
                            :active-key="tableState.activeSortColumnKey.value"
                            :direction="tableState.state.sort.value.direction"
                            @click="tableState.onHeaderClick('title')"
                        />
                    </template>
                    <template #header-amount="{ column }">
                        <SortableHeader
                            :label="column.label"
                            sort-key="amount"
                            align="right"
                            :active-key="tableState.activeSortColumnKey.value"
                            :direction="tableState.state.sort.value.direction"
                            @click="tableState.onHeaderClick('amount')"
                        />
                    </template>

                    <template #cell-startDate="{ row }">
                        {{ formatDateFr(row.startDate) }}
                    </template>
                    <template #cell-duration="{ row }">
                        <span :class="row.endDate === null ? 'text-slate-400 italic' : 'text-slate-600'">
                            {{ durationLabel(row) }}
                        </span>
                    </template>
                    <template #cell-title="{ row }">
                        {{ row.title }}
                    </template>
                    <template #cell-categories="{ row }">
                        <span class="flex flex-wrap gap-1">
                            <Badge
                                v-for="cat in row.categories"
                                :key="cat"
                                tone="slate"
                                :uppercase="false"
                            >
                                {{ cat }}
                            </Badge>
                        </span>
                    </template>
                    <template #cell-amount="{ row }">
                        <span v-if="amountLabel(row) !== null">{{ amountLabel(row) }}</span>
                        <span v-else class="text-slate-300">·</span>
                    </template>

                    <template #empty>
                        Aucun événement ne correspond à ces filtres.
                    </template>
                </DataTable>

                <Paginator
                    :meta="events.meta"
                    @page-change="tableState.state.setPage"
                    @per-page-change="tableState.state.setPerPage"
                />
            </template>
        </div>
    </UserLayout>
</template>
