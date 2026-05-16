<script setup lang="ts">
/**
 * Page Show Déclaration fiscale (Phase 11 D4 + D5.6, refondu D5.10.G/H).
 * Affiche l'identité, la référence lisible, le statut, la synthèse
 * fiscale (snapshot avec breakdown contrats + clusters in-line via
 * `<FiscalSummaryCard>`), les motifs d'obsolescence si applicable, le
 * PDF annexe + lien download + bouton Régénérer si obsolète, et
 * l'historique de la chaîne `superseded_by_id`. Quand la déclaration
 * remplace une version antérieure, un mini banner narratif rappelle
 * la traçabilité.
 *
 * Phase 13 D5.10.H · `canonicalHeadDeclarationId` distingue le head
 * éditable des brouillons intermédiaires (orphelins) qui ne sont que
 * supprimables.
 */
import { Deferred, Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import DeclarationHistoryTimeline from '@/Components/Domain/Declaration/DeclarationHistoryTimeline.vue';
import FiscalSummaryCard from '@/Components/Domain/Declaration/FiscalSummaryCard.vue';
import UserLayout from '@/Components/Layouts/UserLayout.vue';
import Card from '@/Components/Ui/Card/Card.vue';
import Skeleton from '@/Components/Ui/Skeleton/Skeleton.vue';
import Header from './partials/Header.vue';
import ObsolescenceBanner from './partials/ObsolescenceBanner.vue';
import PdfCard from './partials/PdfCard.vue';
import PredecessorNoticeBanner from './partials/PredecessorNoticeBanner.vue';

type ItemData = App.Data.User.FiscalDeclaration.DeclarationListItemData;

const props = defineProps<{
    declaration: App.Data.User.FiscalDeclaration.FiscalDeclarationData;
    /**
     * P0.5 (audit perf 2026-05-16 / 08-misc.md P0 #2) · servi en
     * `Inertia::defer` UNIQUEMENT pour les Draft sans payload persiste
     * (cas fallback `engine->compute()` ~100-500 ms). Pour les
     * Generated avec payload, arrive eager au mount. <Deferred> gere
     * les 2 cas proprement · slot defaut immediat si eager, skeleton
     * sinon.
     */
    snapshot?: App.Data.User.FiscalDeclaration.FiscalDeclarationSnapshotData;
    history: ItemData[];
    predecessorDeclaration: ItemData | null;
    successorDeclaration: ItemData | null;
    canonicalHeadDeclarationId: number | null;
}>();

/**
 * Le predecessor sera ré-activé à la suppression si ses motifs
 * d'obsolescence sont **uniquement** des VoluntaryModification (cas
 * du brouillon de régénération volontaire abandonné). Ces motifs
 * sont portés par le predecessor lui-même · on les lit via la
 * relation déjà chargée dans `predecessorDeclaration` quand exposée.
 *
 * Note · le DTO `DeclarationListItemData` n'expose pas les motifs
 * (ils sont disponibles côté Review via prop séparée). Pour Show,
 * on calcule simplement sur la base · si le statut current est draft
 * et qu'il a un predecessor, c'est généralement une modification
 * volontaire. Approximation acceptable pour le message UX.
 */
const predecessorWillReactivate = computed<boolean>(() => {
    // Heuristique simple · si on est sur un brouillon avec
    // predecessor, on présume volontaire. Le backend décide vraiment.
    return props.declaration.status === 'draft' && props.predecessorDeclaration !== null;
});

const predecessorReference = computed<string | null>(
    () => props.predecessorDeclaration?.internalLabel ?? null,
);

const isCanonicalHead = computed<boolean>(
    () => props.declaration.id === props.canonicalHeadDeclarationId,
);
</script>

<template>
    <Head :title="`Déclaration ${declaration.companyShortCode} ${declaration.fiscalYear} · Floty`" />

    <UserLayout>
        <div class="m-auto flex w-full max-w-[64em] flex-col gap-6">
            <Header
                :declaration="declaration"
                :predecessor-reference="predecessorReference"
                :predecessor-will-reactivate="predecessorWillReactivate"
            />

            <ObsolescenceBanner :declaration="declaration" />

            <PredecessorNoticeBanner
                v-if="predecessorDeclaration"
                :predecessor="predecessorDeclaration"
            />

            <Deferred data="snapshot">
                <template #fallback>
                    <Skeleton class="h-72 rounded-2xl" />
                </template>
                <FiscalSummaryCard :snapshot="snapshot!" />
            </Deferred>

            <PdfCard
                :declaration="declaration"
                :successor-declaration="successorDeclaration"
                :is-canonical-head="isCanonicalHead"
            />

            <Card v-if="history.length > 0">
                <DeclarationHistoryTimeline
                    :entries="history"
                    :current-declaration-id="declaration.id"
                />
            </Card>
        </div>
    </UserLayout>
</template>
