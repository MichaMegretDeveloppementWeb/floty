/**
 * Composable de soumission d'une décision de revue cluster (Phase 11
 * D4). Submit POST `/declarations/{id}/decisions` avec partial reload
 * sur `preview` après succès.
 */
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
            } as Record<string, unknown>,
            {
                preserveScroll: true,
                only: ['preview', 'declaration'],
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
