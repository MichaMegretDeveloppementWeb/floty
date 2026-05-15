<script setup lang="ts">
/**
 * Page Review d'une déclaration fiscale (Phase 11 D5.8 refonte, enrichi
 * D5.10.D/E/H) :
 * synthèse fiscale prévisualisée + tableau chronologique des contrats
 * avec clusters LCD groupés visuellement (`<ClusterGroup>`) et
 * actions Conserver / Requalifier in-line.
 *
 * Un `<ReviewContextBanner>` narratif distingue les modes Préparation
 * (première version) vs Régénération (remplace une version obsolète).
 * Le `<DeclarationClustersRecap>` sticky permet de trancher les
 * décisions sans scroller jusqu'au cluster correspondant.
 *
 * Phase 13 D5.10.H · bouton Supprimer dans le header (top right
 * responsive), toggle « Voir en lecture » au-dessus du header,
 * suppression de l'accent border-l-2 amber sur le main container.
 */
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, Building2, LoaderCircle, ShieldCheck, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import DeclarationClustersRecap from '@/Components/Domain/Declaration/DeclarationClustersRecap.vue';
import FiscalSummaryCard from '@/Components/Domain/Declaration/FiscalSummaryCard.vue';
import ReviewContextBanner from '@/Components/Domain/Declaration/ReviewContextBanner.vue';
import UserLayout from '@/Components/Layouts/UserLayout.vue';
import Button from '@/Components/Ui/Button/Button.vue';
import ConfirmModal from '@/Components/Ui/ConfirmModal/ConfirmModal.vue';
import StatusPill from '@/Components/Ui/StatusPill/StatusPill.vue';
import { useReviewForm } from '@/Composables/Declaration/useReviewForm';
import { show as companyShowRoute } from '@/routes/user/companies';
import {
    destroy as destroyRoute,
    show as showDeclarationRoute,
} from '@/routes/user/declarations';
import ReviewActionsBar from './partials/ReviewActionsBar.vue';

const props = defineProps<{
    declaration: App.Data.User.FiscalDeclaration.FiscalDeclarationData;
    preview: App.Data.User.FiscalDeclaration.DeclarationPreviewData;
    snapshot: App.Data.User.FiscalDeclaration.FiscalDeclarationSnapshotData;
    predecessorDeclaration: App.Data.User.FiscalDeclaration.DeclarationListItemData | null;
    obsoleteReasons: App.Data.User.FiscalDeclaration.InvalidationReasonData[];
    canonicalHeadDeclarationId: number | null;
    riskSettings: App.Data.User.FiscalRiskSettings.FiscalRiskSettingsData;
}>();

const { submitting, submitDecision } = useReviewForm(props.declaration.id);

const isDeferred = computed<boolean>(() => props.declaration.status === 'deferred');

const bannerMode = computed<'preparation' | 'regeneration'>(
    () => (props.predecessorDeclaration !== null ? 'regeneration' : 'preparation'),
);

/**
 * Phase 13 D5.10.E · si tous les motifs d'obsolescence du predecessor
 * sont du type `voluntary_modification`, la suppression de ce brouillon
 * ré-activera le predecessor.
 */
const predecessorWillReactivate = computed<boolean>(() => {
    if (props.predecessorDeclaration === null || props.obsoleteReasons.length === 0) {
        return false;
    }
    return props.obsoleteReasons.every((r) => r.type === 'voluntary_modification');
});

const predecessorReference = computed<string | null>(
    () => props.predecessorDeclaration?.internalLabel ?? null,
);

const fiscalSummaryRef = ref<InstanceType<typeof FiscalSummaryCard> | null>(null);

// Phase 13 D5.10.H · bouton Supprimer dans le header Review.
const discarding = ref<boolean>(false);
const discardConfirmOpen = ref<boolean>(false);

const discardConfirmMessage = computed<string>(() => {
    const predRef = predecessorReference.value;
    if (predRef === null) {
        return 'Ce brouillon sera supprimé. Aucune autre déclaration n\'est concernée. Cette action est irréversible.';
    }
    if (predecessorWillReactivate.value) {
        return `Ce brouillon sera supprimé et la déclaration ${predRef} redeviendra active (la modification volontaire en cours sera annulée).`;
    }
    return `Ce brouillon sera supprimé. La déclaration ${predRef} restera obsolète et pourra être régénérée plus tard.`;
});

function requestDiscard(): void {
    if (discarding.value) {
        return;
    }
    discardConfirmOpen.value = true;
}

function confirmDiscard(): void {
    discardConfirmOpen.value = false;
    discarding.value = true;
    router.delete(destroyRoute.url({ declaration: props.declaration.id }), {
        preserveScroll: false,
        onFinish: () => {
            discarding.value = false;
        },
    });
}

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

function handleQuickRequalify(fingerprint: string): void {
    const cluster = props.preview.clusters.find((c) => c.fingerprint === fingerprint);
    if (cluster === undefined) {
        return;
    }
    // Phase 13 D5.10.S · le quick requalify depuis le recap reprend
    // l'état d'inclusion actuel (vide par défaut = tous inclus).
    handleSubmit(cluster, 'requalified', null, cluster.excludedContractIds ?? []);
}

function handleScrollTo(fingerprint: string): void {
    fiscalSummaryRef.value?.scrollToCluster(fingerprint);
}
</script>

<template>
    <Head :title="`Revue ${declaration.companyShortCode} ${declaration.fiscalYear} · Floty`" />

    <UserLayout>
        <div class="m-auto flex w-full max-w-[64em] flex-col gap-6 pb-24">
            <Link
                :href="showDeclarationRoute.url({ declaration: declaration.id })"
                class="inline-flex w-fit cursor-pointer items-center gap-1 text-xs font-medium text-slate-500 underline-offset-2 transition-colors duration-[120ms] hover:text-slate-800 hover:underline"
            >
                <ArrowLeft :size="14" :stroke-width="1.75" />
                Voir en lecture seule
            </Link>

            <header class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div class="flex items-start gap-3">
                    <div
                        class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-amber-50 text-amber-600"
                    >
                        <Building2 :size="22" :stroke-width="1.75" />
                    </div>
                    <div class="flex flex-col gap-1">
                        <p class="text-xs font-medium tracking-wider text-slate-500 uppercase">
                            Déclaration fiscale
                        </p>
                        <StatusPill tone="amber" class="w-fit">Revue interactive</StatusPill>
                        <Link
                            :href="companyShowRoute.url({ company: declaration.companyId })"
                            class="group flex w-fit cursor-pointer flex-col gap-0.5 transition-colors duration-[120ms]"
                        >
                            <h1 class="text-2xl font-semibold text-slate-900 group-hover:underline">
                                {{ declaration.companyShortCode }} · {{ declaration.fiscalYear }}
                            </h1>
                            <p class="text-sm text-slate-500 group-hover:text-slate-700">
                                {{ declaration.companyLegalName }}
                            </p>
                        </Link>
                        <p
                            class="mt-1 inline-flex w-fit items-center rounded-md bg-slate-100 px-2 py-0.5 font-mono text-xs font-medium text-slate-700"
                        >
                            {{ declaration.internalLabel }}
                        </p>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <Button
                        variant="destructive-soft"
                        :disabled="discarding"
                        @click="requestDiscard"
                    >
                        <LoaderCircle v-if="discarding" :size="16" :stroke-width="1.75" class="animate-spin" />
                        <Trash2 v-else :size="16" :stroke-width="1.75" />
                        Supprimer
                    </Button>
                </div>
            </header>

            <ReviewContextBanner
                :mode="bannerMode"
                :predecessor="predecessorDeclaration"
                :obsolete-reasons="obsoleteReasons"
                :fiscal-year="declaration.fiscalYear"
            />

            <DeclarationClustersRecap
                :clusters="preview.clusters"
                :submitting="submitting"
                @quick-requalify="handleQuickRequalify"
                @scroll-to="handleScrollTo"
            />

            <FiscalSummaryCard
                ref="fiscalSummaryRef"
                :snapshot="snapshot"
                :review-clusters="preview.clusters"
                :submitting="submitting"
                :risk-settings="riskSettings"
                @submit="(cluster, decision, justification, excludedIds) => handleSubmit(cluster, decision, justification, excludedIds)"
            />

            <div
                v-if="preview.clusters.length === 0"
                class="flex flex-col items-center gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 p-8 text-center"
            >
                <div
                    class="flex h-12 w-12 items-center justify-center rounded-xl bg-white text-emerald-600"
                >
                    <ShieldCheck :size="24" :stroke-width="1.75" />
                </div>
                <p class="text-sm font-semibold text-emerald-900">
                    Aucune chaîne LCD à risque détectée
                </p>
                <p class="max-w-md text-xs text-emerald-800">
                    Le périmètre fiscal de cet exercice ne révèle aucune chaîne
                    LCD nécessitant une revue. Vous pouvez générer la déclaration
                    directement.
                </p>
            </div>

            <ReviewActionsBar
                :declaration-id="declaration.id"
                :pending-clusters-count="preview.pendingClustersCount"
                :can-generate="preview.canGenerate"
                :is-deferred="isDeferred"
            />
        </div>

        <ConfirmModal
            v-model:open="discardConfirmOpen"
            title="Supprimer le brouillon ?"
            :message="discardConfirmMessage"
            confirm-label="Supprimer"
            cancel-label="Annuler"
            tone="danger"
            @confirm="confirmDiscard"
        />
    </UserLayout>
</template>
