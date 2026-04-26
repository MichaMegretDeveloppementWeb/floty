<script setup lang="ts">
import UserLayout from '@/Components/Layouts/UserLayout.vue';
import Button from '@/Components/Ui/Button/Button.vue';
import CompanyTag from '@/Components/Ui/CompanyTag/CompanyTag.vue';
import DataTable from '@/Components/Ui/DataTable/DataTable.vue';
import EmptyState from '@/Components/Ui/EmptyState/EmptyState.vue';
import { useFiscalYear } from '@/Composables/Shared/useFiscalYear';
import type { DataTableColumn } from '@/types/ui';
import { Head, Link } from '@inertiajs/vue3';
import { Building2, Plus } from 'lucide-vue-next';
import { computed } from 'vue';

type CompanyRow = App.Data.User.Company.CompanyListItemData;

const props = defineProps<{
    companies: CompanyRow[];
}>();

const { currentYear: fiscalYear } = useFiscalYear();

const columns = computed<readonly DataTableColumn<CompanyRow>[]>(() => [
    { key: 'company', label: 'Entreprise' },
    { key: 'siren', label: 'SIREN', mono: true },
    { key: 'city', label: 'Ville' },
    { key: 'daysUsed', label: `Jours ${fiscalYear.value}`, mono: true },
    { key: 'annualTaxDue', label: `Taxe ${fiscalYear.value}` },
]);

const formatEur = (value: number): string =>
    new Intl.NumberFormat('fr-FR', {
        style: 'currency',
        currency: 'EUR',
        maximumFractionDigits: 0,
    })
        .format(value)
        .replace(/ | /g, ' ');
</script>

<template>
    <Head title="Entreprises" />

    <UserLayout>
        <div class="flex flex-col gap-6">
            <header class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p class="eyebrow mb-1">Données</p>
                    <h1
                        class="text-2xl font-semibold tracking-tight text-slate-900 md:text-3xl"
                    >
                        Entreprises utilisatrices
                    </h1>
                    <p class="mt-1 text-base text-slate-600">
                        Clients utilisateurs de la flotte et taxes {{ fiscalYear }}.
                    </p>
                </div>
                <Link href="/app/companies/create">
                    <Button>
                        <template #icon-left>
                            <Plus :size="14" :stroke-width="1.75" />
                        </template>
                        Nouvelle entreprise
                    </Button>
                </Link>
            </header>

            <EmptyState
                v-if="props.companies.length === 0"
                title="Aucune entreprise enregistrée"
                description="Ajoutez votre première entreprise utilisatrice pour commencer à créer des attributions."
            >
                <template #icon>
                    <Building2 :size="20" :stroke-width="1.75" />
                </template>
                <template #actions>
                    <Link href="/app/companies/create">
                        <Button>
                            <template #icon-left>
                                <Plus :size="14" :stroke-width="1.75" />
                            </template>
                            Nouvelle entreprise
                        </Button>
                    </Link>
                </template>
            </EmptyState>

            <DataTable
                v-else
                :columns="columns"
                :rows="props.companies"
                :row-key="(row) => row.id"
            >
                <template #cell-company="{ row }">
                    <div class="flex items-center gap-2">
                        <span
                            :class="[
                                'inline-block h-2 w-2 shrink-0 rounded-full',
                                row.isActive
                                    ? 'bg-emerald-500'
                                    : 'bg-slate-400',
                            ]"
                            :title="row.isActive ? 'Active' : 'Inactive'"
                            aria-hidden="true"
                        />
                        <CompanyTag
                            :name="row.legalName"
                            :initials="row.shortCode"
                            :color="row.color"
                        />
                    </div>
                </template>
                <template #cell-siren="{ value }">
                    {{ value ?? '—' }}
                </template>
                <template #cell-city="{ value }">
                    {{ value ?? '—' }}
                </template>
                <template #cell-daysUsed="{ value }">
                    <span class="text-slate-700">{{ value }} j</span>
                </template>
                <template #cell-annualTaxDue="{ value }">
                    <span class="font-mono font-medium text-slate-900">
                        {{ formatEur(Number(value)) }}
                    </span>
                </template>
            </DataTable>
        </div>
    </UserLayout>
</template>
