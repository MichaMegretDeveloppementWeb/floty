<script setup lang="ts">
/**
 * Onglet « Vue d'ensemble » de la fiche entreprise (chantier K,
 * ADR-0020 D3). Remplace l'ancien `CompanyInfoTab.vue` (tableau
 * d'état civil) par un assembleur de cartes thématiques inspirées du
 * pattern « fiche d'entité » CRM moderne :
 *
 *   1. Stats lifetime (cumul tous exercices)
 *   2. Aperçu par année (sélecteur local + 4 KPIs annuels)
 *   3. Historique par année (tableau récap)
 *   4. Contact (si renseigné)
 *   5. Adresse
 *   6. Informations légales
 *
 * Ce tab sert de **référence visible** du pattern D3 — les autres
 * fiches d'entité (Vehicle Show, Driver Show futur) suivront le même
 * pattern lors de leur passage en chantier.
 */
import { toRef } from 'vue';
import { useCompanySelectedYear } from '@/Composables/Company/Show/useCompanySelectedYear';
import CompanyAddressCard from './overview/CompanyAddressCard.vue';
import CompanyContactCard from './overview/CompanyContactCard.vue';
import CompanyLegalInfoCard from './overview/CompanyLegalInfoCard.vue';
import CompanyLifetimeStatsCard from './overview/CompanyLifetimeStatsCard.vue';
import CompanyYearHistoryCard from './overview/CompanyYearHistoryCard.vue';
import CompanyYearStatsCard from './overview/CompanyYearStatsCard.vue';

type Company = App.Data.User.Company.CompanyDetailData;

const props = defineProps<{
    company: Company;
}>();

const { selectedYear, byYear, setSelectedYear } = useCompanySelectedYear({
    history: toRef(() => props.company.history),
    availableYears: toRef(() => props.company.availableYears),
    currentRealYear: toRef(() => props.company.currentRealYear),
});
</script>

<template>
    <div class="flex flex-col gap-6">
        <CompanyLifetimeStatsCard :lifetime="company.lifetime" />

        <CompanyYearStatsCard
            :by-year="byYear"
            :available-years="company.availableYears"
            :current-real-year="company.currentRealYear"
            :selected-year="selectedYear"
            @update:selected-year="setSelectedYear"
        />

        <CompanyYearHistoryCard
            :history="company.history"
            :current-real-year="company.currentRealYear"
        />

        <div class="grid gap-4 md:grid-cols-2">
            <CompanyContactCard :company="company" />
            <CompanyAddressCard :company="company" />
        </div>

        <CompanyLegalInfoCard :company="company" />
    </div>
</template>
