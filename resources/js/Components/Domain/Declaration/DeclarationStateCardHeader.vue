<script setup lang="ts">
/**
 * En-tête commun des 7 branches de {@see DeclarationStateCard.vue}
 * (Lot 5 D12 · F-19D-012 + F-19D2-018 · extraction de la duplication
 * structurelle observée).
 *
 * Chaque carte d'état (S1 à S7) commence par le même bloc · une icône
 * Lucide colorée à gauche, suivie d'un titre et d'un éventuel
 * `StatusPill`, puis du contenu textuel descriptif via le slot par
 * défaut. Centraliser ce squelette évite que les 7 branches divergent
 * accidentellement (espacements, classes, structure HTML).
 *
 * **Slots** ·
 *   - `#icon` · l'icône Lucide (taille gérée par l'appelant ·
 *     typiquement `:size="22" :stroke-width="1.75"`)
 *   - default · le contenu textuel (paragraphe + sous-info)
 *
 * **Pas de logique métier** · composant purement présentationnel.
 */
import StatusPill from '@/Components/Ui/StatusPill/StatusPill.vue';
import type { StatusTone } from '@/types/ui/status';

defineProps<{
    /**
     * Classes Tailwind appliquées au cartouche d'icône (combine fond
     * et couleur de l'icône). Ex. `'bg-blue-50 text-blue-600'`,
     * `'bg-emerald-50 text-emerald-600'`, etc.
     */
    iconBgClass: string;
    title: string;
    /**
     * Tone et label du `StatusPill` accolé au titre. `null` ou
     * `undefined` masque le pill (cas S1 « Untouched » qui n'a pas
     * de pill par exemple).
     */
    pillTone?: StatusTone | null;
    pillLabel?: string | null;
}>();
</script>

<template>
    <div class="flex items-start gap-4 self-start max-w-[60em]">
        <div
            :class="[
                'flex h-12 w-12 shrink-0 items-center justify-center rounded-xl',
                iconBgClass,
            ]"
        >
            <slot name="icon" />
        </div>
        <div class="flex flex-col gap-1.5">
            <div v-if="pillLabel" class="flex flex-wrap items-center gap-2">
                <h4 class="text-lg font-semibold text-slate-900">
                    {{ title }}
                </h4>
                <StatusPill :tone="pillTone ?? 'slate'">{{ pillLabel }}</StatusPill>
            </div>
            <h4 v-else class="text-lg font-semibold text-slate-900">
                {{ title }}
            </h4>
            <slot />
        </div>
    </div>
</template>
