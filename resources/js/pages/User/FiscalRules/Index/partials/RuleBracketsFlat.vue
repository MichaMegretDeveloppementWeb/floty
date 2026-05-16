<script setup lang="ts">
type FlatBrackets = App.Data.User.Fiscal.Pedagogical.FlatBracketsTableData;

defineProps<{
    brackets: FlatBrackets;
}>();
</script>

<template>
    <div class="mt-6">
        <table class="w-full overflow-hidden rounded-lg border border-slate-200 text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-3 py-2 text-left text-xs font-medium text-slate-500">
                        {{ brackets.header[0] }}
                    </th>
                    <th class="px-3 py-2 text-right text-xs font-medium text-slate-500">
                        {{ brackets.header[1] }}
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="row in brackets.rows"
                    :key="row.category"
                    class="border-t border-slate-100"
                >
                    <td class="px-3 py-2">
                        <span class="text-slate-700">{{ row.category }}</span>
                        <span
                            v-if="row.note"
                            class="mt-0.5 block text-xs text-slate-500"
                        >
                            {{ row.note }}
                        </span>
                    </td>
                    <td
                        class="px-3 py-2 text-right align-top font-mono font-medium text-slate-900"
                    >
                        {{ row.amount }}
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
