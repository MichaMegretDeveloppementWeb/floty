import { router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import type { ComputedRef, Ref } from 'vue';
import { useUnavailabilityDocuments } from '@/Composables/Unavailability/useUnavailabilityDocuments';

type Unavailability = App.Data.User.Unavailability.UnavailabilityData;
type Document = App.Data.User.Unavailability.UnavailabilityDocumentData;

/**
 * Limites alignées avec le backend (`UploadUnavailabilityDocumentData`
 * FormRequest + `UploadUnavailabilityDocumentAction`). Exposées pour
 * que la modale puisse les afficher (compteur « N / 5 », label « 5 Mo
 * max », `accept` attribut sur l'input).
 */
export const MAX_DOCUMENTS = 5;
export const MAX_DOCUMENT_SIZE_BYTES = 5 * 1024 * 1024;
export const ACCEPTED_DOCUMENT_MIMES = 'application/pdf,image/jpeg,image/png,image/webp';

export type UseUnavailabilityFormDocumentsReturn = {
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
 * Gestion des justificatifs joints à une indisponibilité côté formulaire
 * (P1). Sort toute la logique de la modale Vue conformément à la règle
 * « strict minimum dans les .vue · tout (logique, données, init,
 * constantes, helpers) sort en composable ».
 *
 * **Contrat de fonctionnement** ·
 *   - Initialise `documents` depuis `editing.documents` à chaque
 *     changement (création → liste vide, édition → docs serveur).
 *   - Upload séquentiel des fichiers ajoutés (cf. `uploadMany`).
 *   - Refresh partiel Inertia (`unavailabilities`) après mutation
 *     pour garder le payload server cohérent · permet à l'utilisateur
 *     de fermer/rouvrir la modale sans perdre les justificatifs.
 *   - Suppression à 2 étapes (confirmation modale avant DELETE) pour
 *     éviter une perte accidentelle.
 *
 * **Précondition** · `editing` doit être non-null avant tout upload/
 * delete · une indispo doit exister en BDD pour qu'on puisse y joindre
 * un document. La modale gère ce gating côté template (`v-if="isEditing"`).
 */
export function useUnavailabilityFormDocuments(props: {
    editing: Unavailability | null;
}): UseUnavailabilityFormDocumentsReturn {
    const { uploading, uploadMany, deleteDocument } = useUnavailabilityDocuments();

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
            router.reload({ only: ['unavailabilities'] });
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
        router.reload({ only: ['unavailabilities'] });
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
