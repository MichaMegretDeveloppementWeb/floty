<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Download, FileText, Plus, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import Button from '@/Components/Ui/Button/Button.vue';
import Card from '@/Components/Ui/Card/Card.vue';
import ConfirmModal from '@/Components/Ui/ConfirmModal/ConfirmModal.vue';
import DocumentDropZone from '@/Components/Ui/DocumentDropZone/DocumentDropZone.vue';
import Modal from '@/Components/Ui/Modal/Modal.vue';
import { useContractDocuments } from '@/Composables/Contract/useContractDocuments';
import { formatDateFr } from '@/Utils/format/formatDateFr';

type Document = App.Data.User.Contract.ContractDocumentData;

const props = defineProps<{
    contractId: number;
    documents: Document[];
}>();

const MAX_DOCUMENTS = 5;
const MAX_SIZE_BYTES = 10 * 1024 * 1024; // 10 Mo

const { uploading, uploadMany, deleteDocument } = useContractDocuments();

const remainingSlots = computed<number>(() => MAX_DOCUMENTS - props.documents.length);
const isFull = computed<boolean>(() => remainingSlots.value <= 0);

const uploadModalOpen = ref<boolean>(false);
const documentToDelete = ref<Document | null>(null);
const deleting = ref<boolean>(false);

const confirmDeleteOpen = computed<boolean>({
    get: () => documentToDelete.value !== null,
    set: (value) => {
        if (!value) {
            documentToDelete.value = null;
        }
    },
});

function openUploadModal(): void {
    if (isFull.value) {
        return;
    }

    uploadModalOpen.value = true;
}

async function onFilesAdded(files: File[]): Promise<void> {
    // Limite côté client : ne dépasse pas remainingSlots
    const toUpload = files.slice(0, remainingSlots.value);

    await uploadMany(props.contractId, toUpload);

    uploadModalOpen.value = false;
    router.reload({ only: ['documents'] });
}

function requestDelete(doc: Document): void {
    documentToDelete.value = doc;
}

async function confirmDelete(): Promise<void> {
    if (documentToDelete.value === null) {
        return;
    }

    deleting.value = true;

    try {
        await deleteDocument(props.contractId, documentToDelete.value.id);
        documentToDelete.value = null;
        router.reload({ only: ['documents'] });
    } catch {
        // Toast erreur déjà affiché par useApi
    } finally {
        deleting.value = false;
    }
}
</script>

<template>
    <Card>
        <template #header>
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-base font-semibold text-slate-900">
                        Documents
                    </h2>
                    <p class="mt-0.5 text-xs text-slate-500">
                        {{ documents.length }} / {{ MAX_DOCUMENTS }} document{{
                            MAX_DOCUMENTS > 1 ? 's' : ''
                        }} joint{{ documents.length > 1 ? 's' : '' }}
                        — PDF uniquement, 10 Mo max par fichier.
                    </p>
                </div>
                <Button
                    type="button"
                    :disabled="isFull"
                    @click="openUploadModal"
                >
                    <Plus :size="14" :stroke-width="2" />
                    Ajouter un document
                </Button>
            </div>
        </template>

        <p
            v-if="documents.length === 0"
            class="text-sm text-slate-500"
        >
            Aucun document joint à ce contrat.
        </p>

        <ul
            v-else
            class="flex flex-col gap-2"
        >
            <li
                v-for="doc in documents"
                :key="doc.id"
                class="flex items-center gap-3 rounded-lg border border-slate-100 bg-slate-50 px-3 py-2"
            >
                <FileText
                    :size="20"
                    :stroke-width="1.75"
                    class="shrink-0 text-rose-500"
                    aria-hidden="true"
                />
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium text-slate-900">
                        {{ doc.filename }}
                    </p>
                    <p class="text-xs text-slate-500">
                        {{ doc.sizeFormatted }}
                        <span class="text-slate-300">·</span>
                        Ajouté le {{ formatDateFr(doc.uploadedAt.slice(0, 10)) }}
                    </p>
                </div>
                <a
                    :href="doc.downloadUrl"
                    class="inline-flex h-8 w-8 items-center justify-center rounded-md text-slate-500 transition-colors duration-[120ms] ease-out hover:bg-slate-200 hover:text-slate-900"
                    :title="`Télécharger ${doc.filename}`"
                    :aria-label="`Télécharger ${doc.filename}`"
                >
                    <Download :size="16" :stroke-width="1.75" />
                </a>
                <button
                    type="button"
                    class="inline-flex h-8 w-8 items-center justify-center rounded-md text-slate-500 transition-colors duration-[120ms] ease-out hover:bg-rose-100 hover:text-rose-700"
                    :title="`Supprimer ${doc.filename}`"
                    :aria-label="`Supprimer ${doc.filename}`"
                    @click="requestDelete(doc)"
                >
                    <Trash2 :size="16" :stroke-width="1.75" />
                </button>
            </li>
        </ul>

        <Modal
            v-model:open="uploadModalOpen"
            title="Ajouter un document"
            :description="`PDF uniquement, 10 Mo max. Vous pouvez ajouter ${remainingSlots} fichier${remainingSlots > 1 ? 's' : ''} de plus.`"
            size="md"
        >
            <DocumentDropZone
                accept="application/pdf"
                :max-size-bytes="MAX_SIZE_BYTES"
                :max-files="remainingSlots"
                :disabled="uploading"
                multiple
                @files-added="onFilesAdded"
            />
            <p
                v-if="uploading"
                class="mt-3 text-center text-sm text-slate-600"
            >
                Upload en cours…
            </p>
        </Modal>

        <ConfirmModal
            v-model:open="confirmDeleteOpen"
            title="Supprimer ce document"
            :message="`Voulez-vous vraiment supprimer « ${documentToDelete?.filename ?? ''} » ? Cette action est irréversible (le fichier physique est immédiatement effacé).`"
            confirm-label="Supprimer"
            tone="danger"
            :loading="deleting"
            @confirm="confirmDelete"
            @cancel="documentToDelete = null"
        />
    </Card>
</template>
