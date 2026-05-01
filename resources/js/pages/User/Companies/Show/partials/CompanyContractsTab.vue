<script setup lang="ts">
import Card from '@/Components/Ui/Card/Card.vue';

defineProps<{
    company: App.Data.User.Company.CompanyDetailData;
}>();
</script>

<template>
    <Card>
        <h3 class="text-base font-semibold text-slate-900">Contrats</h3>
        <p class="mt-2 text-sm text-slate-500">
            {{ company.contractsCount }} contrat(s) au total pour cette
            entreprise. Voir la page
            <span class="font-mono">/app/contracts</span> pour la liste
            détaillée filtrable.
        </p>
        <p class="mt-2 text-xs text-slate-400">
            Section à enrichir avec le tableau filtré + génération facture
            mensuelle (Phase 14).
        </p>
    </Card>
</template>
