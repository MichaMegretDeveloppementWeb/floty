<script setup lang="ts">
import { FileCheck2 } from 'lucide-vue-next';
</script>

<template>
    <div class="flex flex-col items-center gap-3 rounded-2xl border border-slate-200 bg-white p-12 text-center">
        <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-slate-50 text-slate-400">
            <FileCheck2 :size="24" :stroke-width="1.75" />
        </div>
        <p class="text-sm font-medium text-slate-700">
            Aucune déclaration fiscale enregistrée.
        </p>
        <p class="max-w-md text-xs text-slate-500">
            Choisissez une entreprise et un exercice clos pour ouvrir sa
            fiscalité, puis lancer la préparation de la déclaration.
        </p>
        <slot />
    </div>
</template>
