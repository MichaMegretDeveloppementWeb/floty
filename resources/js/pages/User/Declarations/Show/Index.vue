<script setup lang="ts">
/**
 * Page Show Déclaration fiscale · écran unique adaptatif
 * (Lot 5 D12 · fusion Show + Review).
 *
 * **Mode A · lecture pure** · déclaration `generated` (active ou
 * obsolète) ou ancien snapshot persisté. Affiche · Header (avec
 * pill Lecture si non-éditable), ObsolescenceBanner si Generated
 * obsolète, PredecessorNoticeBanner si remplace une version,
 * FiscalSummaryCard lecture, PdfCard (download + CTA modifier /
 * régénérer / reprendre régénération), historique.
 *
 * **Mode B · revue interactive** · brouillon `draft` ou `deferred`
 * ET head canonique du couple `(company, year)`. Affiche · Header
 * (avec boutons supprimer / annuler mise en attente), ObsolescenceBanner
 * (jamais affichée car les brouillons ne sont plus flaggés obsolètes),
 * ReviewContextBanner narratif (preparation / regeneration),
 * DeclarationClustersRecap sticky, FiscalSummaryCard interactive
 * (@submit décisions cluster), empty state si aucun cluster,
 * ReviewActionsBar sticky (Reporter / Générer), historique.
 *
 * **Mode C · brouillon intermédiaire orphelin** · brouillon
 * `draft` / `deferred` qui n'est pas head canonique (un autre
 * brouillon plus récent est devenu head). Affiche le même chrome
 * que mode A mais le PdfCard rend un message « intermédiaire »
 * invitant à supprimer (la suppression passe par le Header).
 *
 * Le mode est dérivé via `isEditableDraft` à partir de `declaration.status`
 * et `canonicalHeadDeclarationId`. Le backend (`DeclarationController::show`)
 * ne sert `preview`, `obsoleteReasons` et `riskSettings` qu'en mode B.
 */
import { Deferred, Head } from '@inertiajs/vue3';
import { Clock } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import DeclarationClustersRecap from '@/Components/Domain/Declaration/DeclarationClustersRecap.vue';
import DeclarationHistoryTimeline from '@/Components/Domain/Declaration/DeclarationHistoryTimeline.vue';
import FiscalSummaryCard from '@/Components/Domain/Declaration/FiscalSummaryCard.vue';
import ReviewContextBanner from '@/Components/Domain/Declaration/ReviewContextBanner.vue';
import UserLayout from '@/Components/Layouts/UserLayout.vue';
import Card from '@/Components/Ui/Card/Card.vue';
import Skeleton from '@/Components/Ui/Skeleton/Skeleton.vue';
import { useReviewForm } from '@/Composables/Declaration/useReviewForm';
import Header from './partials/Header.vue';
import ObsolescenceBanner from './partials/ObsolescenceBanner.vue';
import PdfCard from './partials/PdfCard.vue';
import PredecessorNoticeBanner from './partials/PredecessorNoticeBanner.vue';
import ReviewActionsBar from './partials/ReviewActionsBar.vue';

type ItemData = App.Data.User.FiscalDeclaration.DeclarationListItemData;

const props = defineProps<{
    declaration: App.Data.User.FiscalDeclaration.FiscalDeclarationData;
    /**
     * P0.5 (audit perf 2026-05-16 / 08-misc.md P0 #2) · servi en
     * `Inertia::defer` pour Draft sans payload (cas fallback
     * `engine->compute()` ~100-500 ms) ainsi qu'en mode B (revue
     * interactive recalculée live). Pour Generated avec payload,
     * arrive eager au mount. `<Deferred>` gère les 2 cas proprement.
     */
    snapshot?: App.Data.User.FiscalDeclaration.FiscalDeclarationSnapshotData;
    history: ItemData[];
    predecessorDeclaration: ItemData | null;
    successorDeclaration: ItemData | null;
    canonicalHeadDeclarationId: number | null;
    /**
     * Lot 5 D12 · props mode B (revue interactive). Servies par le
     * backend uniquement quand `declaration` est un brouillon head
     * canonique. `undefined` en mode A et C.
     */
    preview?: App.Data.User.FiscalDeclaration.DeclarationPreviewData;
    obsoleteReasons?: App.Data.User.FiscalDeclaration.InvalidationReasonData[];
    riskSettings?: App.Data.User.FiscalRiskSettings.FiscalRiskSettingsData;
}>();

/**
 * Mode B éligible si la déclaration est un brouillon (draft/deferred)
 * ET le head canonique du couple `(company, year)`. Le backend
 * synchronise · `preview` / `riskSettings` / `obsoleteReasons` ne sont
 * servis que dans ce cas.
 */
const isEditableDraft = computed<boolean>(
    () => (props.declaration.status === 'draft' || props.declaration.status === 'deferred')
        && props.canonicalHeadDeclarationId === props.declaration.id,
);

const isDeferred = computed<boolean>(() => props.declaration.status === 'deferred');

/**
 * Mode B uniquement · contexte narratif preparation vs regeneration
 * pour le `<ReviewContextBanner>`. La régénération est signalée par la
 * présence d'un `predecessorDeclaration` (chaîne `superseded_by_id`).
 */
const bannerMode = computed<'preparation' | 'regeneration'>(
    () => (props.predecessorDeclaration !== null ? 'regeneration' : 'preparation'),
);

/**
 * Phase 13 D5.10.E · si tous les motifs d'obsolescence du predecessor
 * sont du type `voluntary_modification`, la suppression de ce brouillon
 * ré-activera le predecessor (cas modification volontaire abandonnée).
 *
 * Mode B · on lit `obsoleteReasons` servi par le backend (extraction
 * `InvalidationReasonData::listFromRaw` du predecessor).
 *
 * Modes A et C · heuristique de repli (cf. ancienne logique Show) ·
 * draft + predecessor présent → présumé volontaire. Le backend décide
 * vraiment à la suppression effective.
 */
const predecessorWillReactivate = computed<boolean>(() => {
    if (isEditableDraft.value && (props.obsoleteReasons?.length ?? 0) > 0) {
        return props.obsoleteReasons!.every((r) => r.type === 'voluntary_modification');
    }

    return props.declaration.status === 'draft' && props.predecessorDeclaration !== null;
});

const predecessorReference = computed<string | null>(
    () => props.predecessorDeclaration?.internalLabel ?? null,
);

const isCanonicalHead = computed<boolean>(
    () => props.declaration.id === props.canonicalHeadDeclarationId,
);

/**
 * Hook de soumission décision cluster · partial reload sur `preview`,
 * `declaration` et `snapshot` après succès. Initialisé même en mode A/C
 * (composable inactif tant que `submitDecision` n'est pas appelé) pour
 * éviter un init conditionnel qui violerait les règles d'hooks Vue.
 */
const { submitting, submitDecision } = useReviewForm(props.declaration.id);

const fiscalSummaryRef = ref<InstanceType<typeof FiscalSummaryCard> | null>(null);

function handleSubmit(
    cluster: App.Data.User.FiscalDeclaration.ReviewClusterData,
    decision: 'conserved' | 'requalified',
    justification: string | null,
    excludedContractIds: number[],
): void {
    submitDecision({
        companyId: props.declaration.companyId,
        fiscalYear: props.declaration.fiscalYear,
        riskCode: cluster.code,
        clusterFingerprint: cluster.fingerprint,
        decision,
        justification,
        excludedContractIds,
    });
}

function handleScrollTo(fingerprint: string): void {
    fiscalSummaryRef.value?.scrollToCluster(fingerprint);
}
</script>

<template>
    <Head :title="`Déclaration ${declaration.companyShortCode} ${declaration.fiscalYear} · Floty`" />

    <UserLayout>
        <div
            :class="[
                'm-auto flex w-full max-w-[64em] flex-col gap-6',
                isEditableDraft && 'pb-24',
            ]"
        >
            <Header
                :declaration="declaration"
                :predecessor-reference="predecessorReference"
                :predecessor-will-reactivate="predecessorWillReactivate"
            />

            <ObsolescenceBanner :declaration="declaration" />

            <!--
                Bandeau prédécesseur · `ReviewContextBanner` narratif en
                mode B (preparation / regeneration), `PredecessorNoticeBanner`
                générique en modes A/C. Mutuellement exclusifs.
            -->
            <template v-if="isEditableDraft">
                <ReviewContextBanner
                    :mode="bannerMode"
                    :predecessor="predecessorDeclaration"
                    :obsolete-reasons="obsoleteReasons ?? []"
                    :fiscal-year="declaration.fiscalYear"
                />
                <!--
                    Lot 5 D13 · banner « raison du report » affiché quand
                    le brouillon est reporté (`status === 'deferred'`) et
                    qu'une raison a été saisie au modal Reporter. État
                    transitoire · effacé au revert ou à la génération.
                -->
                <div
                    v-if="declaration.status === 'deferred' && declaration.deferReason"
                    class="flex items-start gap-3 rounded-sm border border-slate-200 border-l-2 border-l-amber-400 bg-white p-4"
                >
                    <Clock class="shrink-0 text-amber-500" :size="18" :stroke-width="1.75" />
                    <div class="flex flex-col gap-1">
                        <p class="text-sm font-semibold text-slate-900">
                            Raison du report
                        </p>
                        <p class="whitespace-pre-line text-sm text-slate-700">
                            {{ declaration.deferReason }}
                        </p>
                    </div>
                </div>
            </template>
            <PredecessorNoticeBanner
                v-else-if="predecessorDeclaration"
                :predecessor="predecessorDeclaration"
            />

            <!--
                Mode B · revue interactive · ClustersRecap + FiscalSummaryCard
                avec @submit + empty state + ReviewActionsBar sticky. Les 2
                pipelines (`preview` RiskDetection + `snapshot` Fiscal Engine)
                sont servis en `Inertia::defer` côté backend · skeleton
                fallback transparent.
            -->
            <template v-if="isEditableDraft">
                <Deferred :data="['preview', 'snapshot']">
                    <template #fallback>
                        <Skeleton class="h-24 rounded-2xl" />
                        <Skeleton class="h-96 rounded-2xl" />
                    </template>

                    <DeclarationClustersRecap
                        :clusters="preview!.clusters"
                        :submitting="submitting"
                        @scroll-to="handleScrollTo"
                    />

                    <FiscalSummaryCard
                        ref="fiscalSummaryRef"
                        :snapshot="snapshot!"
                        :review-clusters="preview!.clusters"
                        :submitting="submitting"
                        :risk-settings="riskSettings!"
                        @submit="(cluster, decision, justification, excludedIds) => handleSubmit(cluster, decision, justification, excludedIds)"
                    />

                    <ReviewActionsBar
                        :declaration-id="declaration.id"
                        :pending-clusters-count="preview!.pendingClustersCount"
                        :can-generate="preview!.canGenerate"
                        :is-deferred="isDeferred"
                    />
                </Deferred>
            </template>

            <!--
                Modes A et C · FiscalSummaryCard lecture (snapshot
                figé ou recalculé live selon le payload) + PdfCard
                qui rend le download + les CTA contextuels (Modifier
                / Régénérer / Reprendre régénération / Message
                intermédiaire orphelin selon le statut).
            -->
            <template v-else>
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
            </template>

            <Card v-if="history.length > 0">
                <DeclarationHistoryTimeline
                    :entries="history"
                    :current-declaration-id="declaration.id"
                />
            </Card>
        </div>
    </UserLayout>
</template>