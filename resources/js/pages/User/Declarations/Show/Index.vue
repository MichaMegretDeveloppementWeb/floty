<script setup lang="ts">
/**
 * Page Show Déclaration fiscale (Phase 11 D4 + D5.6). Affiche
 * l'identité, la référence lisible, le statut, la synthèse fiscale
 * (snapshot recalculé via DeclarationFiscalEngine), les motifs
 * d'obsolescence si applicable, le PDF annexe + lien download +
 * bouton Régénérer si obsolète, l'historique des décisions appliquées,
 * et l'historique de la chaîne `superseded_by_id`.
 */
import { Head } from '@inertiajs/vue3';
import ClustersHistoryList from '@/Components/Domain/Declaration/ClustersHistoryList.vue';
import FiscalSummaryCard from '@/Components/Domain/Declaration/FiscalSummaryCard.vue';
import UserLayout from '@/Components/Layouts/UserLayout.vue';
import Header from './partials/Header.vue';
import HistoryChainCard from './partials/HistoryChainCard.vue';
import ObsolescenceBanner from './partials/ObsolescenceBanner.vue';
import PdfCard from './partials/PdfCard.vue';

const props = defineProps<{
    declaration: App.Data.User.FiscalDeclaration.FiscalDeclarationData;
    snapshot: App.Data.User.FiscalDeclaration.FiscalDeclarationSnapshotData;
    history: Array<App.Data.User.FiscalDeclaration.DeclarationListItemData>;
}>();
</script>

<template>
    <Head :title="`Déclaration ${declaration.companyShortCode} ${declaration.fiscalYear} · Floty`" />

    <UserLayout>
        <div class="m-auto flex w-full max-w-[64em] flex-col gap-6">
            <Header :declaration="props.declaration" />

            <ObsolescenceBanner :declaration="props.declaration" />

            <FiscalSummaryCard :snapshot="props.snapshot" />

            <PdfCard :declaration="props.declaration" />

            <ClustersHistoryList
                :decisions="props.snapshot.appliedDecisions"
                :opt-out-contracts-count="props.snapshot.optOutContractIds.length"
            />

            <HistoryChainCard :history="props.history" :current-id="props.declaration.id" />
        </div>
    </UserLayout>
</template>
