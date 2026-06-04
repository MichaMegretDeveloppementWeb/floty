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
    /** Files queued in create mode, uploaded atomically with the event. */
    pendingFiles: Ref<File[]>;
    uploading: Ref<boolean>;
    canUploadMore: ComputedRef<boolean>;
    remainingSlots: ComputedRef<number>;
    confirmDeleteOpen: Ref<boolean>;
    documentToDelete: Ref<Document | null>;
    deleteConfirmMessage: ComputedRef<string>;
    onFilesAdded: (files: File[]) => Promise<void>;
    removePending: (index: number) => void;
    resetPending: () => void;
    requestDelete: (doc: Document) => void;
    confirmDelete: () => Promise<void>;
};

/**
 * Form-side handling of a vehicle event's supporting documents (used by the
 * dedicated create/edit pages).
 *
 * Contract:
 *   - Initialises `documents` from `editing.documents` (create → empty, edit → server docs).
 *   - Create mode: files are QUEUED in `pendingFiles` and uploaded atomically
 *     with the event (multipart create request).
 *   - Edit mode: files are uploaded immediately to the existing event; the
 *     local `documents` ref is updated in place (no reload needed on the page).
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
    const pendingFiles = ref<File[]>([]);

    watch(
        () => props.editing,
        (value) => {
            documents.value = value !== null ? [...value.documents] : [];
            pendingFiles.value = [];
        },
        { immediate: true },
    );

    // Used slots: persisted documents in edit mode, queued files in create mode.
    const usedSlots = computed<number>(() =>
        props.editing !== null ? documents.value.length : pendingFiles.value.length,
    );
    const canUploadMore = computed<boolean>(() => usedSlots.value < MAX_DOCUMENTS);
    const remainingSlots = computed<number>(() => MAX_DOCUMENTS - usedSlots.value);

    const confirmDeleteOpen = ref<boolean>(false);
    const documentToDelete = ref<Document | null>(null);

    const deleteConfirmMessage = computed<string>(() => {
        if (documentToDelete.value === null) {
            return '';
        }

        return `Le fichier « ${documentToDelete.value.filename} » sera supprimé définitivement. Cette action est irréversible.`;
    });

    async function onFilesAdded(files: File[]): Promise<void> {
        const accepted = files.slice(0, remainingSlots.value);

        // Create mode: queue the files; they are uploaded atomically with
        // the event via the multipart create request (no id exists yet).
        if (props.editing === null) {
            pendingFiles.value = [...pendingFiles.value, ...accepted];

            return;
        }

        const uploaded = await uploadMany(props.editing.id, accepted);
        documents.value = [...uploaded, ...documents.value];
    }

    function removePending(index: number): void {
        pendingFiles.value = pendingFiles.value.filter((_, i) => i !== index);
    }

    function resetPending(): void {
        pendingFiles.value = [];
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
    }

    return {
        documents,
        pendingFiles,
        uploading,
        canUploadMore,
        remainingSlots,
        confirmDeleteOpen,
        documentToDelete,
        deleteConfirmMessage,
        onFilesAdded,
        removePending,
        resetPending,
        requestDelete,
        confirmDelete,
    };
}
