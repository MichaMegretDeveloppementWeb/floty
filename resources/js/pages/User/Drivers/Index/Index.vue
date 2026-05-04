<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Plus, UserPlus } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import UserLayout from '@/Components/Layouts/UserLayout.vue';
import Button from '@/Components/Ui/Button/Button.vue';
import FieldLabel from '@/Components/Ui/FieldLabel/FieldLabel.vue';
import Paginator from '@/Components/Ui/Paginator/Paginator.vue';
import SearchableSelect from '@/Components/Ui/SearchableSelect/SearchableSelect.vue';
import SearchInput from '@/Components/Ui/SearchInput/SearchInput.vue';
import SelectInput from '@/Components/Ui/SelectInput/SelectInput.vue';
import FilterPopover from '@/Components/Ui/Table/FilterPopover.vue';
import { useDriversTable } from '@/Composables/Driver/Index/useDriversTable';
import { create as createRoute } from '@/routes/user/drivers';
import DriversTable from './partials/DriversTable.vue';

type CompanyOption = {
    id: number;
    shortCode: string;
    legalName: string;
};

const props = defineProps<{
    drivers: App.Data.User.Driver.PaginatedDriverListData;
    options: {
        companies: CompanyOption[];
    };
    query: App.Data.User.Driver.DriverIndexQueryData;
}>();

const tableState = useDriversTable(props.query);
const filtersOpen = ref<boolean>(false);

const searchModel = computed<string>({
    get: () => tableState.state.search.value,
    set: (value: string) => {
        tableState.state.search.value = value;
    },
});

const companySelectOptions = computed(() =>
    props.options.companies.map((c) => ({
        value: c.id,
        label: `${c.shortCode} · ${c.legalName}`,
    })),
);

const companyIdModel = computed<number | null>({
    get: () => tableState.state.filters.value.companyId,
    set: (value: string | number | null) => {
        tableState.state.setFilter(
            'companyId',
            typeof value === 'number' ? value : null,
        );
    },
});

const activityStatusOptions = [
    { value: 'active', label: 'Actuellement actif' },
    { value: 'inactive', label: 'Inactif partout' },
];

const activityStatusModel = computed<string | number>({
    get: () => tableState.state.filters.value.activityStatus ?? '',
    set: (value: string | number) => {
        const v = String(value);
        tableState.state.setFilter(
            'activityStatus',
            v === 'active' || v === 'inactive' ? v : null,
        );
    },
});

const contractsScopeOptions = [
    { value: 'with', label: 'Avec contrats' },
    { value: 'without', label: 'Sans contrats' },
];

const contractsScopeModel = computed<string | number>({
    get: () => tableState.state.filters.value.contractsScope ?? '',
    set: (value: string | number) => {
        const v = String(value);
        tableState.state.setFilter(
            'contractsScope',
            v === 'with' || v === 'without' ? v : null,
        );
    },
});
</script>

<template>
    <Head title="Conducteurs" />

    <UserLayout>
        <div class="flex flex-col gap-6">
            <div
                class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"
            >
                <div class="flex flex-col gap-1">
                    <p
                        class="text-xs font-medium tracking-wider text-slate-500 uppercase"
                    >
                        Données
                    </p>
                    <h1 class="text-2xl font-bold text-slate-900">
                        Conducteurs
                    </h1>
                    <p class="text-sm text-slate-500">
                        {{ drivers.meta.total }} conducteur{{
                            drivers.meta.total > 1 ? 's' : ''
                        }}
                        au total
                    </p>
                </div>
                <Link :href="createRoute().url">
                    <Button>
                        <template #icon-left>
                            <Plus :size="14" :stroke-width="1.75" />
                        </template>
                        Ajouter un conducteur
                    </Button>
                </Link>
            </div>

            <div
                v-if="
                    drivers.meta.total === 0 &&
                    searchModel === '' &&
                    tableState.activeFiltersCount.value === 0
                "
                class="flex flex-col items-center gap-3 rounded-xl border border-dashed border-slate-200 bg-white px-6 py-16 text-center"
            >
                <span
                    class="inline-flex h-12 w-12 items-center justify-center rounded-full bg-blue-50 text-blue-600"
                >
                    <UserPlus :size="22" :stroke-width="1.75" />
                </span>
                <p class="text-base font-semibold text-slate-900">
                    Aucun conducteur
                </p>
                <p class="max-w-sm text-sm text-slate-500">
                    Commencez par créer votre premier conducteur. Vous pourrez
                    ensuite l'affecter à un ou plusieurs contrats.
                </p>
                <Link :href="createRoute().url" class="mt-2">
                    <Button>Créer un conducteur</Button>
                </Link>
            </div>

            <template v-else>
                <div class="flex flex-wrap items-center gap-3">
                    <div class="grow max-w-md">
                        <SearchInput
                            v-model="searchModel"
                            placeholder="Rechercher un conducteur (nom ou prénom)"
                            aria-label="Rechercher un conducteur"
                        />
                    </div>
                    <FilterPopover
                        v-model:open="filtersOpen"
                        :active-count="tableState.activeFiltersCount.value"
                        @reset="tableState.state.clearFilters"
                    >
                        <div class="flex flex-col gap-3">
                            <div>
                                <FieldLabel for="filter-company"
                                    >Entreprise</FieldLabel
                                >
                                <SearchableSelect
                                    id="filter-company"
                                    v-model="companyIdModel"
                                    placeholder="Toutes les entreprises"
                                    :options="companySelectOptions"
                                />
                            </div>
                            <div>
                                <FieldLabel for="filter-activity"
                                    >Statut</FieldLabel
                                >
                                <SelectInput
                                    id="filter-activity"
                                    v-model="activityStatusModel"
                                    placeholder="Tous"
                                    :options="activityStatusOptions"
                                    nullable
                                />
                            </div>
                            <div>
                                <FieldLabel for="filter-contracts"
                                    >Contrats</FieldLabel
                                >
                                <SelectInput
                                    id="filter-contracts"
                                    v-model="contractsScopeModel"
                                    placeholder="Tous"
                                    :options="contractsScopeOptions"
                                    nullable
                                />
                            </div>
                        </div>
                    </FilterPopover>
                </div>

                <DriversTable
                    :drivers="drivers.data"
                    :columns="tableState.columns"
                    :active-sort-column-key="
                        tableState.activeSortColumnKey.value
                    "
                    :sort-direction="tableState.state.sort.value.direction"
                    @header-click="tableState.onHeaderClick"
                    @row-click="tableState.onRowClick"
                />

                <Paginator
                    :meta="drivers.meta"
                    @page-change="tableState.state.setPage"
                    @per-page-change="tableState.state.setPerPage"
                />
            </template>
        </div>
    </UserLayout>
</template>
