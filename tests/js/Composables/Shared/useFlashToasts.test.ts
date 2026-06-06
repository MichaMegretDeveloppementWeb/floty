import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { defineComponent, h, nextTick, reactive } from 'vue';
import { useToasts } from '@/Composables/Shared/useToasts';

/**
 * `useFlashToasts` reads `page.props.flash.toasts` (a `ToastEntryData[]`),
 * not the legacy scalar success/error/warning/info channels. The tests
 * therefore mock a list of toasts directly.
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

// Imported after vi.mock so the @inertiajs/vue3 stub is installed before
// the composable is evaluated.
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

    it('ne pousse pas deux fois la même entrée id (dédup retour arrière)', async () => {
        const entry = { id: 'dedup-back-1', tone: 'success', message: 'Action réalisée.' };
        flashState.toasts = [entry];

        const { unmount } = await mountAndFlush();
        expect(useToasts().toasts.length).toBe(1);

        // Inertia restaure les mêmes flash.toasts (même id) au retour arrière :
        // le watcher se redéclenche mais l'id déjà vu est ignoré.
        flashState.toasts = [{ ...entry }];
        await nextTick();

        expect(useToasts().toasts.length).toBe(1);

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
