import { router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import type { ComputedRef, Ref } from 'vue';
import { useVehicleEventDocuments } from '@/Composables/VehicleEvent/useVehicleEventDocuments';

type VehicleEvent = App.Data.User.VehicleEvent.VehicleEventData;
type Document = App.Data.User.VehicleEvent.VehicleEventDocumentData;

/**
 * Limits aligned with the backend (`UploadVehicleEventDocumentData` FormRequest +
 * `UploadVehicleEventDocumentAction`). Exposed so the modal can render them (N / 5 counter,
 * 5 Mo max label, `accept` attribute on the input).
 */
export const MAX_DOCUMENTS = 5;
export const MAX_DOCUMENT_SIZE_BYTES = 5 * 1024 * 1024;
export const ACCEPTED_DOCUMENT_MIMES = 'application/pdf,image/jpeg,image/png,image/webp';

export type UseVehicleEventFormDocumentsReturn = {
    documents: Ref<Document[]>;
    uploading: Ref<boolean>;
    canUploadMore: ComputedRef<boolean>;
    remainingSlots: ComputedRef<number>;
    confirmDeleteOpen: Ref<boolean>;
    documentToDelete: Ref<Document | null>;
    deleteConfirmMessage: ComputedRef<string>;
    onFilesAdded: (files: File[]) => Promise<void>;
    requestDelete: (doc: Document) => void;
    confirmDelete: () => Promise<void>;
};

/**
 * Form-side handling of a vehicle event's supporting documents.
 *
 * Contract:
 *   - Initialises `documents` from `editing.documents` on each change (create → empty, edit → server docs).
 *   - Sequential upload of added files (via `uploadMany`).
 *   - Partial Inertia reload (`vehicleEvents`) after mutation to keep the server payload coherent,
 *     so closing/reopening the modal preserves the documents.
 *   - Two-step delete (modal confirmation before DELETE) to avoid accidental loss.
 *
 * Precondition: `editing` must be non-null before upload/delete. A vehicle event must exist in DB
 * to attach documents to. The modal gates this via `v-if="isEditing"`.
 */
export function useVehicleEventFormDocuments(props: {
    editing: VehicleEvent | null;
}): UseVehicleEventFormDocumentsReturn {
    const { uploading, uploadMany, deleteDocument } = useVehicleEventDocuments();

    const documents = ref<Document[]>([]);

    watch(
        () => props.editing,
        (value) => {
            documents.value = value !== null ? [...value.documents] : [];
        },
        { immediate: true },
    );

    const canUploadMore = computed<boolean>(() => documents.value.length < MAX_DOCUMENTS);
    const remainingSlots = computed<number>(() => MAX_DOCUMENTS - documents.value.length);

    const confirmDeleteOpen = ref<boolean>(false);
    const documentToDelete = ref<Document | null>(null);

    const deleteConfirmMessage = computed<string>(() => {
        if (documentToDelete.value === null) {
            return '';
        }

        return `Le fichier « ${documentToDelete.value.filename} » sera supprimé définitivement. Cette action est irréversible.`;
    });

    async function onFilesAdded(files: File[]): Promise<void> {
        if (props.editing === null) {
            return;
        }

        const accepted = files.slice(0, remainingSlots.value);
        const uploaded = await uploadMany(props.editing.id, accepted);
        documents.value = [...uploaded, ...documents.value];

        if (uploaded.length > 0) {
            router.reload({ only: ['vehicleEvents'] });
        }
    }

    function requestDelete(doc: Document): void {
        documentToDelete.value = doc;
        confirmDeleteOpen.value = true;
    }

    async function confirmDelete(): Promise<void> {
        if (props.editing === null || documentToDelete.value === null) {
            return;
        }

        const docId = documentToDelete.value.id;
        confirmDeleteOpen.value = false;
        await deleteDocument(props.editing.id, docId);
        documents.value = documents.value.filter((d) => d.id !== docId);
        documentToDelete.value = null;
        router.reload({ only: ['vehicleEvents'] });
    }

    return {
        documents,
        uploading,
        canUploadMore,
        remainingSlots,
        confirmDeleteOpen,
        documentToDelete,
        deleteConfirmMessage,
        onFilesAdded,
        requestDelete,
        confirmDelete,
    };
}
