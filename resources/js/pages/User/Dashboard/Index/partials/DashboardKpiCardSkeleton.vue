<script setup lang="ts">
/**
 * Skeleton d'une carte KPI · proportions calquées sur `KpiCard.vue`
 * (label uppercase tiny + value mono 4xl + caption sm + aside facultatif)
 * pour éviter le layout shift à l'hydratation des props deferred.
 *
 * Utilise `Skeleton` (shimmer animé contrasté) plutôt que `animate-pulse`
 * pour rester visible sur fond blanc des cards.
 */
import Skeleton from '@/Components/Ui/Skeleton/Skeleton.vue';
</script>

<template>
    <article
        class="flex flex-col gap-2.5 rounded-xl border border-slate-200 bg-white px-5 py-4"
        aria-busy="true"
    >
        <!-- Label uppercase (text-xs · h-3) -->
        <Skeleton class="h-3 w-28 rounded" />
        <!-- Valeur principale (text-4xl · h-9) -->
        <div class="flex items-end justify-between gap-3">
            <Skeleton class="h-9 w-32 rounded-md" />
            <!-- Aside (mock pour cohérence layout) -->
            <div class="flex flex-col items-end gap-1">
                <Skeleton class="h-4 w-12 rounded" />
                <Skeleton class="h-2 w-14 rounded" />
            </div>
        </div>
        <!-- Caption (text-sm · h-4) -->
        <Skeleton class="h-4 w-44 rounded" />
    </article>
</template>
