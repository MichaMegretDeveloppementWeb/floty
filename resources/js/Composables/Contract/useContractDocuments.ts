import { ref } from 'vue';
import type { Ref } from 'vue';
import { useApi } from '@/Composables/Shared/useApi';
import { useToasts } from '@/Composables/Shared/useToasts';
import {
    destroy as destroyDocumentRoute,
    store as storeDocumentRoute,
} from '@/routes/user/contracts/documents';

type Document = App.Data.User.Contract.ContractDocumentData;

export type UseContractDocumentsReturn = {
    uploading: Ref<boolean>;
    uploadOne: (contractId: number, file: File) => Promise<Document>;
    uploadMany: (contractId: number, files: File[]) => Promise<Document[]>;
    deleteDocument: (contractId: number, documentId: number) => Promise<void>;
};

/**
 * État + opérations sur les documents PDF d'un contrat (chantier 04.N).
 *
 * Les uploads multi-fichiers sont **séquentiels** (pas parallèles) pour :
 *   - garder un feedback de progression compréhensible côté UI
 *   - éviter de saturer le rate-limit serveur (30 uploads/min)
 *   - simplifier la gestion d'erreur (un échec n'annule pas les
 *     uploads précédents — l'utilisateur sait ce qui est passé)
 */
export function useContractDocuments(): UseContractDocumentsReturn {
    const api = useApi();
    const toasts = useToasts();
    const uploading = ref<boolean>(false);

    const uploadOne = async (
        contractId: number,
        file: File,
    ): Promise<Document> => {
        const formData = new FormData();
        formData.append('file', file);

        const response = await api.postFormData<{ document: Document }>(
            storeDocumentRoute.url({ contract: contractId }),
            formData,
        );

        return response.document;
    };

    const uploadMany = async (
        contractId: number,
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
                    const doc = await uploadOne(contractId, file);
                    uploaded.push(doc);
                } catch {
                    // Toast erreur déjà affiché par useApi. On continue
                    // les autres fichiers — pattern « best effort ».
                }
            }
        } finally {
            uploading.value = false;
        }

        if (uploaded.length > 0 && uploaded.length === files.length) {
            toasts.push({
                tone: 'success',
                title: 'Documents ajoutés',
                description: `${uploaded.length} document${uploaded.length > 1 ? 's' : ''} uploadé${uploaded.length > 1 ? 's' : ''}.`,
            });
        }

        return uploaded;
    };

    const deleteDocument = async (
        contractId: number,
        documentId: number,
    ): Promise<void> => {
        await api.delete(
            destroyDocumentRoute.url({ contract: contractId, document: documentId }),
        );

        toasts.push({
            tone: 'success',
            title: 'Document supprimé',
        });
    };

    return { uploading, uploadOne, uploadMany, deleteDocument };
}
