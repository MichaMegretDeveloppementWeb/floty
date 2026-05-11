<script setup lang="ts">
import type { CompanyTabKey } from '@/Composables/Company/Show/useCompanyTabs';

withDefaults(
    defineProps<{
        activeTab: CompanyTabKey;
        /**
         * Phase 12 D5.9.D · affiche un petit dot ambre à droite du
         * libellé « Fiscalité » quand au moins une déclaration de
         * l'entreprise est en attente d'action (lifecycle ≠ Generated
         * Active). Signale subtilement « quelque chose à faire » sans
         * crier comme une alerte rouge.
         */
        fiscalHasTodo?: boolean;
    }>(),
    {
        fiscalHasTodo: false,
    },
);

defineEmits<{
    change: [tab: CompanyTabKey];
}>();

const tabs: readonly { key: CompanyTabKey; label: string }[] = [
    { key: 'overview', label: 'Vue d\'ensemble' },
    { key: 'contracts', label: 'Locations' },
    { key: 'drivers', label: 'Conducteurs' },
    { key: 'fiscal', label: 'Fiscalité' },
    { key: 'billing', label: 'Facturation' },
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
                'inline-flex shrink-0 cursor-pointer items-center gap-1.5 border-b-2 px-4 py-2 text-sm font-medium whitespace-nowrap transition-colors',
                activeTab === tab.key
                    ? 'border-blue-600 text-blue-700'
                    : 'border-transparent text-slate-600 hover:border-slate-300 hover:text-slate-900',
            ]"
            @click="$emit('change', tab.key)"
        >
            {{ tab.label }}
            <span
                v-if="tab.key === 'fiscal' && fiscalHasTodo"
                class="inline-block size-1.5 rounded-full bg-amber-400"
                title="Déclarations en attente"
                aria-hidden="true"
            />
        </button>
    </div>
</template>
