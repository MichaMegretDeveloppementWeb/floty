<script setup lang="ts">
import UserLayout from '@/Components/Layouts/UserLayout.vue';
import Button from '@/Components/Ui/Button/Button.vue';
import DataTable from '@/Components/Ui/DataTable/DataTable.vue';
import EmptyState from '@/Components/Ui/EmptyState/EmptyState.vue';
import Plate from '@/Components/Ui/Plate/Plate.vue';
import { useFiscalYear } from '@/Composables/Shared/useFiscalYear';
import type { DataTableColumn } from '@/types/ui';
import { Head, Link } from '@inertiajs/vue3';
import { Car, Plus } from 'lucide-vue-next';
import { computed } from 'vue';

type VehicleRow = {
    id: number;
    licensePlate: string;
    brand: string;
    model: string;
    currentStatus: string;
    firstFrenchRegistrationDate: string;
    acquisitionDate: string;
    exitDate: string | null;
    annualTaxDue: number;
};

const props = defineProps<{
    vehicles: VehicleRow[];
}>();

const { currentYear: fiscalYear } = useFiscalYear();

const columns = computed<readonly DataTableColumn<VehicleRow>[]>(() => [
    { key: 'licensePlate', label: 'Immatriculation' },
    { key: 'brand', label: 'Marque' },
    { key: 'model', label: 'Modèle' },
    { key: 'firstFrenchRegistrationDate', label: '1ʳᵉ immat.', mono: true },
    { key: 'annualTaxDue', label: `Taxe ${fiscalYear.value}` },
]);

const statusLabel: Record<string, string> = {
    active: 'Active',
    maintenance: 'Maintenance',
    sold: 'Vendu',
    destroyed: 'Détruit',
    other: 'Autre',
};

const statusDotClass: Record<string, string> = {
    active: 'bg-emerald-500',
    maintenance: 'bg-amber-500',
    sold: 'bg-slate-400',
    destroyed: 'bg-rose-500',
    other: 'bg-slate-400',
};

const formatEur = (value: number): string =>
    new Intl.NumberFormat('fr-FR', {
        style: 'currency',
        currency: 'EUR',
        maximumFractionDigits: 0,
    })
        .format(value)
        .replace(/ | /g, ' ');

const formatDateFr = (iso: string): string => {
    const [y, m, d] = iso.split('-');
    return `${d}/${m}/${y}`;
};
</script>

<template>
    <Head title="Flotte" />

    <UserLayout>
        <div class="flex flex-col gap-6">
            <header class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p class="eyebrow mb-1">Données</p>
                    <h1
                        class="text-2xl font-semibold tracking-tight text-slate-900 md:text-3xl"
                    >
                        Flotte
                    </h1>
                    <p class="mt-1 text-base text-slate-600">
                        Véhicules enregistrés et taxes annuelles {{ fiscalYear }}.
                    </p>
                </div>
                <Link href="/app/vehicles/create">
                    <Button>
                        <template #icon-left>
                            <Plus :size="14" :stroke-width="1.75" />
                        </template>
                        Nouveau véhicule
                    </Button>
                </Link>
            </header>

            <EmptyState
                v-if="props.vehicles.length === 0"
                title="Aucun véhicule enregistré"
                description="Commencez par saisir les véhicules de la flotte partagée."
            >
                <template #icon>
                    <Car :size="20" :stroke-width="1.75" />
                </template>
                <template #actions>
                    <Link href="/app/vehicles/create">
                        <Button>
                            <template #icon-left>
                                <Plus :size="14" :stroke-width="1.75" />
                            </template>
                            Nouveau véhicule
                        </Button>
                    </Link>
                </template>
            </EmptyState>

            <DataTable
                v-else
                :columns="columns"
                :rows="props.vehicles"
                :row-key="(row) => row.id"
            >
                <template #cell-licensePlate="{ row }">
                    <div class="flex items-center gap-2">
                        <span
                            :class="[
                                'inline-block h-2 w-2 shrink-0 rounded-full',
                                statusDotClass[row.currentStatus] ?? 'bg-slate-400',
                            ]"
                            :title="statusLabel[row.currentStatus] ?? row.currentStatus"
                            aria-hidden="true"
                        />
                        <Plate :value="row.licensePlate" />
                    </div>
                </template>
                <template #cell-firstFrenchRegistrationDate="{ value }">
                    {{ formatDateFr(String(value)) }}
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
