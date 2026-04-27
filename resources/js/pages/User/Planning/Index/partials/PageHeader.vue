<script setup lang="ts">
defineProps<{
    fiscalYear: number;
}>();
</script>

<template>
    <header>
        <p class="eyebrow mb-1">Planning</p>
        <h1
            class="text-2xl font-semibold tracking-tight text-slate-900 md:text-3xl"
        >
            Vue d'ensemble · {{ fiscalYear }}
        </h1>
        <p class="mt-1 text-base text-slate-600">
            Densité d'utilisation de la flotte semaine par semaine.
            Cliquez sur une cellule pour attribuer des jours et voir
            l'impact fiscal en temps réel.
        </p>
    </header>
</template>
