<script setup lang="ts">
defineProps<{
    fiscalYear: number;
}>();
</script>

<template>
    <header>
        <p class="eyebrow mb-2">Tableau de bord · {{ fiscalYear }}</p>
        <h1
            class="text-2xl font-semibold tracking-tight text-slate-900 md:text-3xl"
        >
            Aperçu flotte {{ fiscalYear }}
        </h1>
        <p class="mt-1 text-base text-slate-600">
            Vue synthétique de l'activité en cours.
        </p>
    </header>
</template>
