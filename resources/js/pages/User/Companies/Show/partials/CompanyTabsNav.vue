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
    <!-- Scroll horizontal natif sur mobile (touch drag fonctionne en
         standard) ; sur desktop la barre fait sa largeur naturelle si
         tout rentre, sinon scroll molette/touchpad. Scrollbar masquée
         visuellement pour rester propre - le visuel border-b reste
         continu sous la nav. -->
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
