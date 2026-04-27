<script setup lang="ts">
defineProps<{
    daysInFiscalYear: number;
}>();
</script>

<template>
    <section
        class="rounded-xl border border-slate-200 bg-gradient-to-br from-slate-50 to-white p-5"
    >
        <p class="eyebrow mb-2">Formule générale</p>
        <p class="font-mono text-sm leading-relaxed text-slate-800">
            <span class="font-medium text-slate-900">Taxe due</span>
            =
            <span class="text-blue-700">(Tarif CO₂ + Tarif polluants)</span>
            ×
            <span class="text-slate-700">
                (jours utilisés / {{ daysInFiscalYear }})
            </span>
            ×
            <span class="text-emerald-700">(1 − exonérations)</span>
        </p>
        <p class="mt-3 text-sm text-slate-600">
            Le tarif annuel plein dépend des caractéristiques du véhicule ;
            le prorata dépend de l'attribution ; les exonérations dépendent
            du couple véhicule × entreprise.
        </p>
    </section>
</template>
