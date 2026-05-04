<script setup lang="ts">
import DataTable from '@/Components/Ui/DataTable/DataTable.vue';
import Plate from '@/Components/Ui/Plate/Plate.vue';
import SortableHeader from '@/Components/Ui/Table/SortableHeader.vue';
import type { DataTableColumn } from '@/types/ui';
import { formatDateFr } from '@/Utils/format/formatDateFr';
import { formatEur } from '@/Utils/format/formatEur';

type VehicleRow = App.Data.User.Vehicle.VehicleListItemData;

// Colonnes triables côté serveur (cf. VehicleIndexQueryData::allowedSortKeys()).
// `fullYearTax` et `rentalPriceFullYear` sont volontairement absents
// (valeurs calculées non SQL, règle ADR-0020 D6).
const SORTABLE_COLUMNS: ReadonlySet<string> = new Set([
    'licensePlate',
    'model',
    'firstFrenchRegistrationDate',
]);

defineProps<{
    vehicles: VehicleRow[];
    columns: readonly DataTableColumn<VehicleRow>[];
    activeSortColumnKey: string | null;
    sortDirection: 'asc' | 'desc';
}>();

const emit = defineEmits<{
    'header-click': [columnKey: string];
    'row-click': [row: VehicleRow];
}>();

// Pastille de statut affichée avant l'immatriculation. Code couleur métier :
//   - vert  : véhicule en service
//   - orange : véhicule indisponible temporairement (maintenance)
//   - rouge : sorti définitivement (vendu, détruit ou autre)
function statusPalette(
    status: App.Enums.Vehicle.VehicleStatus,
): { dot: string; label: string } {
    switch (status) {
        case 'active':
            return { dot: 'bg-emerald-500', label: 'Actif' };
        case 'maintenance':
            return { dot: 'bg-amber-500', label: 'En maintenance' };
        default:
            return { dot: 'bg-rose-500', label: 'Sorti de flotte' };
    }
}
</script>

<template>
    <DataTable
        :columns="columns"
        :rows="vehicles"
        :row-key="(row) => row.id"
        clickable
        @row-click="(row) => emit('row-click', row)"
    >
        <template
            v-for="column in columns"
            #[`header-${column.key}`]="{ column: col }"
            :key="column.key"
        >
            <SortableHeader
                v-if="SORTABLE_COLUMNS.has(col.key)"
                :label="col.label"
                :sort-key="col.key"
                :active-key="activeSortColumnKey"
                :direction="sortDirection"
                :align="col.align === 'right' ? 'right' : 'left'"
                @click="emit('header-click', col.key)"
            />
            <span
                v-else
                :class="[
                    'inline-flex w-full text-xs font-semibold tracking-wider text-slate-500 uppercase',
                    col.align === 'right' ? 'justify-end' : 'justify-start',
                ]"
            >
                {{ col.label }}
            </span>
        </template>

        <template #cell-licensePlate="{ row }">
            <div :class="['flex flex-col gap-0.5', row.isExited && 'opacity-60']">
                <div class="flex flex-wrap items-center gap-2">
                    <span
                        :class="[
                            'inline-block size-2.5 shrink-0 rounded-full',
                            statusPalette(row.currentStatus).dot,
                        ]"
                        :title="statusPalette(row.currentStatus).label"
                        :aria-label="statusPalette(row.currentStatus).label"
                    />
                    <Plate :value="row.licensePlate" />
                </div>
                <span
                    v-if="row.isExited && row.exitDate"
                    class="text-xs text-slate-400"
                >
                    Sortie le {{ formatDateFr(row.exitDate) }}
                </span>
            </div>
        </template>
        <template #cell-model="{ row }">
            <span :class="['text-slate-700', row.isExited && 'opacity-60']">
                <span class="font-semibold text-slate-900">
                    {{ row.brand }}
                </span>
                {{ row.model }}
            </span>
        </template>
        <template #cell-firstFrenchRegistrationDate="{ value }">
            {{ formatDateFr(String(value)) }}
        </template>
        <template #cell-fullYearTax="{ row }">
            <div class="flex flex-col items-end leading-tight">
                <span class="font-mono font-normal text-slate-900">
                    {{ formatEur(row.fullYearTax) }}
                </span>
                <span class="text-xs text-slate-400">
                    {{ formatEur(row.dailyTaxRate, 2) }} / jour
                </span>
            </div>
        </template>
        <template #cell-rentalPriceFullYear="{ row }">
            <span
                v-if="row.rentalPriceFullYear !== null"
                class="font-mono font-normal text-slate-900"
            >
                {{ formatEur(row.rentalPriceFullYear) }}
            </span>
            <span v-else class="text-slate-300" title="Module facturation V1.2 — à venir">
                —
            </span>
        </template>

        <template #empty>
            <div class="flex flex-col items-center gap-2 py-8 text-center">
                <p class="text-sm font-medium text-slate-700">
                    Aucun véhicule ne correspond aux filtres
                </p>
                <p class="text-xs text-slate-500">
                    Modifiez votre recherche ou réinitialisez les filtres.
                </p>
            </div>
        </template>
    </DataTable>
</template>
