<script setup lang="ts">
/**
 * Onglet « Vue d'ensemble » de la fiche entreprise.
 *
 * **Doctrine temporelle (chantier η Phase 1, 2026-05-05)** : 3 lentilles
 * temporelles distinctes :
 *   - **Présent** = `CompanyKpiCards` (4 KPIs sur l'année calendaire courante)
 *   - **Évolution** = `CompanyYearHistoryCard` (récap années passées)
 *   - **Exploration** = `CompanyActivityCard` (sélecteur d'année local)
 *
 * Layout responsive (pattern aligné avec Vehicle Show) :
 *   - Hero (full width, dans Show/Index.vue) ✓
 *   - 4 KPIs Présent (full width)
 *   - Historique par année (full width)
 *   - Layout 2 colonnes XL+ : main (Historique en col-span-2) + aside
 *     (Contact + Adresse en col-span-1)
 *   - < xl : Contact + Adresse passent dans le flux principal sous
 *     l'historique (déjà rendus dans le main, l'aside disparaît)
 */
import CompanyActivityCard from './overview/CompanyActivityCard.vue';
import CompanyAddressCard from './overview/CompanyAddressCard.vue';
import CompanyContactCard from './overview/CompanyContactCard.vue';
import CompanyKpiCards from './overview/CompanyKpiCards.vue';
import CompanyYearHistoryCard from './overview/CompanyYearHistoryCard.vue';

defineProps<{
    company: App.Data.User.Company.CompanyDetailData;
}>();
</script>

<template>
    <div class="flex flex-col gap-6">
        <CompanyKpiCards
            :kpi-stats="company.kpiStats"
            :kpi-year="company.kpiYear"
            :kpi-fiscal-available="company.kpiFiscalAvailable"
        />

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
            <!-- Colonne principale -->
            <div class="flex flex-col gap-6 xl:col-span-2">
                <CompanyYearHistoryCard :history="company.history" />
                <CompanyActivityCard :company="company" />

                <!-- < xl : Contact + Adresse dans le main flow, sous l'activité. En xl+, l'aside les porte. -->
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 xl:hidden">
                    <CompanyContactCard :company="company" />
                    <CompanyAddressCard :company="company" />
                </div>
            </div>

            <!-- Aside visible xl+ uniquement -->
            <aside class="hidden xl:col-span-1 xl:block">
                <div class="flex flex-col gap-6">
                    <CompanyContactCard :company="company" />
                    <CompanyAddressCard :company="company" />
                </div>
            </aside>
        </div>
    </div>
</template>
