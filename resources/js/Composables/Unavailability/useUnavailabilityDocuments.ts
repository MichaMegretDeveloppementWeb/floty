import { ref } from 'vue';
import type { Ref } from 'vue';
import { useApi } from '@/Composables/Shared/useApi';
import { useToasts } from '@/Composables/Shared/useToasts';
import {
    destroy as destroyDocumentRoute,
    store as storeDocumentRoute,
} from '@/routes/user/unavailabilities/documents';

type Document = App.Data.User.Unavailability.UnavailabilityDocumentData;

export type UseUnavailabilityDocumentsReturn = {
    uploading: Ref<boolean>;
    uploadOne: (unavailabilityId: number, file: File) => Promise<Document>;
    uploadMany: (unavailabilityId: number, files: File[]) => Promise<Document[]>;
    deleteDocument: (unavailabilityId: number, documentId: number) => Promise<void>;
};

/**
 * État + opérations sur les justificatifs joints à une indispo (P1).
 *
 * Les uploads multi-fichiers sont **séquentiels** (pas parallèles) ·
 *   - feedback de progression compréhensible côté UI
 *   - évite de saturer le rate-limit serveur (30 uploads/min)
 *   - gestion d'erreur simple · un échec n'annule pas les uploads
 *     précédents, l'utilisateur sait ce qui est passé
 *
 * Aligné sur {@see useContractDocuments}.
 */
export function useUnavailabilityDocuments(): UseUnavailabilityDocumentsReturn {
    const api = useApi();
    const toasts = useToasts();
    const uploading = ref<boolean>(false);

    const uploadOne = async (
        unavailabilityId: number,
        file: File,
    ): Promise<Document> => {
        const formData = new FormData();
        formData.append('file', file);

        const response = await api.postFormData<{ document: Document }>(
            storeDocumentRoute.url({ unavailability: unavailabilityId }),
            formData,
        );

        return response.document;
    };

    const uploadMany = async (
        unavailabilityId: number,
        files: File[],
    ): Promise<Document[]> => {
        if (files.length === 0) {
            return [];
        }

        uploading.value = true;
        const uploaded: Document[] = [];

        try {
            for (const file of files) {
                try {
                    const doc = await uploadOne(unavailabilityId, file);
                    uploaded.push(doc);
                } catch {
                    // Toast erreur déjà affiché par useApi. On continue
                    // les autres fichiers · pattern « best effort ».
                }
            }
        } finally {
            uploading.value = false;
        }

        if (uploaded.length > 0) {
            const allOk = uploaded.length === files.length;
            const plural = uploaded.length > 1 ? 's' : '';
            toasts.push({
                tone: 'success',
                title: 'Documents ajoutés',
                description: allOk
                    ? `${uploaded.length} document${plural} uploadé${plural}.`
                    : `${uploaded.length} / ${files.length} document${plural} uploadé${plural}.`,
            });
        }

        return uploaded;
    };

    const deleteDocument = async (
        unavailabilityId: number,
        documentId: number,
    ): Promise<void> => {
        await api.delete(
            destroyDocumentRoute.url({ unavailability: unavailabilityId, document: documentId }),
        );

        toasts.push({
            tone: 'success',
            title: 'Document supprimé',
        });
    };

    return { uploading, uploadOne, uploadMany, deleteDocument };
}
