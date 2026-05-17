<script setup lang="ts">
// Skeleton d'une carte KPI · même gabarit que `KpiCard.vue` pour
// éviter le layout shift à l'hydratation des props deferred
// (chantier perf Dashboard 2026-05-17).
</script>

<template>
    <article
        class="flex flex-col gap-2.5 rounded-xl border border-slate-200 bg-white px-5 py-4"
        aria-busy="true"
    >
        <div class="skeleton-shimmer h-3 w-32 rounded" />
        <div class="mt-1 skeleton-shimmer h-8 w-24 rounded" />
        <div class="skeleton-shimmer h-3 w-40 rounded" />
    </article>
</template>
