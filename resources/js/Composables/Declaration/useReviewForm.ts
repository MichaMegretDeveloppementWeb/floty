/**
 * Submits a cluster review decision via POST `/declarations/{id}/decisions`.
 *
 * On success, partial-reloads `preview`, `declaration` and `snapshot` so the snapshot reflects
 * the post-decision fiscal total and `FiscalSummaryCard` stays consistent with the opt-outs in flight.
 */
import type { RequestPayload } from '@inertiajs/core';
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import type { Ref } from 'vue';
import { store as storeDecisionRoute } from '@/routes/user/declarations/decisions';

type StoreReviewDecisionData = App.Data.User.FiscalReviewDecision.StoreReviewDecisionData;

export function useReviewForm(declarationId: number): {
    submitting: Ref<boolean>;
    submitDecision: (data: StoreReviewDecisionData) => void;
} {
    const submitting = ref<boolean>(false);

    function submitDecision(data: StoreReviewDecisionData): void {
        submitting.value = true;

        router.post(
            storeDecisionRoute.url({ declaration: declarationId }),
            {
                company_id: data.companyId,
                fiscal_year: data.fiscalYear,
                risk_code: data.riskCode,
                cluster_fingerprint: data.clusterFingerprint,
                decision: data.decision,
                justification: data.justification ?? '',
                excluded_contract_ids: data.excludedContractIds ?? [],
            } as RequestPayload,
            {
                preserveScroll: true,
                // `flash` must be included so toasts (success or validation error) propagate
                // through `useFlashToasts` watching `page.props.flash?.toasts`. Without it the
                // user gets no visual feedback after submit.
                only: ['preview', 'declaration', 'snapshot', 'flash'],
                onFinish: () => {
                    submitting.value = false;
                },
            },
        );
    }

    return {
        submitting,
        submitDecision,
    };
}
