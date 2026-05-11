<script setup lang="ts">
/**
 * Page Review d'une déclaration fiscale (Phase 11 D5.8 refonte) :
 * synthèse fiscale prévisualisée + tableau chronologique des contrats
 * avec clusters LCD groupés visuellement (`<ClusterGroup>`) et
 * actions Conserver / Requalifier in-line.
 *
 * Un `<ReviewContextBanner>` narratif distingue les modes Préparation
 * (première version) vs Régénération (remplace une version obsolète).
 * Le `<DeclarationClustersRecap>` sticky permet de trancher les
 * décisions sans scroller jusqu'au cluster correspondant.
 */
import { Head } from '@inertiajs/vue3';
import { Building2, ShieldCheck } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import DeclarationClustersRecap from '@/Components/Domain/Declaration/DeclarationClustersRecap.vue';
import FiscalSummaryCard from '@/Components/Domain/Declaration/FiscalSummaryCard.vue';
import ReviewContextBanner from '@/Components/Domain/Declaration/ReviewContextBanner.vue';
import UserLayout from '@/Components/Layouts/UserLayout.vue';
import { useReviewForm } from '@/Composables/Declaration/useReviewForm';
import ReviewActionsBar from './partials/ReviewActionsBar.vue';

const props = defineProps<{
    declaration: App.Data.User.FiscalDeclaration.FiscalDeclarationData;
    preview: App.Data.User.FiscalDeclaration.DeclarationPreviewData;
    snapshot: App.Data.User.FiscalDeclaration.FiscalDeclarationSnapshotData;
    predecessorDeclaration: App.Data.User.FiscalDeclaration.DeclarationListItemData | null;
    obsoleteReasons: App.Data.User.FiscalDeclaration.InvalidationReasonData[];
}>();

const { submitting, submitDecision } = useReviewForm(props.declaration.id);

const isDeferred = computed<boolean>(() => props.declaration.status === 'deferred');

const bannerMode = computed<'preparation' | 'regeneration'>(
    () => (props.predecessorDeclaration !== null ? 'regeneration' : 'preparation'),
);

const fiscalSummaryRef = ref<InstanceType<typeof FiscalSummaryCard> | null>(null);

function handleSubmit(
    cluster: App.Data.User.FiscalDeclaration.ReviewClusterData,
    decision: 'conserved' | 'requalified',
    justification: string | null,
): void {
    submitDecision({
        companyId: props.declaration.companyId,
        fiscalYear: props.declaration.fiscalYear,
        riskCode: cluster.code,
        clusterFingerprint: cluster.fingerprint,
        decision,
        justification,
    });
}

function handleQuickRequalify(fingerprint: string): void {
    const cluster = props.preview.clusters.find((c) => c.fingerprint === fingerprint);

    if (cluster === undefined) {
        return;
    }

    handleSubmit(cluster, 'requalified', null);
}

function handleScrollTo(fingerprint: string): void {
    fiscalSummaryRef.value?.scrollToCluster(fingerprint);
}
</script>

<template>
    <Head :title="`Revue ${declaration.companyShortCode} ${declaration.fiscalYear} · Floty`" />

    <UserLayout>
        <div class="m-auto flex w-full max-w-[64em] flex-col gap-6 pb-24">
            <header class="flex items-start gap-3">
                <div
                    class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-600"
                >
                    <Building2 :size="22" :stroke-width="1.75" />
                </div>
                <div class="flex flex-col gap-1">
                    <p class="text-xs font-medium tracking-wider text-slate-500 uppercase">
                        Revue fiscale
                    </p>
                    <h1 class="text-2xl font-semibold text-slate-900">
                        {{ declaration.companyShortCode }} · {{ declaration.fiscalYear }}
                    </h1>
                    <p class="text-sm text-slate-500">{{ declaration.companyLegalName }}</p>
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
                @submit="(cluster, decision, justification) => handleSubmit(cluster, decision, justification)"
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
    </UserLayout>
</template>
