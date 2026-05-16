<script setup lang="ts">
defineProps<{
    daysInFiscalYear: number;
}>();
</script>

<template>
    <section
        class="flex flex-col gap-3 rounded-xl border border-slate-200 bg-slate-50/40 p-6"
    >
        <p class="eyebrow">Formule générale</p>
        <p class="font-mono text-base leading-relaxed text-slate-700">
            <span class="font-medium text-slate-900">Taxe due</span>
            <span class="mx-1 text-slate-400">=</span>
            <span class="text-slate-700">(Tarif CO₂ + Tarif polluants)</span>
            <span class="mx-1 text-slate-400">×</span>
            <span class="text-slate-700">
                jours utilisés
                <span class="text-slate-400">/</span>
                {{ daysInFiscalYear }}
            </span>
            <span class="mx-1 text-slate-400">×</span>
            <span class="text-slate-700">(1 − exonérations)</span>
        </p>
        <p class="text-sm leading-relaxed text-slate-500">
            Le tarif annuel plein dépend des caractéristiques du véhicule ·
            le prorata, de l'attribution · les exonérations, du couple
            véhicule × entreprise.
        </p>
    </section>
</template>
