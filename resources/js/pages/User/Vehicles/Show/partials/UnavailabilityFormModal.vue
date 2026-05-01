<script setup lang="ts">
import Button from '@/Components/Ui/Button/Button.vue';
import CheckboxInput from '@/Components/Ui/CheckboxInput/CheckboxInput.vue';
import DateRangePicker from '@/Components/Ui/DateRangePicker/DateRangePicker.vue';
import InputError from '@/Components/Ui/InputError/InputError.vue';
import Modal from '@/Components/Ui/Modal/Modal.vue';
import TextInput from '@/Components/Ui/TextInput/TextInput.vue';
import { useUnavailabilityForm } from '@/Composables/Vehicle/Show/useUnavailabilityForm';

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
    currentYear,
    form,
    range,
    ongoing,
    isEditing,
    canSubmit,
    selectedIsReductive,
    submit,
} = useUnavailabilityForm(props, open);
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
                    Cette indisponibilité <strong>n'a pas d'effet fiscal</strong>.
                    Le véhicule reste considéré comme affecté à l'entreprise
                    pendant cette période.
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
                        :year="currentYear"
                        :disabled-dates="props.busyDates"
                    />
                </div>
                <InputError v-if="form.errors.start_date" :message="form.errors.start_date" />
                <InputError v-if="form.errors.end_date" :message="form.errors.end_date" />
                <p class="text-xs text-slate-500">
                    Les jours déjà attribués au véhicule (barrés) ne peuvent
                    pas être inclus dans la plage.
                </p>
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
</template>
