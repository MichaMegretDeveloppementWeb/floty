<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Download, FileText, ImageIcon, Trash2 } from 'lucide-vue-next';
import Button from '@/Components/Ui/Button/Button.vue';
import Card from '@/Components/Ui/Card/Card.vue';
import CheckboxInput from '@/Components/Ui/CheckboxInput/CheckboxInput.vue';
import ConfirmModal from '@/Components/Ui/ConfirmModal/ConfirmModal.vue';
import DateRangePicker from '@/Components/Ui/DateRangePicker/DateRangePicker.vue';
import DocumentDropZone from '@/Components/Ui/DocumentDropZone/DocumentDropZone.vue';
import EventCategoriesField from '@/Components/Ui/EventCategoriesField/EventCategoriesField.vue';
import InputError from '@/Components/Ui/InputError/InputError.vue';
import NumberInput from '@/Components/Ui/NumberInput/NumberInput.vue';
import TextInput from '@/Components/Ui/TextInput/TextInput.vue';
import { useToasts } from '@/Composables/Shared/useToasts';
import { useVehicleEventForm } from '@/Composables/Vehicle/Show/useVehicleEventForm';
import {
    ACCEPTED_DOCUMENT_MIMES,
    MAX_DOCUMENT_SIZE_BYTES,
    MAX_DOCUMENTS,
    useVehicleEventFormDocuments,
} from '@/Composables/VehicleEvent/useVehicleEventFormDocuments';
import { show as vehiclesShowRoute } from '@/routes/user/vehicles';

type VehicleEvent = App.Data.User.VehicleEvent.VehicleEventData;

const props = defineProps<{
    vehicleId: number;
    /** null = création, sinon édition. */
    editing: VehicleEvent | null;
    /** Dates ISO Y-m-d déjà attribuées au véhicule (indicatif). */
    busyDates: string[];
    /** ISO Y-m-d pré-sélectionnée en création (ajout depuis un jour de la timeline). */
    initialDate?: string | null;
    /** Catégories déjà saisies (back), pour l'auto-complétion. */
    categorySuggestions?: string[];
}>();

const toasts = useToasts();

const {
    documents,
    pendingFiles,
    uploading,
    canUploadMore,
    remainingSlots,
    confirmDeleteOpen,
    deleteConfirmMessage,
    onFilesAdded,
    removePending,
    requestDelete,
    confirmDelete,
} = useVehicleEventFormDocuments(props);

const {
    optionGroups,
    viewYear,
    form,
    range,
    ongoing,
    initialMonth,
    isEditing,
    isFixedDate,
    fixedDateLabel,
    isCustom,
    lockedDefaultCategories,
    categorySuggestions,
    canSubmit,
    selectedIsReductive,
    conflictDaysCount,
    amountError,
    submit,
} = useVehicleEventForm(
    {
        vehicleId: props.vehicleId,
        editing: props.editing,
        busyDates: props.busyDates,
        categorySuggestions: props.categorySuggestions,
    },
    { pendingDocuments: pendingFiles, initialDate: props.initialDate ?? undefined },
);

// Annuler / retour : l'onglet « Événements » du véhicule.
const backUrl = vehiclesShowRoute.url({ vehicle: props.vehicleId }, { query: { tab: 'events' } });
</script>

<template>
    <form class="flex flex-col gap-6" @submit.prevent="submit">
        <Card>
            <template #header>
                <h2 class="text-base font-semibold text-slate-900">
                    Type et nature
                </h2>
            </template>
            <div class="flex flex-col gap-4">
                <div class="flex flex-col gap-1.5">
                    <label
                        for="vehicle-event-type"
                        class="text-sm font-medium text-slate-500"
                    >
                        Type d'événement
                        <span aria-hidden="true" class="ml-0.5 text-rose-600">*</span>
                    </label>
                    <select
                        id="vehicle-event-type"
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
                                :class="{ 'font-semibold': option.value === 'other' }"
                            >
                                {{ option.label }}
                            </option>
                        </optgroup>
                    </select>
                    <InputError v-if="form.errors.type" :message="form.errors.type" />
                </div>

                <template v-if="isCustom">
                    <TextInput
                        v-model="form.title"
                        label="Nom de l'événement"
                        placeholder="Ex. Pose covering, prêt à un partenaire..."
                        :required="true"
                        :error="form.errors.title"
                    />
                </template>

                <EventCategoriesField
                    :model-value="form.categories"
                    :locked-defaults="lockedDefaultCategories"
                    :suggestions="categorySuggestions"
                    :max="5"
                    :error="form.errors.categories"
                    @update:model-value="(value: string[]) => (form.categories = value)"
                />

                <CheckboxInput
                    v-if="isCustom"
                    v-model="form.implies_unavailability"
                    label="Génère une indisponibilité du véhicule"
                    hint="Cochez si l'événement rend le véhicule indisponible : il sera signalé par un badge et compté dans la heatmap et l'utilisation."
                />

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
                        Cet événement <strong>réduira</strong> le numérateur
                        du prorata fiscal sur la période concernée.
                    </p>
                    <p v-else>
                        Cet événement <strong>ne réduit pas</strong> le
                        numérateur du prorata fiscal. Le véhicule reste considéré
                        comme affecté à l'entreprise pendant la période.
                    </p>
                </div>
            </div>
        </Card>

        <Card>
            <template #header>
                <h2 class="text-base font-semibold text-slate-900">
                    Période
                </h2>
            </template>

            <div v-if="isFixedDate" class="flex flex-col gap-1.5">
                <p class="text-sm text-slate-700">
                    Le <span class="font-medium text-slate-900">{{ fixedDateLabel }}</span>.
                </p>
                <InputError v-if="form.errors.start_date" :message="form.errors.start_date" />
                <InputError v-if="form.errors.end_date" :message="form.errors.end_date" />
            </div>

            <div v-else class="flex flex-col gap-4">
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

                <CheckboxInput
                    v-model="ongoing"
                    label="Événement en cours (sans date de fin)"
                    hint="Cochez si la date de retour n'est pas encore connue."
                />

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
                        à une location. L'événement
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
        </Card>

        <Card>
            <template #header>
                <h2 class="text-base font-semibold text-slate-900">
                    Montant
                </h2>
            </template>
            <NumberInput
                v-model="form.amount"
                label="Coût"
                :min="0"
                :step="0.01"
                placeholder="0,00"
                hint="Coût associé à cet événement (TTC), facultatif."
                :error="amountError"
            >
                <template #unit>€</template>
            </NumberInput>
        </Card>

        <Card>
            <template #header>
                <h2 class="text-base font-semibold text-slate-900">
                    Description
                </h2>
            </template>
            <div class="flex flex-col gap-2">
                <textarea
                    v-model="form.description"
                    rows="3"
                    maxlength="500"
                    placeholder="Optionnel. Précisez le contexte si utile."
                    class="w-full rounded-md border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100 focus:outline-none"
                />
                <InputError v-if="form.errors.description" :message="form.errors.description" />
            </div>
        </Card>

        <Card>
            <template #header>
                <div class="flex items-baseline justify-between gap-2">
                    <h2 class="text-base font-semibold text-slate-900">
                        Documents liés
                    </h2>
                    <p class="text-xs text-slate-500">
                        {{ isEditing ? documents.length : pendingFiles.length }} / {{ MAX_DOCUMENTS }}
                    </p>
                </div>
            </template>
            <div class="flex flex-col gap-3">
                <p class="text-xs text-slate-500">
                    Images ou PDF, {{ MAX_DOCUMENTS }} fichiers max, 5 Mo chacun.
                    <template v-if="!isEditing">
                        Ils seront enregistrés en même temps que l'événement.
                    </template>
                </p>

                <ul
                    v-if="isEditing && documents.length > 0"
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

                <ul
                    v-else-if="!isEditing && pendingFiles.length > 0"
                    class="flex flex-col gap-2"
                >
                    <li
                        v-for="(file, index) in pendingFiles"
                        :key="`${file.name}-${index}`"
                        class="flex items-center gap-3 rounded-lg border border-slate-200 bg-white px-3 py-2"
                    >
                        <span
                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-slate-100 text-slate-600"
                            aria-hidden="true"
                        >
                            <FileText :size="16" :stroke-width="1.75" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-slate-900">
                                {{ file.name }}
                            </p>
                            <p class="text-xs text-slate-500">
                                En attente d'enregistrement
                            </p>
                        </div>
                        <button
                            type="button"
                            class="inline-flex h-7 w-7 cursor-pointer items-center justify-center rounded-md text-slate-500 transition-colors duration-[120ms] ease-out hover:bg-rose-100 hover:text-rose-700"
                            :title="`Retirer ${file.name}`"
                            :aria-label="`Retirer ${file.name}`"
                            @click="removePending(index)"
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
                    Limite de {{ MAX_DOCUMENTS }} documents atteinte.
                    Supprimez-en un pour en ajouter un nouveau.
                </p>

                <p v-if="uploading" class="text-xs italic text-slate-500">
                    Upload en cours…
                </p>
            </div>
        </Card>

        <div class="flex items-center justify-end gap-3">
            <Link :href="backUrl">
                <Button variant="ghost" type="button" :disabled="form.processing">
                    Annuler
                </Button>
            </Link>
            <Button
                type="submit"
                :loading="form.processing"
                :disabled="!canSubmit"
            >
                {{ isEditing ? 'Enregistrer les modifications' : 'Enregistrer l\'événement' }}
            </Button>
        </div>
    </form>

    <ConfirmModal
        v-model:open="confirmDeleteOpen"
        title="Supprimer ce document ?"
        :message="deleteConfirmMessage"
        confirm-label="Supprimer"
        cancel-label="Annuler"
        tone="danger"
        @confirm="confirmDelete"
    />
</template>
