<script setup lang="ts">
import type { CompanyTabKey } from '@/Composables/Company/Show/useCompanyTabs';

defineProps<{
    activeTab: CompanyTabKey;
}>();

defineEmits<{
    change: [tab: CompanyTabKey];
}>();

const tabs: readonly { key: CompanyTabKey; label: string }[] = [
    { key: 'overview', label: 'Vue d\'ensemble' },
    { key: 'contracts', label: 'Contrats' },
    { key: 'drivers', label: 'Conducteurs' },
    { key: 'fiscal', label: 'Recap fiscal' },
    { key: 'billing', label: 'Recap facturation' },
] as const;
</script>

<template>
    <div class="flex gap-1 border-b border-slate-200">
        <button
            v-for="tab in tabs"
            :key="tab.key"
            type="button"
            :class="[
                'border-b-2 px-4 py-2 text-sm font-medium transition-colors',
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
