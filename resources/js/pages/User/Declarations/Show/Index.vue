<script setup lang="ts">
/**
 * Adaptive Show screen for a fiscal declaration. Modes:
 *   A · read-only (generated or stale snapshot)
 *   B · interactive review (draft/deferred head canonical)
 *   C · orphan intermediate draft (read-only chrome, delete-only)
 * `isEditableDraft` derives the mode from `declaration.status` and
 * `canonicalHeadDeclarationId`. Backend serves `preview`,
 * `obsoleteReasons` and `riskSettings` only in mode B.
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
    /** Inertia::defer for drafts/mode B; eager for generated with payload. */
    snapshot?: App.Data.User.FiscalDeclaration.FiscalDeclarationSnapshotData;
    history: ItemData[];
    predecessorDeclaration: ItemData | null;
    successorDeclaration: ItemData | null;
    canonicalHeadDeclarationId: number | null;
    /** Mode B (interactive review) props; undefined in modes A and C. */
    preview?: App.Data.User.FiscalDeclaration.DeclarationPreviewData;
    obsoleteReasons?: App.Data.User.FiscalDeclaration.InvalidationReasonData[];
    riskSettings?: App.Data.User.FiscalRiskSettings.FiscalRiskSettingsData;
}>();

const isEditableDraft = computed<boolean>(
    () => (props.declaration.status === 'draft' || props.declaration.status === 'deferred')
        && props.canonicalHeadDeclarationId === props.declaration.id,
);

const isDeferred = computed<boolean>(() => props.declaration.status === 'deferred');

const bannerMode = computed<'preparation' | 'regeneration'>(
    () => (props.predecessorDeclaration !== null ? 'regeneration' : 'preparation'),
);

// Mode B reads backend `obsoleteReasons`; modes A/C fall back to the
// draft + predecessor heuristic. Backend decides definitively at delete.
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

// Always initialised to satisfy Vue hooks rules; inert until called in mode B.
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

            <template v-if="isEditableDraft">
                <ReviewContextBanner
                    :mode="bannerMode"
                    :predecessor="predecessorDeclaration"
                    :obsolete-reasons="obsoleteReasons ?? []"
                    :fiscal-year="declaration.fiscalYear"
                />
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