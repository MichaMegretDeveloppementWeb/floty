<script setup lang="ts">
import type { VehicleTabKey } from '@/Composables/Vehicle/Show/useVehicleTabs';

defineProps<{
    activeTab: VehicleTabKey;
}>();

defineEmits<{
    change: [tab: VehicleTabKey];
}>();

const tabs: readonly { key: VehicleTabKey; label: string }[] = [
    { key: 'overview', label: 'Vue d\'ensemble' },
    { key: 'fiscal', label: 'Fiscalité' },
    { key: 'billing', label: 'Facturation' },
] as const;
</script>

<template>
    <div
        class="flex gap-1 overflow-x-auto border-b border-slate-200 [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
        role="tablist"
    >
        <button
            v-for="tab in tabs"
            :key="tab.key"
            type="button"
            role="tab"
            :aria-selected="activeTab === tab.key"
            :class="[
                'shrink-0 border-b-2 px-4 py-2 text-sm font-medium whitespace-nowrap transition-colors',
                activeTab === tab.key
                    ? 'border-blue-600 text-blue-700'
                    : 'border-transparent text-slate-600 hover:border-slate-300 hover:text-slate-900',
            ]"
            @click="$emit('change', tab.key)"
        >
            {{ tab.label }}
        </button>
    </div>
</template>
