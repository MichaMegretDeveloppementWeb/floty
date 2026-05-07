/**
 * Composable de régénération de facture (T11 / E.2). Mutualise la
 * logique partagée entre `GenerateInvoiceButton.vue` (depuis l'onglet
 * Facturation d'une entreprise · target `'company-tab'`) et
 * `InvoiceDivergenceBanner.vue` (depuis la fiche Show facture · target
 * `'show'`).
 *
 * Le `redirectTarget` détermine la cible côté serveur après la
 * régénération (cf. backend `RegenerateRedirectTarget` enum) :
 * - `'show'` : redirige vers la nouvelle facture (id changé après
 *   régénération · usage typique fiche Show).
 * - `'company-tab'` : reste sur l'onglet Facturation entreprise.
 * - `'index'` : retombe sur la liste Factures.
 */

import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import type { Ref } from 'vue';
import { regenerate as invoicesRegenerateRoute } from '@/routes/user/invoices';

export type InvoiceRegenerationTarget = 'show' | 'company-tab' | 'index';

export function useInvoiceRegeneration(opts: {
    redirectTarget: InvoiceRegenerationTarget;
    onFinish?: () => void;
}): {
    regenerating: Ref<boolean>;
    regenerate: (invoiceId: number) => void;
} {
    const regenerating = ref<boolean>(false);

    function regenerate(invoiceId: number): void {
        regenerating.value = true;

        router.post(
            invoicesRegenerateRoute.url({ invoice: invoiceId }),
            { redirect_target: opts.redirectTarget },
            {
                preserveScroll: true,
                onFinish: () => {
                    regenerating.value = false;
                    opts.onFinish?.();
                },
            },
        );
    }

    return { regenerating, regenerate };
}
