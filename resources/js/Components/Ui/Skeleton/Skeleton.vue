<script setup lang="ts">
/**
 * Skeleton avec animation shimmer (reflet mouvant gauche → droite) ·
 * remplace l'ancien `<div class="animate-pulse bg-slate-100" />` qui
 * contrastait trop peu avec les fonds blancs/slate-50 du design system.
 *
 * La classe `.skeleton-shimmer` + le keyframe `skeleton-shimmer` sont
 * définis globalement dans `resources/css/app.css` · gradient
 * slate-100 → slate-200 → slate-100, deplacement horizontal de 1.6s
 * en boucle. Sobre, conforme à la palette Floty.
 *
 * Utilisation typique ·
 *   <Skeleton class="h-12 w-full rounded-xl" />
 *   <Skeleton class="h-4 w-16 rounded" />
 *   <Skeleton class="h-24 rounded-2xl" />
 *
 * Le composant n'impose pas de border-radius par défaut · à preciser
 * via class (`rounded`, `rounded-xl`, `rounded-full`, etc.).
 */
import { computed } from 'vue';

const props = defineProps<{
    class?: string;
}>();

const rootClass = computed<string>(() => ['skeleton-shimmer', props.class].filter(Boolean).join(' '));
</script>

<template>
    <div :class="rootClass" aria-busy="true" aria-live="polite" />
</template>
