<script setup lang="ts">
/**
 * Modal to attach a new driver to a company from the Company Show page.
 * Reuses the POST /drivers/{driver}/memberships route with company_id in the payload.
 */
import { useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import Button from '@/Components/Ui/Button/Button.vue';
import DateInput from '@/Components/Ui/DateInput/DateInput.vue';
import FieldLabel from '@/Components/Ui/FieldLabel/FieldLabel.vue';
import InputError from '@/Components/Ui/InputError/InputError.vue';
import Modal from '@/Components/Ui/Modal/Modal.vue';
import SelectInput from '@/Components/Ui/SelectInput/SelectInput.vue';
import { filterAvailableDrivers } from '@/Composables/Driver/membershipPickers';
import { store as storeRoute } from '@/routes/user/drivers/memberships';

type DriverOption = { id: number; fullName: string; initials: string };

const props = defineProps<{
    companyId: number;
    existingDriverIds: number[];
    availableDrivers: DriverOption[];
}>();

const emit = defineEmits<{ close: [] }>();

const open = ref(true);

const form = useForm({
    driver_id: null as number | null,
    company_id: props.companyId,
    joined_at: new Date().toISOString().slice(0, 10),
});

const driverOptions = computed(() =>
    filterAvailableDrivers(props.availableDrivers, props.existingDriverIds),
);

const noOptions = computed<boolean>(() => driverOptions.value.length === 0);

function close(): void {
    open.value = false;
    emit('close');
}

function submit(): void {
    if (form.driver_id === null) {
        return;
    }

    form.post(storeRoute.url(form.driver_id), {
        preserveScroll: true,
        onSuccess: () => close(),
    });
}
</script>

<template>
    <Modal
        v-model:open="open"
        title="Ajouter un conducteur"
        @close="emit('close')"
    >
        <form class="flex flex-col gap-4" @submit.prevent="submit">
            <div>
                <FieldLabel for="add-driver-id">Conducteur</FieldLabel>
                <SelectInput
                    id="add-driver-id"
                    v-model="form.driver_id"
                    placeholder="Sélectionner un conducteur"
                    :options="driverOptions"
                    :disabled="noOptions"
                />
                <p
                    v-if="noOptions"
                    class="mt-1 text-xs text-slate-500"
                >
                    Tous les conducteurs sont déjà rattachés à cette entreprise.
                </p>
                <InputError :message="form.errors.driver_id" />
            </div>
            <div>
                <FieldLabel for="add-joined-at">Date d'entrée</FieldLabel>
                <DateInput id="add-joined-at" v-model="form.joined_at" />
                <InputError :message="form.errors.joined_at" />
            </div>
            <div class="flex justify-end gap-2">
                <Button variant="ghost" type="button" @click="close">
                    Annuler
                </Button>
                <Button
                    type="submit"
                    :loading="form.processing"
                    :disabled="noOptions || form.driver_id === null"
                >
                    Ajouter
                </Button>
            </div>
        </form>
    </Modal>
</template>
