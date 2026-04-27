<script setup lang="ts">
type ProgressiveBrackets = {
    unit: string;
    header: [string, string];
    rows: { label: string; rate: string }[];
};

defineProps<{
    brackets: ProgressiveBrackets;
}>();
</script>

<template>
    <div class="mt-4">
        <p class="eyebrow mb-2">Barème {{ brackets.unit }}</p>
        <table class="w-full overflow-hidden rounded-lg border border-slate-200 text-base">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-3 py-2 text-left font-medium text-slate-600">
                        {{ brackets.header[0] }}
                    </th>
                    <th class="px-3 py-2 text-right font-medium text-slate-600">
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
