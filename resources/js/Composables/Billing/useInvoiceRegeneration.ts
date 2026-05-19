/**
 * Invoice regeneration helper shared by company-tab button and show-page banner.
 *
 * `redirectTarget` maps to backend `RegenerateRedirectTarget` enum:
 * - `'show'` redirects to the regenerated invoice (id changes after regeneration).
 * - `'company-tab'` keeps the user on the company billing tab.
 * - `'index'` falls back to the invoices list.
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
