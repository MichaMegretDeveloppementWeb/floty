import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { defineComponent, h, nextTick, reactive } from 'vue';
import { useToasts } from '@/Composables/Shared/useToasts';

/**
 * Lot 5 D6 (F-19-007 + bug back-button) · `useFlashToasts` lit désormais
 * `page.props.flash.toasts` (liste accumulée `ToastEntryData[]`) au lieu
 * des 4 canaux scalaires (success/error/warning/info). Les tests
 * mockent donc une liste de toasts directement.
 */
type FlashToastEntry = { id: string; tone: string; message: string };

const flashState = reactive<{ toasts: FlashToastEntry[] }>({ toasts: [] });

vi.mock('@inertiajs/vue3', () => ({
    usePage: () => ({
        props: {
            flash: flashState,
        },
    }),
}));

// Import après vi.mock - l'ordre est intentionnel pour que le mock
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

let nextId = 1;
const buildEntry = (tone: string, message: string): FlashToastEntry => ({
    id: `t-${nextId++}`,
    tone,
    message,
});

describe('useFlashToasts', () => {
    beforeEach(() => {
        flashState.toasts = [];
        useToasts().clear();
    });

    afterEach(() => {
        useToasts().clear();
    });

    it("ne pousse aucun toast quand la liste flash.toasts est vide", async () => {
        const { unmount } = await mountAndFlush();

        expect(useToasts().toasts.length).toBe(0);

        unmount();
    });

    it("pousse un toast error quand flash.toasts contient une entrée error au montage", async () => {
        flashState.toasts = [
            buildEntry('error', 'Les nouvelles bornes chevauchent une autre version.'),
        ];

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

    it("pousse les quatre tons cumulés si flash.toasts en contient quatre", async () => {
        flashState.toasts = [
            buildEntry('success', 'OK enregistré.'),
            buildEntry('error', 'Erreur métier.'),
            buildEntry('warning', 'Session expirée.'),
            buildEntry('info', 'Adjacent ajusté.'),
        ];

        const { unmount } = await mountAndFlush();

        const tones = useToasts().toasts.map((t) => t.tone);
        expect(tones).toEqual(['success', 'error', 'warning', 'info']);

        unmount();
    });

    it("pousse un nouveau toast quand flash.toasts gagne une entrée après montage", async () => {
        const { unmount } = await mountAndFlush();
        expect(useToasts().toasts.length).toBe(0);

        flashState.toasts = [buildEntry('success', 'Version fiscale mise à jour.')];
        await nextTick();

        const toasts = useToasts().toasts;
        expect(toasts.length).toBe(1);
        expect(toasts[0]!.tone).toBe('success');
        expect(toasts[0]!.description).toBe('Version fiscale mise à jour.');

        unmount();
    });

    it('ignore les entrées avec un tone non reconnu', async () => {
        flashState.toasts = [
            buildEntry('mystery', 'Tone inconnu, devrait être skip.'),
        ];

        const { unmount } = await mountAndFlush();

        expect(useToasts().toasts.length).toBe(0);

        unmount();
    });
});
