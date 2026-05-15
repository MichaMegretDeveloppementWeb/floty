<script setup lang="ts">
/**
 * Onglet « Vue d'ensemble » de la fiche entreprise.
 *
 * **Doctrine temporelle (chantier η Phase 1, 2026-05-05)** · 3 lentilles
 * temporelles distinctes ·
 *   - **Présent** = `CompanyKpiCards` (4 KPIs sur l'année calendaire courante)
 *   - **Évolution** = `CompanyYearHistoryCard` (récap années passées)
 *   - **Exploration** = `CompanyActivityCard` (sélecteur d'année local)
 *
 * Refonte design D5.10.W · pattern Linear-éditorial avec eyebrows
 * sectionnés, KPIs flush (plus de StatCard), historique flush, et
 * conservation des cards aside Contact/Adresse (info statique
 * compacte). L'alerte `PendingActionsAlert` reste en bannière rouge
 * en haut (sémantique d'urgence, conservée comme avant).
 */
import { computed } from 'vue';
import CompanyActivityCard from './overview/CompanyActivityCard.vue';
import CompanyAddressCard from './overview/CompanyAddressCard.vue';
import CompanyContactCard from './overview/CompanyContactCard.vue';
import CompanyKpiCards from './overview/CompanyKpiCards.vue';
import CompanyYearHistoryCard from './overview/CompanyYearHistoryCard.vue';
import PendingActionsAlert from './overview/PendingActionsAlert.vue';

const props = defineProps<{
    company: App.Data.User.Company.CompanyDetailData;
    pendingDeclarations?: App.Data.User.FiscalDeclaration.PendingDeclarationData[];
    pendingInvoices?: App.Data.User.Billing.PendingInvoiceYearData[];
}>();

const emit = defineEmits<{
    'goto-fiscal-year': [year: number];
    'goto-billing-year': [year: number];
}>();

const hasAnyPending = computed<boolean>(
    () => (props.pendingDeclarations?.length ?? 0) + (props.pendingInvoices?.length ?? 0) > 0,
);
</script>

<template>
    <div class="flex flex-col gap-10">
        <!-- Alerte actions à traiter (sémantique d'urgence · conservée) -->
        <PendingActionsAlert
            v-if="hasAnyPending"
            :pending-declarations="props.pendingDeclarations ?? []"
            :pending-invoices="props.pendingInvoices ?? []"
            :company-id="props.company.id"
            @goto-fiscal-year="(year) => emit('goto-fiscal-year', year)"
            @goto-billing-year="(year) => emit('goto-billing-year', year)"
        />

        <!--
            Section · KPIs présent (année courante). Pas d'eyebrow ·
            les KPIs parlent d'eux-mêmes en tête de page, et la
            séparation `border-y` du stats row fournit déjà une
            structure visuelle suffisante.
        -->
        <CompanyKpiCards
            :kpi-stats="company.kpiStats"
            :kpi-year="company.kpiYear"
            :kpi-fiscal-available="company.kpiFiscalAvailable"
        />

        <div class="grid grid-cols-1 gap-10 xl:grid-cols-3 xl:gap-12">
            <!-- Colonne principale -->
            <div class="flex flex-col gap-10 xl:col-span-2">
                <!-- Section · Historique par année (évolution) -->
                <section class="flex flex-col gap-4">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-500">
                        Historique par année
                    </p>
                    <CompanyYearHistoryCard :history="company.history" unwrapped />
                </section>

                <!-- Section · Activité (exploration, conservée avec sa carte interne) -->
                <CompanyActivityCard :company="company" />

                <!--
                    < xl · Contact + Adresse passent dans le flux
                    principal sous l'activité. En xl+, l'aside les
                    porte.
                -->
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
