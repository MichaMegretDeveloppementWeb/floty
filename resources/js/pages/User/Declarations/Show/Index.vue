<script setup lang="ts">
/**
 * Page Show Déclaration fiscale (Phase 11 D4). Affiche l'identité, le
 * statut, les motifs d'obsolescence si applicable, le PDF annexe + lien
 * download + bouton Régénérer si obsolète, l'historique de la chaîne
 * `superseded_by_id` et un placeholder pour les décisions de revue
 * (livraison D5).
 */
import { Head } from '@inertiajs/vue3';
import UserLayout from '@/Components/Layouts/UserLayout.vue';
import ClustersHistorySection from './partials/ClustersHistorySection.vue';
import Header from './partials/Header.vue';
import HistoryChainCard from './partials/HistoryChainCard.vue';
import ObsolescenceBanner from './partials/ObsolescenceBanner.vue';
import PdfCard from './partials/PdfCard.vue';

const props = defineProps<{
    declaration: App.Data.User.FiscalDeclaration.FiscalDeclarationData;
    history: Array<App.Data.User.FiscalDeclaration.DeclarationListItemData>;
}>();
</script>

<template>
    <Head :title="`Déclaration ${declaration.companyShortCode} ${declaration.fiscalYear} · Floty`" />

    <UserLayout>
        <div class="m-auto flex w-full max-w-[64em] flex-col gap-6">
            <Header :declaration="props.declaration" />

            <ObsolescenceBanner :declaration="props.declaration" />

            <PdfCard :declaration="props.declaration" />

            <ClustersHistorySection :declaration="props.declaration" />

            <HistoryChainCard :history="props.history" :current-id="props.declaration.id" />
        </div>
    </UserLayout>
</template>
