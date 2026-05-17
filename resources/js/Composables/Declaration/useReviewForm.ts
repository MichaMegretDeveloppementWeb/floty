/**
 * Composable de soumission d'une décision de revue cluster (Phase 11
 * D4 + D5.6). Submit POST `/declarations/{id}/decisions` avec partial
 * reload sur `preview`, `declaration` et `snapshot` après succès -
 * le snapshot reflète le total fiscal post-décision pour que la
 * `FiscalSummaryCard` se mette à jour en direct (sinon les totaux
 * affichés divergent des opt-outs en cours).
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
                // Lot 5 D14 · `flash` doit être inclus sinon les toasts
                // (success « Décision enregistrée. » ou error de
                // validation) ne remontent pas à `useFlashToasts` qui
                // watche `page.props.flash?.toasts` · l'utilisateur
                // n'aurait aucun retour visuel après soumission. Bug
                // observé sur arbitrage Conserver rejeté silencieusement.
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
