<script setup lang="ts">
type FlatBrackets = {
    header: [string, string];
    rows: { category: string; amount: string; note?: string }[];
};

defineProps<{
    brackets: FlatBrackets;
}>();
</script>

<template>
    <div class="mt-4">
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
