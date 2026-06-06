<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { BadgePercent, Plus } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import UserLayout from '@/Components/Layouts/UserLayout.vue';
import Button from '@/Components/Ui/Button/Button.vue';
import FieldLabel from '@/Components/Ui/FieldLabel/FieldLabel.vue';
import Paginator from '@/Components/Ui/Paginator/Paginator.vue';
import SearchableSelect from '@/Components/Ui/SearchableSelect/SearchableSelect.vue';
import SearchInput from '@/Components/Ui/SearchInput/SearchInput.vue';
import SelectInput from '@/Components/Ui/SelectInput/SelectInput.vue';
import FilterPopover from '@/Components/Ui/Table/FilterPopover.vue';
import { useRentalDiscountsTable } from '@/Composables/RentalDiscount/Index/useRentalDiscountsTable';
import { create as createRoute } from '@/routes/user/rental-discounts';
import RentalDiscountsStats from './partials/RentalDiscountsStats.vue';
import RentalDiscountsTable from './partials/RentalDiscountsTable.vue';

type CompanyOption = {
    id: number;
    shortCode: string;
    legalName: string;
    color: string;
};

const props = defineProps<{
    rentalDiscounts: App.Data.User.RentalDiscount.PaginatedRentalDiscountListData;
    options: {
        companies: CompanyOption[];
    };
    stats: { active: number; planned: number; expired: number };
    query: App.Data.User.RentalDiscount.RentalDiscountIndexQueryData;
    hasAnyRentalDiscount: boolean;
}>();

const tableState = useRentalDiscountsTable(props.query);
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

const statusOptions = [
    { value: '', label: 'Tous' },
    { value: 'active', label: 'Active' },
    { value: 'planned', label: 'Planifiée' },
    { value: 'expired', label: 'Expirée' },
];

const statusModel = computed<string | number>({
    get: () => tableState.state.filters.value.status ?? '',
    set: (value: string | number) => {
        const v = String(value);
        tableState.state.setFilter(
            'status',
            v === 'active' || v === 'planned' || v === 'expired' ? v : null,
        );
    },
});
</script>

<template>
    <Head title="Réductions commerciales" />

    <UserLayout>
        <div class="flex flex-col gap-6">
            <header class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p class="eyebrow mb-1">Facturation</p>
                    <h1 class="text-2xl font-semibold tracking-tight text-slate-900 md:text-3xl">
                        Réductions commerciales
                    </h1>
                    <p class="mt-1 text-base text-slate-600">
                        Remises appliquées sur le loyer mensuel par entreprise et par période.
                    </p>
                </div>
                <Link :href="createRoute.url()">
                    <Button>
                        <template #icon-left>
                            <Plus :size="14" :stroke-width="1.75" />
                        </template>
                        Nouvelle réduction
                    </Button>
                </Link>
            </header>

            <div
                v-if="!hasAnyRentalDiscount"
                class="flex flex-col items-center gap-3 rounded-xl border border-dashed border-slate-200 bg-white px-6 py-16 text-center"
            >
                <span class="inline-flex h-12 w-12 items-center justify-center rounded-full bg-emerald-50 text-emerald-600">
                    <BadgePercent :size="22" :stroke-width="1.75" />
                </span>
                <p class="text-base font-semibold text-slate-900">
                    Aucune réduction commerciale
                </p>
                <p class="max-w-sm text-sm text-slate-500">
                    Créez votre première réduction pour appliquer un pourcentage de remise sur les loyers d'une entreprise pendant une période donnée.
                </p>
                <Link :href="createRoute.url()" class="mt-2">
                    <Button>Créer une réduction</Button>
                </Link>
            </div>

            <template v-else>
                <RentalDiscountsStats
                    :total="rentalDiscounts.meta.total"
                    :stats="stats"
                />

                <div class="flex flex-wrap items-center gap-3">
                    <div class="grow max-w-md">
                        <SearchInput
                            v-model="searchModel"
                            placeholder="Rechercher (libellé, entreprise)"
                            aria-label="Rechercher une réduction commerciale"
                        />
                    </div>
                    <FilterPopover
                        v-model:open="filtersOpen"
                        :active-count="tableState.activeFiltersCount.value"
                        @reset="tableState.state.clearFilters"
                    >
                        <div class="flex flex-col gap-3">
                            <div>
                                <FieldLabel for="filter-company">Entreprise</FieldLabel>
                                <SearchableSelect
                                    id="filter-company"
                                    dropdown-in-flow
                                    v-model="companyIdModel"
                                    placeholder="Toutes les entreprises"
                                    :options="companySelectOptions"
                                />
                            </div>
                            <div>
                                <FieldLabel for="filter-status">Statut</FieldLabel>
                                <SelectInput
                                    id="filter-status"
                                    v-model="statusModel"
                                    :options="statusOptions"
                                />
                            </div>
                        </div>
                    </FilterPopover>
                </div>

                <RentalDiscountsTable
                    :rental-discounts="rentalDiscounts.data"
                    :columns="tableState.columns"
                    :active-sort-column-key="tableState.activeSortColumnKey.value"
                    :sort-direction="tableState.state.sort.value.direction"
                    @header-click="tableState.onHeaderClick"
                    @row-click="tableState.onRowClick"
                />

                <Paginator
                    :meta="rentalDiscounts.meta"
                    @page-change="tableState.state.setPage"
                    @per-page-change="tableState.state.setPerPage"
                />
            </template>
        </div>
    </UserLayout>
</template>
