<script setup lang="ts">
import { Download, FileText, ImageIcon, Trash2 } from 'lucide-vue-next';
import Button from '@/Components/Ui/Button/Button.vue';
import CheckboxInput from '@/Components/Ui/CheckboxInput/CheckboxInput.vue';
import ConfirmModal from '@/Components/Ui/ConfirmModal/ConfirmModal.vue';
import DateRangePicker from '@/Components/Ui/DateRangePicker/DateRangePicker.vue';
import DocumentDropZone from '@/Components/Ui/DocumentDropZone/DocumentDropZone.vue';
import InputError from '@/Components/Ui/InputError/InputError.vue';
import Modal from '@/Components/Ui/Modal/Modal.vue';
import TextInput from '@/Components/Ui/TextInput/TextInput.vue';
import { useToasts } from '@/Composables/Shared/useToasts';
import {
    ACCEPTED_DOCUMENT_MIMES,
    MAX_DOCUMENT_SIZE_BYTES,
    MAX_DOCUMENTS,
    useUnavailabilityFormDocuments,
} from '@/Composables/Unavailability/useUnavailabilityFormDocuments';
import { useUnavailabilityForm } from '@/Composables/Vehicle/Show/useUnavailabilityForm';

const toasts = useToasts();

type Unavailability = App.Data.User.Unavailability.UnavailabilityData;

const props = defineProps<{
    vehicleId: number;
    /** null = mode création, sinon mode édition. */
    editing: Unavailability | null;
    /** Dates ISO Y-m-d déjà attribuées au véhicule (calendrier les grise). */
    busyDates: string[];
}>();

const open = defineModel<boolean>('open', { required: true });

const {
    optionGroups,
    viewYear,
    form,
    range,
    ongoing,
    initialMonth,
    isEditing,
    canSubmit,
    selectedIsReductive,
    conflictDaysCount,
    submit,
} = useUnavailabilityForm(props, open);

const {
    documents,
    uploading,
    canUploadMore,
    remainingSlots,
    confirmDeleteOpen,
    deleteConfirmMessage,
    onFilesAdded,
    requestDelete,
    confirmDelete,
} = useUnavailabilityFormDocuments(props);
</script>

<template>
    <Modal
        v-model:open="open"
        :title="isEditing ? 'Modifier l\'indisponibilité' : 'Ajouter une indisponibilité'"
        size="md"
    >
        <form class="flex flex-col gap-4" @submit.prevent="submit">
            <div class="flex flex-col gap-1.5">
                <label
                    for="unavailability-type"
                    class="text-sm font-medium text-slate-500"
                >
                    Type d'indisponibilité
                    <span aria-hidden="true" class="ml-0.5 text-rose-600">*</span>
                </label>
                <select
                    id="unavailability-type"
                    v-model="form.type"
                    required
                    class="w-full rounded-md border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100 focus:outline-none"
                >
                    <optgroup
                        v-for="group in optionGroups"
                        :key="group.label"
                        :label="group.label"
                    >
                        <option
                            v-for="option in group.options"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </optgroup>
                </select>
                <InputError v-if="form.errors.type" :message="form.errors.type" />
            </div>

            <div
                :class="[
                    'rounded-lg border px-3 py-2.5 text-xs leading-snug',
                    selectedIsReductive
                        ? 'border-emerald-200 bg-emerald-50/60 text-emerald-800'
                        : 'border-slate-200 bg-slate-50/60 text-slate-600',
                ]"
                role="status"
                aria-live="polite"
            >
                <p v-if="selectedIsReductive">
                    Cette indisponibilité <strong>réduira</strong> le numérateur
                    du prorata fiscal sur la période concernée.
                </p>
                <p v-else>
                    Cette indisponibilité <strong>ne réduit pas</strong> le
                    numérateur du prorata fiscal. Le véhicule reste considéré
                    comme affecté à l'entreprise pendant la période.
                </p>
            </div>

            <div class="flex flex-col gap-2">
                <span class="text-sm font-medium text-slate-500">
                    Période
                    <span aria-hidden="true" class="ml-0.5 text-rose-600">*</span>
                </span>
                <div class="rounded-lg border border-slate-200 p-3">
                    <DateRangePicker
                        v-model:range="range"
                        v-model:ongoing="ongoing"
                        :year="viewYear"
                        :start-month="initialMonth"
                    />
                </div>
                <InputError v-if="form.errors.start_date" :message="form.errors.start_date" />
                <InputError v-if="form.errors.end_date" :message="form.errors.end_date" />

                <div
                    v-if="conflictDaysCount > 0"
                    :class="[
                        'rounded-lg border px-3 py-2.5 text-xs leading-snug',
                        selectedIsReductive
                            ? 'border-amber-200 bg-amber-50/60 text-amber-800'
                            : 'border-slate-200 bg-slate-50/60 text-slate-600',
                    ]"
                    role="status"
                    aria-live="polite"
                >
                    <p v-if="selectedIsReductive">
                        Cette plage chevauche
                        <strong>{{ conflictDaysCount }}</strong>
                        jour{{ conflictDaysCount > 1 ? 's' : '' }}
                        déjà attribué{{ conflictDaysCount > 1 ? 's' : '' }}
                        à une location. L'indisponibilité
                        <strong>réduira</strong> le prorata fiscal
                        des locations concernées (R-2024-008).
                    </p>
                    <p v-else>
                        Cette plage chevauche
                        <strong>{{ conflictDaysCount }}</strong>
                        jour{{ conflictDaysCount > 1 ? 's' : '' }}
                        déjà attribué{{ conflictDaysCount > 1 ? 's' : '' }}
                        à une location.
                        <strong>Aucun impact</strong> sur le calcul fiscal.
                    </p>
                </div>
            </div>

            <CheckboxInput
                v-model="ongoing"
                label="Indisponibilité en cours (sans date de fin)"
                hint="Cochez si la date de retour n'est pas encore connue. Bloque toute attribution future jusqu'à la clôture."
            />

            <TextInput
                v-model="form.description"
                label="Description"
                hint="Optionnel. Précisez le contexte si utile."
                :error="form.errors.description"
            />

            <!--
                Up to 5 attached documents (images or PDF, 5 MB each).
                Edit mode only: in create mode the unavailability does
                not exist yet, so the user is told they will be able to
                attach proofs after creation.
            -->
            <section class="flex flex-col gap-2 border-t border-slate-100 pt-4">
                <div class="flex items-baseline justify-between gap-2">
                    <p class="text-sm font-medium text-slate-700">
                        Justificatifs
                    </p>
                    <p v-if="isEditing" class="text-xs text-slate-500">
                        {{ documents.length }} / {{ MAX_DOCUMENTS }}
                    </p>
                </div>

                <p
                    v-if="!isEditing"
                    class="rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-600"
                >
                    Les justificatifs (images ou PDF, 5 fichiers max, 5 Mo
                    chacun) pourront être ajoutés après la création.
                </p>

                <template v-else>
                    <ul
                        v-if="documents.length > 0"
                        class="flex flex-col gap-2"
                    >
                        <li
                            v-for="doc in documents"
                            :key="doc.id"
                            class="flex items-center gap-3 rounded-lg border border-slate-200 bg-white px-3 py-2"
                        >
                            <span
                                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-slate-100 text-slate-600"
                                aria-hidden="true"
                            >
                                <ImageIcon v-if="doc.isImage" :size="16" :stroke-width="1.75" />
                                <FileText v-else :size="16" :stroke-width="1.75" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium text-slate-900">
                                    {{ doc.filename }}
                                </p>
                                <p class="text-xs text-slate-500">
                                    {{ doc.sizeFormatted }}
                                </p>
                            </div>
                            <a
                                :href="doc.downloadUrl"
                                class="inline-flex h-7 w-7 cursor-pointer items-center justify-center rounded-md text-slate-500 transition-colors duration-[120ms] ease-out hover:bg-slate-100 hover:text-slate-900"
                                :title="`Télécharger ${doc.filename}`"
                                :aria-label="`Télécharger ${doc.filename}`"
                            >
                                <Download :size="14" :stroke-width="1.75" />
                            </a>
                            <button
                                type="button"
                                class="inline-flex h-7 w-7 cursor-pointer items-center justify-center rounded-md text-slate-500 transition-colors duration-[120ms] ease-out hover:bg-rose-100 hover:text-rose-700"
                                :title="`Supprimer ${doc.filename}`"
                                :aria-label="`Supprimer ${doc.filename}`"
                                @click="requestDelete(doc)"
                            >
                                <Trash2 :size="14" :stroke-width="1.75" />
                            </button>
                        </li>
                    </ul>

                    <DocumentDropZone
                        v-if="canUploadMore"
                        :accept="ACCEPTED_DOCUMENT_MIMES"
                        :max-size-bytes="MAX_DOCUMENT_SIZE_BYTES"
                        :max-files="remainingSlots"
                        multiple
                        @files-added="onFilesAdded"
                @rejected="(msg: string) => toasts.push({ tone: 'error', title: 'Fichier rejeté', description: msg })"
                    />

                    <p
                        v-else
                        class="rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-500"
                    >
                        Limite de {{ MAX_DOCUMENTS }} justificatifs atteinte.
                        Supprimez-en un pour en ajouter un nouveau.
                    </p>

                    <p
                        v-if="uploading"
                        class="text-xs italic text-slate-500"
                    >
                        Upload en cours…
                    </p>
                </template>
            </section>
        </form>

        <template #footer>
            <Button
                variant="ghost"
                :disabled="form.processing"
                @click="open = false"
            >
                Annuler
            </Button>
            <Button
                :loading="form.processing"
                :disabled="!canSubmit"
                @click="submit"
            >
                {{ isEditing ? 'Enregistrer' : 'Ajouter' }}
            </Button>
        </template>
    </Modal>

    <ConfirmModal
        v-model:open="confirmDeleteOpen"
        title="Supprimer ce justificatif ?"
        :message="deleteConfirmMessage"
        confirm-label="Supprimer"
        cancel-label="Annuler"
        tone="danger"
        @confirm="confirmDelete"
    />
</template>
