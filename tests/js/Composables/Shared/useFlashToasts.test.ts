import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { defineComponent, h, nextTick, reactive } from 'vue';
import { useToasts } from '@/Composables/Shared/useToasts';

const flashState = reactive<{
    success: string | null;
    error: string | null;
    warning: string | null;
    info: string | null;
}>({
    success: null,
    error: null,
    warning: null,
    info: null,
});

vi.mock('@inertiajs/vue3', () => ({
    usePage: () => ({
        props: {
            flash: flashState,
        },
    }),
}));

// Import après vi.mock — l'ordre est intentionnel pour que le mock
// d'@inertiajs/vue3 soit installé avant l'évaluation du composable.
// eslint-disable-next-line import/order
import { useFlashToasts } from '@/Composables/Shared/useFlashToasts';

const Host = defineComponent({
    name: 'Host',
    setup() {
        useFlashToasts();

        return () => h('div');
    },
});

async function mountAndFlush(): Promise<{ unmount: () => void }> {
    const { createApp } = await import('vue');
    const root = document.createElement('div');
    document.body.appendChild(root);
    const app = createApp(Host);
    app.mount(root);
    await nextTick();

    return {
        unmount: () => {
            app.unmount();
            root.remove();
        },
    };
}

describe('useFlashToasts', () => {
    beforeEach(() => {
        flashState.success = null;
        flashState.error = null;
        flashState.warning = null;
        flashState.info = null;
        useToasts().clear();
    });

    afterEach(() => {
        useToasts().clear();
    });

    it("ne pousse aucun toast quand tous les canaux flash sont nuls", async () => {
        const { unmount } = await mountAndFlush();

        expect(useToasts().toasts.length).toBe(0);

        unmount();
    });

    it("pousse un toast error quand flash.error est renseigné au montage", async () => {
        flashState.error = 'Les nouvelles bornes chevauchent une autre version.';

        const { unmount } = await mountAndFlush();

        const toasts = useToasts().toasts;
        expect(toasts.length).toBe(1);
        expect(toasts[0]!.tone).toBe('error');
        expect(toasts[0]!.title).toBe('Erreur');
        expect(toasts[0]!.description).toBe(
            'Les nouvelles bornes chevauchent une autre version.',
        );

        unmount();
    });

    it("pousse les quatre tons cumulés si tous sont renseignés", async () => {
        flashState.success = 'OK enregistré.';
        flashState.error = 'Erreur métier.';
        flashState.warning = 'Session expirée.';
        flashState.info = 'Adjacent ajusté.';

        const { unmount } = await mountAndFlush();

        const tones = useToasts().toasts.map((t) => t.tone);
        expect(tones).toEqual(['success', 'error', 'warning', 'info']);

        unmount();
    });

    it("pousse un nouveau toast quand un canal change après montage", async () => {
        const { unmount } = await mountAndFlush();
        expect(useToasts().toasts.length).toBe(0);

        flashState.success = 'Version fiscale mise à jour.';
        await nextTick();

        const toasts = useToasts().toasts;
        expect(toasts.length).toBe(1);
        expect(toasts[0]!.tone).toBe('success');
        expect(toasts[0]!.description).toBe('Version fiscale mise à jour.');

        unmount();
    });

    it('ignore les chaînes vides et les valeurs non-string', async () => {
        flashState.success = '';
        // simulate accidental non-string sneak — ignored
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        (flashState as any).error = 0;

        const { unmount } = await mountAndFlush();

        expect(useToasts().toasts.length).toBe(0);

        unmount();
    });
});
