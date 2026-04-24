<script setup lang="ts" generic="T extends Record<string, unknown>">
import type {
    DataTableColumn,
    DataTableColumnAlign,
} from '@/types/ui';
import { useSlots } from 'vue';

withDefaults(
    defineProps<{
        columns: readonly DataTableColumn<T>[];
        rows: readonly T[];
        rowKey: (row: T) => string | number;
        sticky?: boolean;
        dense?: boolean;
    }>(),
    {
        sticky: false,
        dense: false,
    },
);

const emit = defineEmits<{
    'row-click': [row: T];
}>();

const slots = useSlots();

const cellValue = (row: T, column: DataTableColumn<T>): unknown => {
    if (column.accessor) return column.accessor(row);
    return row[column.key];
};

const alignClass = (align: DataTableColumnAlign | undefined): string => {
    switch (align) {
        case 'right':
            return 'text-right';
        case 'center':
            return 'text-center';
        default:
            return 'text-left';
    }
};
</script>

<template>
    <div
        class="overflow-hidden rounded-xl border border-slate-200 bg-white"
    >
        <div class="overflow-x-auto">
            <table class="w-full border-collapse text-base">
                <thead>
                    <tr
                        :class="[
                            'bg-slate-50/60',
                            sticky && 'sticky top-0 z-10',
                        ]"
                    >
                        <th
                            v-for="column in columns"
                            :key="column.key"
                            :style="column.width ? { width: column.width } : {}"
                            :class="[
                                'border-b border-slate-200 px-[18px] py-2.5 text-xs font-semibold tracking-wider uppercase text-slate-500',
                                alignClass(column.align),
                            ]"
                            scope="col"
                        >
                            {{ column.label }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="rows.length === 0">
                        <td
                            :colspan="columns.length"
                            class="px-[18px] py-8 text-center text-sm text-slate-400"
                        >
                            <slot name="empty">
                                Aucune donnée à afficher.
                            </slot>
                        </td>
                    </tr>
                    <tr
                        v-for="row in rows"
                        :key="rowKey(row)"
                        :class="[
                            'border-b border-slate-100 last:border-b-0 transition-colors duration-[120ms] ease-out',
                            slots['row-actions'] || $attrs.onRowClick
                                ? 'cursor-pointer hover:bg-slate-50'
                                : '',
                        ]"
                        @click="emit('row-click', row)"
                    >
                        <td
                            v-for="column in columns"
                            :key="column.key"
                            :class="[
                                'border-slate-100 px-[18px] text-slate-700',
                                dense ? 'py-2' : 'py-2.5',
                                alignClass(column.align),
                                column.mono && 'font-mono tabular-nums',
                            ]"
                        >
                            <slot
                                :name="`cell-${column.key}`"
                                :row="row"
                                :value="cellValue(row, column)"
                            >
                                {{ cellValue(row, column) }}
                            </slot>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
