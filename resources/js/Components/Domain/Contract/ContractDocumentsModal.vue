<script setup lang="ts">
/**
 * Modale de gestion des PDF en queue avant création d'une location
 * (chantier UX-Loc — utilisée par le drawer planning).
 *
 * Maintient une queue locale de fichiers File[] qu'on remonte au
 * parent via `update:files`. Le parent (drawer ou page) est
 * responsable de :
 *   - Soit persister via `storePendingDocuments` (page Create) avant
 *     un submit Inertia qui redirige vers Show.
 *   - Soit upload synchroneously après réception de `createdIds`
 *     dans la réponse (drawer planning, qui ne redirige pas).
 *
 * Reuse du `DocumentDropZone` UI primitif et de la même logique de
 * limite (`MAX_DOCUMENTS`, `MAX_SIZE_BYTES`) que la page Create.
 */
import { FileText, X } from 'lucide-vue-next';
import Button from '@/Components/Ui/Button/Button.vue';
import DocumentDropZone from '@/Components/Ui/DocumentDropZone/DocumentDropZone.vue';
import Modal from '@/Components/Ui/Modal/Modal.vue';

const MAX_DOCUMENTS = 5;
const MAX_SIZE_BYTES = 10 * 1024 * 1024;

const props = defineProps<{
    files: File[];
}>();

const open = defineModel<boolean>('open', { required: true });

const emit = defineEmits<{
    'update:files': [files: File[]];
    close: [];
}>();

function onFilesAdded(added: File[]): void {
    const remaining = MAX_DOCUMENTS - props.files.length;
    emit('update:files', [...props.files, ...added.slice(0, remaining)]);
}

function removeFile(index: number): void {
    emit('update:files', props.files.filter((_, i) => i !== index));
}

function close(): void {
    open.value = false;
    emit('close');
}
</script>

<template>
    <Modal
        v-model:open="open"
        title="Joindre des documents"
        description="Jusqu'à 5 PDF (10 Mo max chacun). Les documents seront uploadés après la création de la location."
        size="md"
        @close="emit('close')"
    >
        <div class="flex flex-col gap-4">
            <ul
                v-if="files.length > 0"
                class="flex flex-col gap-2"
            >
                <li
                    v-for="(file, idx) in files"
                    :key="idx"
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
                            {{ file.name }}
                        </p>
                        <p class="text-xs text-slate-500">
                            {{ (file.size / (1024 * 1024)).toFixed(1) }} Mo
                        </p>
                    </div>
                    <button
                        type="button"
                        class="inline-flex h-7 w-7 items-center justify-center rounded-md text-slate-500 transition-colors duration-[120ms] ease-out hover:bg-rose-100 hover:text-rose-700"
                        :title="`Retirer ${file.name}`"
                        :aria-label="`Retirer ${file.name}`"
                        @click="removeFile(idx)"
                    >
                        <X :size="14" :stroke-width="1.75" />
                    </button>
                </li>
            </ul>

            <DocumentDropZone
                v-if="files.length < MAX_DOCUMENTS"
                accept="application/pdf"
                :max-size-bytes="MAX_SIZE_BYTES"
                :max-files="MAX_DOCUMENTS - files.length"
                multiple
                @files-added="onFilesAdded"
            />

            <p
                v-else
                class="text-xs text-slate-500"
            >
                Limite de {{ MAX_DOCUMENTS }} fichiers atteinte.
            </p>

            <div class="flex justify-end">
                <Button type="button" @click="close">
                    Terminé
                </Button>
            </div>
        </div>
    </Modal>
</template>
