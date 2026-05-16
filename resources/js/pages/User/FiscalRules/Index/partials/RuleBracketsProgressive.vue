<script setup lang="ts">
type ProgressiveBrackets = App.Data.User.Fiscal.Pedagogical.ProgressiveBracketsTableData;

defineProps<{
    brackets: ProgressiveBrackets;
}>();
</script>

<template>
    <div class="mt-6">
        <p class="eyebrow mb-2">Barème {{ brackets.unit }}</p>
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
                    :key="row.label"
                    class="border-t border-slate-100"
                >
                    <td class="px-3 py-2 text-slate-700">{{ row.label }}</td>
                    <td class="px-3 py-2 text-right font-mono text-slate-900">
                        {{ row.rate }}
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
