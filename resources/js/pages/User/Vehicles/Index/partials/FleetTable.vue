<script setup lang="ts">
import { ShieldAlert } from 'lucide-vue-next';
import DataTable from '@/Components/Ui/DataTable/DataTable.vue';
import Plate from '@/Components/Ui/Plate/Plate.vue';
import Skeleton from '@/Components/Ui/Skeleton/Skeleton.vue';
import SortableHeader from '@/Components/Ui/Table/SortableHeader.vue';
import type { DataTableColumn } from '@/types/ui';
import { formatDateFr } from '@/Utils/format/formatDateFr';
import { formatEur } from '@/Utils/format/formatEur';

type VehicleRow = App.Data.User.Vehicle.VehicleListItemData;
type VehicleCostEntry = {
    fullYearTax: number;
    dailyTaxRate: number;
    rentalPriceFullYear: number | null;
};
type VehicleCosts = Record<number, VehicleCostEntry>;
type ControlsBadge = App.Data.User.Control.Vehicle.VehicleControlsBadgeData;
type ControlsBadges = Record<number, ControlsBadge>;

// Server-side sortable columns (mirror of VehicleIndexQueryData::allowedSortKeys()).
// `fullYearTax` and `rentalPriceFullYear` are intentionally excluded
// because they are non-SQL computed values (ADR-0020 D6).
const SORTABLE_COLUMNS: ReadonlySet<string> = new Set([
    'licensePlate',
    'model',
    'firstFrenchRegistrationDate',
]);

const props = defineProps<{
    vehicles: VehicleRow[];
    costs?: VehicleCosts;
    controlsBadges?: ControlsBadges;
    columns: readonly DataTableColumn<VehicleRow>[];
    activeSortColumnKey: string | null;
    sortDirection: 'asc' | 'desc';
}>();

const emit = defineEmits<{
    'header-click': [columnKey: string];
    'row-click': [row: VehicleRow];
}>();

// Cost entry for a row. Only call inside the surrounding
// `v-if="props.costs && props.costs[row.id]"` cells: the entry is
// guaranteed present there, so the assertions are safe.
const costsFor = (row: VehicleRow): VehicleCostEntry => props.costs![row.id]!;

// Sparse control badge for a row (only vehicles with a control needing
// attention are present in the deferred map; absent while it loads).
const badgeFor = (row: VehicleRow): ControlsBadge | undefined => props.controlsBadges?.[row.id];

function badgeTitle(badge: ControlsBadge): string {
    if (badge.overdueCount > 0) {
        const overdue = `${badge.overdueCount} contrôle${badge.overdueCount > 1 ? 's' : ''} en retard`;

        return badge.dueCount > badge.overdueCount
            ? `${badge.dueCount} contrôles à traiter, dont ${overdue}`
            : overdue;
    }

    return `${badge.dueCount} contrôle${badge.dueCount > 1 ? 's' : ''} à échéance`;
}

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
                    <span
                        v-if="badgeFor(row)"
                        :class="[
                            'inline-flex items-center gap-1 rounded-full px-1.5 py-0.5 text-[10px] font-medium tabular-nums',
                            badgeFor(row)!.overdueCount > 0
                                ? 'bg-rose-50 text-rose-700'
                                : 'bg-amber-50 text-amber-700',
                        ]"
                        :title="badgeTitle(badgeFor(row)!)"
                    >
                        <ShieldAlert :size="11" :stroke-width="2" aria-hidden="true" />
                        {{ badgeFor(row)!.dueCount }}
                    </span>
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
            <span class="whitespace-nowrap">{{ formatDateFr(String(value)) }}</span>
        </template>
        <template #cell-fullYearTax="{ row }">
            <div
                v-if="props.costs && props.costs[row.id]"
                class="flex flex-col items-end leading-tight"
            >
                <span class="font-mono font-normal whitespace-nowrap text-slate-900">
                    {{ formatEur(costsFor(row).fullYearTax) }}
                </span>
                <span class="text-xs whitespace-nowrap text-slate-400">
                    {{ formatEur(costsFor(row).dailyTaxRate, 2) }} / jour
                </span>
            </div>
            <div v-else class="flex flex-col items-end gap-1 leading-tight">
                <Skeleton class="h-4 w-20 rounded" />
                <Skeleton class="h-3 w-16 rounded" />
            </div>
        </template>
        <template #cell-rentalPriceFullYear="{ row }">
            <template v-if="props.costs && props.costs[row.id]">
                <span
                    v-if="costsFor(row).rentalPriceFullYear !== null"
                    class="font-mono font-normal whitespace-nowrap text-slate-900"
                >
                    {{ formatEur(costsFor(row).rentalPriceFullYear!) }}
                </span>
                <span v-else class="text-slate-300" title="Tarif annuel non défini pour ce véhicule">
                    ·
                </span>
            </template>
            <Skeleton v-else class="ml-auto h-4 w-20 rounded" />
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
