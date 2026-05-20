import { router } from '@inertiajs/vue3';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { useInvoiceRegeneration } from '@/Composables/Billing/useInvoiceRegeneration';

describe('useInvoiceRegeneration', () => {
    beforeEach(() => {
        vi.mocked(router.post).mockClear();
    });

    it('expose un flag regenerating à false par défaut', () => {
        const { regenerating } = useInvoiceRegeneration({ redirectTarget: 'show' });

        expect(regenerating.value).toBe(false);
    });

    it('passe regenerating à true au démarrage et déclenche router.post', () => {
        const { regenerating, regenerate } = useInvoiceRegeneration({ redirectTarget: 'show' });

        regenerate(42);

        expect(regenerating.value).toBe(true);
        expect(router.post).toHaveBeenCalledOnce();
        const [, payload] = vi.mocked(router.post).mock.calls[0]!;
        expect(payload).toEqual({ redirect_target: 'show' });
    });

    it('honore le redirectTarget configuré dans le payload', () => {
        const { regenerate } = useInvoiceRegeneration({ redirectTarget: 'company-tab' });

        regenerate(42);

        const [, payload] = vi.mocked(router.post).mock.calls[0]!;
        expect(payload).toEqual({ redirect_target: 'company-tab' });
    });

    it('repasse regenerating à false dans onFinish + déclenche le callback fourni', () => {
        const onFinish = vi.fn();
        const { regenerating, regenerate } = useInvoiceRegeneration({
            redirectTarget: 'show',
            onFinish,
        });

        regenerate(42);

        expect(regenerating.value).toBe(true);

        const [, , options] = vi.mocked(router.post).mock.calls[0]!;
        options?.onFinish?.({} as never);

        expect(regenerating.value).toBe(false);
        expect(onFinish).toHaveBeenCalledOnce();
    });
});
