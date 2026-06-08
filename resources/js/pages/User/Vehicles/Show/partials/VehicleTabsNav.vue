<script setup lang="ts">
import type { VehicleTabKey } from '@/Composables/Vehicle/Show/useVehicleTabs';

defineProps<{
    activeTab: VehicleTabKey;
    controlsBadge: App.Data.User.Control.Vehicle.VehicleControlsBadgeData;
}>();

defineEmits<{
    change: [tab: VehicleTabKey];
}>();

const tabs: readonly { key: VehicleTabKey; label: string }[] = [
    { key: 'overview', label: 'Vue d\'ensemble' },
    { key: 'events', label: 'Événements' },
    { key: 'controls', label: 'Contrôles réglementaires' },
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
                'inline-flex shrink-0 cursor-pointer items-center gap-1.5 border-b-2 px-4 py-2 text-sm font-medium whitespace-nowrap transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-200',
                activeTab === tab.key
                    ? 'border-blue-600 text-blue-700'
                    : 'border-transparent text-slate-600 hover:border-slate-300 hover:text-slate-900',
            ]"
            @click="$emit('change', tab.key)"
        >
            {{ tab.label }}
            <span
                v-if="tab.key === 'controls' && controlsBadge.dueCount > 0"
                :class="[
                    'inline-flex min-w-[1.125rem] items-center justify-center rounded-full px-1 text-[0.6875rem] leading-[1.125rem] font-semibold',
                    controlsBadge.overdueCount > 0
                        ? 'bg-rose-100 text-rose-700'
                        : 'bg-amber-100 text-amber-700',
                ]"
                :title="
                    controlsBadge.overdueCount > 0
                        ? `${controlsBadge.overdueCount} contrôle${controlsBadge.overdueCount > 1 ? 's' : ''} en retard`
                        : `${controlsBadge.dueCount} contrôle${controlsBadge.dueCount > 1 ? 's' : ''} à échéance`
                "
            >
                {{ controlsBadge.dueCount }}
            </span>
        </button>
    </div>
</template>
