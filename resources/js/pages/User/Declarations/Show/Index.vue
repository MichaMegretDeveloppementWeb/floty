<script setup lang="ts">
/**
 * Page Show Déclaration fiscale (Phase 11 D4 + D5.6, refondu D5.8.3).
 * Affiche l'identité, la référence lisible, le statut, la synthèse
 * fiscale (snapshot avec breakdown contrats + clusters in-line via
 * `<FiscalSummaryCard>`), les motifs d'obsolescence si applicable, le
 * PDF annexe + lien download + bouton Régénérer si obsolète, et
 * l'historique de la chaîne `superseded_by_id`. Quand la déclaration
 * remplace une version antérieure, un mini banner narratif rappelle
 * la traçabilité.
 *
 * L'ancien composant `<ClustersHistoryList>` (D5.6) a été retiré : son
 * information (décisions reprises par fingerprint, contrats requalifiés)
 * est désormais visible in-line dans le tableau contrats du
 * `<FiscalSummaryCard>` via les `<ClusterGroup>` groupant les contrats
 * du même cluster, avec leur décision affichée au header.
 */
import { Head } from '@inertiajs/vue3';
import FiscalSummaryCard from '@/Components/Domain/Declaration/FiscalSummaryCard.vue';
import UserLayout from '@/Components/Layouts/UserLayout.vue';
import Header from './partials/Header.vue';
import HistoryChainCard from './partials/HistoryChainCard.vue';
import ObsolescenceBanner from './partials/ObsolescenceBanner.vue';
import PdfCard from './partials/PdfCard.vue';
import PredecessorNoticeBanner from './partials/PredecessorNoticeBanner.vue';

const props = defineProps<{
    declaration: App.Data.User.FiscalDeclaration.FiscalDeclarationData;
    snapshot: App.Data.User.FiscalDeclaration.FiscalDeclarationSnapshotData;
    history: Array<App.Data.User.FiscalDeclaration.DeclarationListItemData>;
    predecessorDeclaration: App.Data.User.FiscalDeclaration.DeclarationListItemData | null;
    successorDeclaration: App.Data.User.FiscalDeclaration.DeclarationListItemData | null;
}>();
</script>

<template>
    <Head :title="`Déclaration ${declaration.companyShortCode} ${declaration.fiscalYear} · Floty`" />

    <UserLayout>
        <div class="m-auto flex w-full max-w-[64em] flex-col gap-6">
            <Header :declaration="props.declaration" />

            <ObsolescenceBanner :declaration="props.declaration" />

            <PredecessorNoticeBanner
                v-if="props.predecessorDeclaration"
                :predecessor="props.predecessorDeclaration"
            />

            <FiscalSummaryCard :snapshot="props.snapshot" />

            <PdfCard
                :declaration="props.declaration"
                :successor-declaration="props.successorDeclaration"
            />

            <HistoryChainCard :history="props.history" :current-id="props.declaration.id" />
        </div>
    </UserLayout>
</template>
