<script setup lang="ts">
import Button from '@/Components/Ui/Button/Button.vue';
import CheckboxInput from '@/Components/Ui/CheckboxInput/CheckboxInput.vue';
import DateRangePicker from '@/Components/Ui/DateRangePicker/DateRangePicker.vue';
import InputError from '@/Components/Ui/InputError/InputError.vue';
import Modal from '@/Components/Ui/Modal/Modal.vue';
import SelectInput from '@/Components/Ui/SelectInput/SelectInput.vue';
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
    typeOptions,
    currentYear,
    form,
    range,
    ongoing,
    isEditing,
    canSubmit,
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
            <SelectInput
                v-model="form.type"
                label="Type"
                :options="typeOptions"
                :error="form.errors.type"
                hint="La fourrière est le seul type qui réduit le numérateur du prorata fiscal."
                required
            />

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
                hint="Optionnel — précisez le contexte si utile."
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
