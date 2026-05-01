<script setup lang="ts">
import Card from '@/Components/Ui/Card/Card.vue';
</script>

<template>
    <Card>
        <h3 class="text-base font-semibold text-slate-900">Recap fiscal</h3>
        <p class="mt-2 text-sm text-slate-500">
            Recap des taxes dues par cette entreprise (CO₂ + polluants) sur l'année fiscale active.
        </p>
        <p class="mt-2 text-xs text-slate-400">
            Section à enrichir avec FleetFiscalAggregator → tableau par véhicule (Phase 11 ADR-0015).
        </p>
    </Card>
</template>
