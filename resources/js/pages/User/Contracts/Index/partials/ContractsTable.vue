<script setup lang="ts">
import { ChevronRight } from 'lucide-vue-next';
import Badge from '@/Components/Ui/Badge/Badge.vue';
import CompanyTag from '@/Components/Ui/CompanyTag/CompanyTag.vue';
import DataTable from '@/Components/Ui/DataTable/DataTable.vue';
import Plate from '@/Components/Ui/Plate/Plate.vue';
import SortableHeader from '@/Components/Ui/Table/SortableHeader.vue';
import type { DataTableColumn } from '@/types/ui';
import { formatDateFr } from '@/Utils/format/formatDateFr';
import { formatEur } from '@/Utils/format/formatEur';
import type {
    contractTypeBadgeTone,
    contractTypeShortLabel,
} from '@/Utils/labels/contractEnumLabels';

type ContractRow = App.Data.User.Contract.ContractListItemData;

/** Costs map indexed by contractId, served via Inertia::defer. */
type ContractCosts = Record<number, { totalTax: number; rentalPrice: number | null }>;

const props = defineProps<{
    contracts: ContractRow[];
    costs?: ContractCosts;
    columns: readonly DataTableColumn<ContractRow>[];
    activeSortColumnKey: string | null;
    sortDirection: 'asc' | 'desc';
    badgeTone: typeof contractTypeBadgeTone;
    shortLabel: typeof contractTypeShortLabel;
}>();

const emit = defineEmits<{
    'header-click': [columnKey: string];
    'row-click': [row: ContractRow];
}>();

function isCostsLoadedFor(row: ContractRow): boolean {
    return props.costs?.[row.id] !== undefined;
}

function totalTaxFor(row: ContractRow): number | null {
    return props.costs?.[row.id]?.totalTax ?? null;
}

function rentalPriceFor(row: ContractRow): number | null {
    return props.costs?.[row.id]?.rentalPrice ?? null;
}
</script>

<template>
    <DataTable
        class="hidden md:block"
        :columns="columns"
        :rows="contracts"
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
                :label="col.label"
                :sort-key="col.key"
                :active-key="activeSortColumnKey ?? ''"
                :direction="sortDirection"
                :align="col.align === 'right' ? 'right' : 'left'"
                @click="emit('header-click', col.key)"
            />
        </template>

        <template #cell-vehicleLicensePlate="{ row }">
            <div
                :class="[
                    'flex flex-col items-start gap-0.5',
                    row.vehicleIsExited && 'opacity-60',
                ]"
            >
                <Plate :value="row.vehicleLicensePlate" />
                <span
                    v-if="row.vehicleIsExited"
                    class="text-[10px] italic text-slate-400"
                >
                    Véhicule retiré
                </span>
            </div>
        </template>
        <template #cell-companyShortCode="{ row }">
            <div class="flex flex-col items-start gap-1">
                <CompanyTag
                    :name="row.companyLegalName"
                    :initials="row.companyShortCode"
                    :color="row.companyColor"
                />
                <span
                    v-if="row.drivers.length > 0"
                    class="text-xs text-slate-500"
                >
                    {{ row.drivers.map((d) => d.fullName).join(', ') }}
                </span>
            </div>
        </template>
        <template #cell-startDate="{ value }">
            <span class="whitespace-nowrap">{{ formatDateFr(String(value)) }}</span>
        </template>
        <template #cell-endDate="{ value }">
            <span class="whitespace-nowrap">{{ formatDateFr(String(value)) }}</span>
        </template>
        <template #cell-durationDays="{ value }">
            <span class="whitespace-nowrap">{{ value }} j</span>
        </template>
        <template #cell-contractType="{ row }">
            <Badge :tone="badgeTone[row.contractType]">
                {{ shortLabel[row.contractType] }}
            </Badge>
        </template>
        <template #cell-totalTax="{ row }">
            <span
                v-if="isCostsLoadedFor(row)"
                class="font-mono whitespace-nowrap tabular-nums text-slate-700"
            >
                {{ formatEur(totalTaxFor(row) ?? 0) }}
            </span>
            <span
                v-else
                class="skeleton-shimmer inline-block h-3 w-14 rounded"
                aria-label="Calcul en cours"
            ></span>
        </template>
        <template #cell-rentalPrice="{ row }">
            <span
                v-if="isCostsLoadedFor(row) && rentalPriceFor(row) !== null"
                class="font-mono whitespace-nowrap tabular-nums text-slate-900"
            >
                {{ formatEur(rentalPriceFor(row)!) }}
            </span>
            <span
                v-else-if="isCostsLoadedFor(row)"
                class="text-slate-300"
                title="Tarif annuel non défini"
            >
                ·
            </span>
            <span
                v-else
                class="skeleton-shimmer inline-block h-3 w-14 rounded"
                aria-label="Calcul en cours"
            ></span>
        </template>
    </DataTable>

    <ul class="flex flex-col gap-2 md:hidden">
        <li v-for="row in contracts" :key="row.id">
            <button
                type="button"
                :class="[
                    'flex w-full cursor-pointer items-center gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3 text-left transition-colors duration-[120ms] ease-out hover:border-slate-300 hover:bg-slate-50',
                    row.vehicleIsExited && 'opacity-60',
                ]"
                @click="emit('row-click', row)"
            >
                <div class="flex min-w-0 flex-1 flex-col gap-2">
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex flex-col items-start gap-0.5">
                            <Plate :value="row.vehicleLicensePlate" />
                            <span
                                v-if="row.vehicleIsExited"
                                class="text-[10px] italic text-slate-400"
                            >
                                Véhicule retiré
                            </span>
                        </div>
                        <Badge :tone="badgeTone[row.contractType]">
                            {{ shortLabel[row.contractType] }}
                        </Badge>
                    </div>
                    <CompanyTag
                        :name="row.companyLegalName"
                        :initials="row.companyShortCode"
                        :color="row.companyColor"
                    />
                    <p class="text-xs text-slate-500">
                        {{ formatDateFr(row.startDate) }}
                        <span class="mx-1 text-slate-300">→</span>
                        {{ formatDateFr(row.endDate) }}
                        <span class="mx-1 text-slate-300">·</span>
                        {{ row.durationDays }} j
                    </p>
                </div>
                <ChevronRight
                    :size="16"
                    :stroke-width="1.75"
                    class="shrink-0 text-slate-400"
                    aria-hidden="true"
                />
            </button>
        </li>
    </ul>
</template>
