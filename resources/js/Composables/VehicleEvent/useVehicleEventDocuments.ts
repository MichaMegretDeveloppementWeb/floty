import { ref } from 'vue';
import type { Ref } from 'vue';
import { useApi } from '@/Composables/Shared/useApi';
import { useToasts } from '@/Composables/Shared/useToasts';
import {
    destroy as destroyDocumentRoute,
    store as storeDocumentRoute,
} from '@/routes/user/vehicle-events/documents';

type Document = App.Data.User.VehicleEvent.VehicleEventDocumentData;

export type UseVehicleEventDocumentsReturn = {
    uploading: Ref<boolean>;
    uploadOne: (vehicleEventId: number, file: File) => Promise<Document>;
    uploadMany: (vehicleEventId: number, files: File[]) => Promise<Document[]>;
    deleteDocument: (vehicleEventId: number, documentId: number) => Promise<void>;
};

/**
 * State and operations for a vehicle event's supporting documents.
 *
 * Multi-file uploads are sequential (not parallel) to keep UI feedback clear, respect the server
 * rate-limit (30 uploads/min) and simplify error handling: one failure does not cancel previous successes.
 *
 * Aligned with {@see useContractDocuments}.
 */
export function useVehicleEventDocuments(): UseVehicleEventDocumentsReturn {
    const api = useApi();
    const toasts = useToasts();
    const uploading = ref<boolean>(false);

    const uploadOne = async (
        vehicleEventId: number,
        file: File,
    ): Promise<Document> => {
        const formData = new FormData();
        formData.append('file', file);

        const response = await api.postFormData<{ document: Document }>(
            storeDocumentRoute.url({ vehicleEvent: vehicleEventId }),
            formData,
        );

        return response.document;
    };

    const uploadMany = async (
        vehicleEventId: number,
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
                    const doc = await uploadOne(vehicleEventId, file);
                    uploaded.push(doc);
                } catch {
                    // Error toast already shown by useApi; continue with the next files (best-effort).
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
        vehicleEventId: number,
        documentId: number,
    ): Promise<void> => {
        await api.delete(
            destroyDocumentRoute.url({ vehicleEvent: vehicleEventId, document: documentId }),
        );

        toasts.push({
            tone: 'success',
            title: 'Document supprimé',
        });
    };

    return { uploading, uploadOne, uploadMany, deleteDocument };
}
