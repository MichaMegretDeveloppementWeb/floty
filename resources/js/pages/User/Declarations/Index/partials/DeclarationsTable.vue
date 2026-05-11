<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronRight } from 'lucide-vue-next';
import DataTable from '@/Components/Ui/DataTable/DataTable.vue';
import StatusPill from '@/Components/Ui/StatusPill/StatusPill.vue';
import SortableHeader from '@/Components/Ui/Table/SortableHeader.vue';
import { show as declarationsShowRoute } from '@/routes/user/declarations';
import type { DataTableColumn } from '@/types/ui';
import { pillForIndexRow, subMentionForRow } from '@/Utils/format/declarationStatus';
import { formatDateFr } from '@/Utils/format/formatDateFr';

type DeclarationRow = App.Data.User.FiscalDeclaration.DeclarationListItemData;

defineProps<{
    declarations: DeclarationRow[];
    columns: readonly DataTableColumn<DeclarationRow>[];
    activeSortColumnKey: string | null;
    sortDirection: 'asc' | 'desc';
}>();

const emit = defineEmits<{
    'header-click': [columnKey: string];
    'row-click': [row: DeclarationRow];
}>();
</script>

<template>
    <DataTable
        class="hidden md:block"
        :columns="columns"
        :rows="declarations"
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
                :active-key="activeSortColumnKey"
                :direction="sortDirection"
                :align="col.align === 'right' ? 'right' : col.align === 'center' ? 'center' : 'left'"
                @click="emit('header-click', col.key)"
            />
        </template>

        <template #cell-companyShortCode="{ row }">
            <div class="flex flex-col">
                <span class="font-medium whitespace-nowrap text-slate-900">{{ row.companyShortCode }}</span>
                <span class="text-xs text-slate-500">{{ row.companyLegalName }}</span>
            </div>
        </template>

        <template #cell-fiscalYear="{ row }">
            <span class="font-mono tabular-nums">{{ row.fiscalYear }}</span>
        </template>

        <template #cell-reference="{ row }">
            <span v-if="row.reference" class="font-mono text-xs whitespace-nowrap text-slate-700">
                {{ row.reference }}
            </span>
            <span v-else class="text-slate-300">·</span>
        </template>

        <template #cell-status="{ row }">
            <div class="flex flex-col items-start gap-1">
                <StatusPill :tone="pillForIndexRow(row).tone">
                    {{ pillForIndexRow(row).label }}
                </StatusPill>
                <Link
                    v-if="subMentionForRow(row)"
                    :href="declarationsShowRoute.url({ declaration: subMentionForRow(row)!.targetDeclarationId })"
                    class="cursor-pointer text-xs text-slate-500 transition-colors duration-[120ms] hover:text-slate-800 hover:underline"
                    @click.stop
                >
                    {{ subMentionForRow(row)!.text }}
                </Link>
            </div>
        </template>

        <template #cell-isObsolete="{ row }">
            <StatusPill v-if="row.isObsolete" tone="rose">Obsolète</StatusPill>
            <span
                v-else
                class="cursor-help text-slate-300"
                title="Déclaration à jour · périmètre contractuel inchangé depuis la génération"
            >·</span>
        </template>

        <template #cell-generatedAt="{ row }">
            <span v-if="row.generatedAt" class="font-mono text-sm whitespace-nowrap text-slate-600">
                {{ formatDateFr(row.generatedAt) }}
            </span>
            <span v-else class="text-slate-400">·</span>
        </template>

        <template #empty>
            <div class="flex flex-col items-center gap-2 py-8 text-center">
                <p class="text-sm font-medium text-slate-700">
                    Aucune déclaration ne correspond à votre recherche.
                </p>
                <p class="text-xs text-slate-500">
                    Essayez d'élargir les filtres ou préparez une nouvelle déclaration depuis la fiche entreprise.
                </p>
            </div>
        </template>
    </DataTable>

    <!-- Mobile < md -->
    <ul v-if="declarations.length > 0" class="flex flex-col gap-2 md:hidden">
        <li v-for="row in declarations" :key="row.id">
            <button
                type="button"
                class="flex w-full cursor-pointer items-center gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3 text-left transition-colors duration-[120ms] ease-out hover:border-slate-300 hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-100"
                @click="emit('row-click', row)"
            >
                <div class="flex min-w-0 flex-1 flex-col gap-1.5">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="font-medium text-slate-900">{{ row.companyShortCode }}</span>
                        <span class="font-mono text-xs text-slate-500">· {{ row.fiscalYear }}</span>
                        <StatusPill v-if="row.isObsolete" tone="rose">Obsolète</StatusPill>
                        <StatusPill :tone="pillForIndexRow(row).tone">
                            {{ pillForIndexRow(row).label }}
                        </StatusPill>
                    </div>
                    <span class="text-xs text-slate-500">{{ row.companyLegalName }}</span>
                    <p v-if="row.reference" class="font-mono text-[11px] text-slate-500">
                        {{ row.reference }}
                    </p>
                    <p
                        v-if="subMentionForRow(row)"
                        class="text-[11px] text-slate-500"
                    >
                        {{ subMentionForRow(row)!.text }}
                    </p>
                    <p v-if="row.generatedAt" class="font-mono text-[11px] text-slate-400">
                        Générée le {{ formatDateFr(row.generatedAt) }}
                    </p>
                </div>
                <ChevronRight :size="16" :stroke-width="1.75" class="shrink-0 text-slate-400" />
            </button>
        </li>
    </ul>
    <div
        v-else
        class="flex flex-col items-center gap-2 rounded-xl border border-slate-200 bg-white py-8 text-center md:hidden"
    >
        <p class="text-sm font-medium text-slate-700">
            Aucune déclaration ne correspond à votre recherche.
        </p>
        <p class="text-xs text-slate-500">
            Essayez d'élargir les filtres.
        </p>
    </div>
</template>
