import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';

const toastPush = vi.fn();
vi.mock('@/Composables/Shared/useToasts', () => ({
    useToasts: () => ({ push: toastPush }),
}));

import DocumentDropZone from '@/Components/Ui/DocumentDropZone/DocumentDropZone.vue';

function buildFile(name: string, type: string, sizeBytes = 100): File {
    const blob = new Blob([new Uint8Array(sizeBytes)], { type });

    return new File([blob], name, { type });
}

async function emitDrop(wrapper: ReturnType<typeof mount>, files: File[]): Promise<void> {
    const dataTransfer = { files } as unknown as DataTransfer;
    await wrapper
        .find('[role="button"]')
        .trigger('drop', { dataTransfer });
}

describe('DocumentDropZone', () => {
    beforeEach(() => {
        toastPush.mockReset();
    });

    describe('mode single-MIME · application/pdf', () => {
        it('accepte un fichier PDF et émet files-added', async () => {
            const wrapper = mount(DocumentDropZone, {
                props: { accept: 'application/pdf', maxSizeBytes: 5_000_000 },
            });

            const file = buildFile('contrat.pdf', 'application/pdf');
            await emitDrop(wrapper, [file]);

            expect(wrapper.emitted('files-added')).toHaveLength(1);
            expect(wrapper.emitted('files-added')![0][0]).toEqual([file]);
            expect(toastPush).not.toHaveBeenCalled();
        });

        it('rejette un fichier JPG en mode single-MIME PDF', async () => {
            const wrapper = mount(DocumentDropZone, {
                props: { accept: 'application/pdf', maxSizeBytes: 5_000_000 },
            });

            await emitDrop(wrapper, [buildFile('photo.jpg', 'image/jpeg')]);

            expect(wrapper.emitted('files-added')).toBeUndefined();
            expect(toastPush).toHaveBeenCalledTimes(1);
            expect(toastPush.mock.calls[0][0].title).toBe('Fichier rejeté');
        });

        it('affiche le label « PDF » dans la zone de dépôt', () => {
            const wrapper = mount(DocumentDropZone, {
                props: { accept: 'application/pdf', maxSizeBytes: 5_000_000 },
            });

            expect(wrapper.text()).toContain('PDF');
        });
    });

    describe('mode multi-MIME · liste séparée par virgules', () => {
        const acceptMulti = 'application/pdf,image/jpeg,image/png,image/webp';

        it('accepte un PDF', async () => {
            const wrapper = mount(DocumentDropZone, {
                props: { accept: acceptMulti, maxSizeBytes: 5_000_000 },
            });

            await emitDrop(wrapper, [buildFile('justif.pdf', 'application/pdf')]);

            expect(wrapper.emitted('files-added')).toHaveLength(1);
        });

        it('accepte un PNG', async () => {
            const wrapper = mount(DocumentDropZone, {
                props: { accept: acceptMulti, maxSizeBytes: 5_000_000 },
            });

            await emitDrop(wrapper, [buildFile('capture.png', 'image/png')]);

            expect(wrapper.emitted('files-added')).toHaveLength(1);
        });

        it('rejette un TXT non listé', async () => {
            const wrapper = mount(DocumentDropZone, {
                props: { accept: acceptMulti, maxSizeBytes: 5_000_000 },
            });

            await emitDrop(wrapper, [buildFile('readme.txt', 'text/plain')]);

            expect(wrapper.emitted('files-added')).toBeUndefined();
            expect(toastPush).toHaveBeenCalledTimes(1);
            expect(toastPush.mock.calls[0][0].description).toContain('PDF, JPG, PNG, WebP');
        });

        it('affiche le label humain « PDF, JPG, PNG, WebP » au lieu du raw MIME', () => {
            const wrapper = mount(DocumentDropZone, {
                props: { accept: acceptMulti, maxSizeBytes: 5_000_000 },
            });

            expect(wrapper.text()).toContain('PDF, JPG, PNG, WebP');
            expect(wrapper.text()).not.toContain('application/pdf,image/jpeg');
        });
    });

    describe('validation taille + nombre', () => {
        it('rejette un fichier qui dépasse maxSizeBytes', async () => {
            const wrapper = mount(DocumentDropZone, {
                props: { accept: 'application/pdf', maxSizeBytes: 1_000 },
            });

            await emitDrop(wrapper, [buildFile('gros.pdf', 'application/pdf', 5_000)]);

            expect(wrapper.emitted('files-added')).toBeUndefined();
            expect(toastPush).toHaveBeenCalledTimes(1);
            expect(toastPush.mock.calls[0][0].description).toContain('dépasse');
        });

        it('tronque à maxFiles et logge une erreur', async () => {
            const wrapper = mount(DocumentDropZone, {
                props: {
                    accept: 'application/pdf',
                    maxSizeBytes: 5_000_000,
                    maxFiles: 2,
                },
            });

            await emitDrop(wrapper, [
                buildFile('a.pdf', 'application/pdf'),
                buildFile('b.pdf', 'application/pdf'),
                buildFile('c.pdf', 'application/pdf'),
            ]);

            const emitted = wrapper.emitted('files-added')!;
            expect(emitted).toHaveLength(1);
            expect(emitted[0][0]).toHaveLength(2);
            expect(toastPush).toHaveBeenCalledTimes(1);
            expect(toastPush.mock.calls[0][0].description).toContain('2 fichier');
        });
    });
});
