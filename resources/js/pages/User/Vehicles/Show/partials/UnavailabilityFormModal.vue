<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import Button from '@/Components/Ui/Button/Button.vue';
import DateInput from '@/Components/Ui/DateInput/DateInput.vue';
import Modal from '@/Components/Ui/Modal/Modal.vue';
import SelectInput from '@/Components/Ui/SelectInput/SelectInput.vue';
import TextInput from '@/Components/Ui/TextInput/TextInput.vue';
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
}>();

const open = defineModel<boolean>('open', { required: true });

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

watch(
    () => props.editing,
    (value) => {
        if (value) {
            form.type = value.type;
            form.start_date = value.startDate;
            form.end_date = value.endDate ?? '';
            form.description = value.description ?? '';
        } else {
            form.reset();
            form.type = 'maintenance';
        }

        form.clearErrors();
    },
);

const isEditing = computed<boolean>(() => props.editing !== null);

const payloadTransform = (data: {
    type: App.Enums.Unavailability.UnavailabilityType;
    start_date: string;
    end_date: string;
    description: string;
}): Record<string, unknown> => ({
    type: data.type,
    start_date: data.start_date,
    end_date: data.end_date === '' ? null : data.end_date,
    description: data.description === '' ? null : data.description,
});

const submit = (): void => {
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
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <DateInput
                    v-model="form.start_date"
                    label="Date de début"
                    :error="form.errors.start_date"
                    required
                />
                <DateInput
                    v-model="form.end_date"
                    label="Date de fin"
                    hint="Laisser vide si l'indispo est en cours."
                    :error="form.errors.end_date"
                />
            </div>
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
                @click="submit"
            >
                {{ isEditing ? 'Enregistrer' : 'Ajouter' }}
            </Button>
        </template>
    </Modal>
</template>
