<script setup lang="ts">
/**
 * Modal d'ajout d'une membership Driver↔Company depuis la fiche
 * Company Show (chantier M.2, ADR-0020 D3).
 *
 * Le picker de driver est peuplé par la prop `availableDrivers`
 * exposée par `CompanyController::show` via `options.drivers`. Les
 * drivers déjà rattachés à la company (peu importe statut) sont
 * filtrés pour éviter de proposer une membership active dupliquée.
 *
 * Réutilise la route POST /drivers/{driver}/memberships côté Driver
 * — le pivot est unique, pas besoin de doubler les endpoints. Le
 * driver_id sélectionné par l'utilisateur sert à construire l'URL,
 * et company_id voyage dans le payload.
 *
 * Pattern symétrique à `AddDriverCompanyModal` (chantier M.1).
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

    form.post(storeRoute(form.driver_id).url, {
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
