<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Plus, UserPlus } from 'lucide-vue-next';
import UserLayout from '@/Components/Layouts/UserLayout.vue';
import Button from '@/Components/Ui/Button/Button.vue';
import Paginator from '@/Components/Ui/Paginator/Paginator.vue';
import SearchInput from '@/Components/Ui/SearchInput/SearchInput.vue';
import { useDriversTable } from '@/Composables/Driver/Index/useDriversTable';
import { create as createRoute } from '@/routes/user/drivers';
import DriversTable from './partials/DriversTable.vue';

const props = defineProps<{
    drivers: App.Data.User.Driver.PaginatedDriverListData;
    query: App.Data.User.Driver.DriverIndexQueryData;
}>();

const tableState = useDriversTable(props.query);

// `state.search` est un Ref<string> — bind direct au v-model du SearchInput.
// Le watch interne du composable déclenche le reload debouncé (300ms).
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

            <div class="max-w-md">
                <SearchInput
                    v-model="tableState.state.search.value"
                    placeholder="Rechercher un conducteur (nom ou prénom)"
                    aria-label="Rechercher un conducteur"
                />
            </div>

            <div
                v-if="
                    drivers.meta.total === 0 &&
                    tableState.state.search.value === ''
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
