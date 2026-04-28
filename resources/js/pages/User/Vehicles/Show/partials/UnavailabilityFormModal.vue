<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import Button from '@/Components/Ui/Button/Button.vue';
import CheckboxInput from '@/Components/Ui/CheckboxInput/CheckboxInput.vue';
import DateRangePicker from '@/Components/Ui/DateRangePicker/DateRangePicker.vue';
import InputError from '@/Components/Ui/InputError/InputError.vue';
import Modal from '@/Components/Ui/Modal/Modal.vue';
import SelectInput from '@/Components/Ui/SelectInput/SelectInput.vue';
import TextInput from '@/Components/Ui/TextInput/TextInput.vue';
import { useFiscalYear } from '@/Composables/Shared/useFiscalYear';
import {
    store as unavailabilitiesStoreRoute,
    update as unavailabilitiesUpdateRoute,
} from '@/routes/user/unavailabilities';
import { unavailabilityTypeLabel } from '@/Utils/labels/unavailabilityEnumLabels';

type Unavailability = App.Data.User.Unavailability.UnavailabilityData;

const props = defineProps<{
    vehicleId: number;
    /** null = mode création, sinon mode édition. */
    editing: Unavailability | null;
    /** Dates ISO Y-m-d déjà attribuées au véhicule (calendrier les grise). */
    busyDates: string[];
}>();

const open = defineModel<boolean>('open', { required: true });

const { currentYear } = useFiscalYear();

const typeOptions = (
    Object.keys(unavailabilityTypeLabel) as App.Enums.Unavailability.UnavailabilityType[]
).map((value) => ({
    value,
    label: unavailabilityTypeLabel[value],
}));

const form = useForm<{
    type: App.Enums.Unavailability.UnavailabilityType;
    start_date: string;
    end_date: string;
    description: string;
}>({
    type: 'maintenance',
    start_date: '',
    end_date: '',
    description: '',
});

const range = ref<{ startDate: string | null; endDate: string | null }>({
    startDate: null,
    endDate: null,
});
const ongoing = ref<boolean>(false);

watch(
    () => props.editing,
    (value) => {
        if (value) {
            form.type = value.type;
            form.description = value.description ?? '';
            range.value = {
                startDate: value.startDate,
                endDate: value.endDate,
            };
            ongoing.value = value.endDate === null;
        } else {
            form.reset();
            form.type = 'maintenance';
            range.value = { startDate: null, endDate: null };
            ongoing.value = false;
        }

        form.clearErrors();
    },
);

const isEditing = computed<boolean>(() => props.editing !== null);

const canSubmit = computed<boolean>(() => {
    if (range.value.startDate === null) {
        return false;
    }

    if (!ongoing.value && range.value.endDate === null) {
        return false;
    }

    return true;
});

const payloadTransform = (data: {
    type: App.Enums.Unavailability.UnavailabilityType;
    description: string;
}): Record<string, unknown> => ({
    type: data.type,
    start_date: range.value.startDate,
    end_date: ongoing.value ? null : range.value.endDate,
    description: data.description === '' ? null : data.description,
});

const submit = (): void => {
    if (!canSubmit.value) {
        return;
    }

    if (isEditing.value && props.editing) {
        form.transform(payloadTransform).patch(
            unavailabilitiesUpdateRoute.url({ unavailability: props.editing.id }),
            {
                preserveScroll: true,
                onSuccess: () => {
                    open.value = false;
                },
            },
        );
    } else {
        form.transform((data) => ({
            ...payloadTransform(data),
            vehicle_id: props.vehicleId,
        })).post(unavailabilitiesStoreRoute.url(), {
            preserveScroll: true,
            onSuccess: () => {
                open.value = false;
                form.reset();
                form.type = 'maintenance';
                range.value = { startDate: null, endDate: null };
                ongoing.value = false;
            },
        });
    }
};
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
