<script setup lang="ts">
/**
 * Modal d'ajout d'une membership Driver↔Company depuis la fiche
 * Driver Show (chantier M.1, ADR-0020 D3).
 *
 * Le picker de company est peuplé par la prop `availableCompanies`
 * exposée par `DriverController::show` via `options.companies`. Les
 * companies déjà rattachées au driver (peu importe statut) sont
 * filtrées pour éviter de proposer une membership active dupliquée.
 *
 * Pattern symétrique à `AddCompanyDriverModal` (chantier M.2).
 */
import { useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import Button from '@/Components/Ui/Button/Button.vue';
import DateInput from '@/Components/Ui/DateInput/DateInput.vue';
import FieldLabel from '@/Components/Ui/FieldLabel/FieldLabel.vue';
import InputError from '@/Components/Ui/InputError/InputError.vue';
import Modal from '@/Components/Ui/Modal/Modal.vue';
import SelectInput from '@/Components/Ui/SelectInput/SelectInput.vue';
import { filterAvailableCompanies } from '@/Composables/Driver/membershipPickers';
import { store as storeRoute } from '@/routes/user/drivers/memberships';

type CompanyOption = { id: number; shortCode: string; legalName: string };

const props = defineProps<{
    driverId: number;
    existingCompanyIds: number[];
    availableCompanies: CompanyOption[];
}>();

const emit = defineEmits<{ close: [] }>();

const open = ref(true);

const form = useForm({
    company_id: null as number | null,
    joined_at: new Date().toISOString().slice(0, 10),
});

const companyOptions = computed(() =>
    filterAvailableCompanies(props.availableCompanies, props.existingCompanyIds),
);

const noOptions = computed<boolean>(() => companyOptions.value.length === 0);

function close(): void {
    open.value = false;
    emit('close');
}

function submit(): void {
    form.post(storeRoute(props.driverId).url, {
        preserveScroll: true,
        onSuccess: () => close(),
    });
}
</script>

<template>
    <Modal
        v-model:open="open"
        title="Ajouter une entreprise"
        @close="emit('close')"
    >
        <form class="flex flex-col gap-4" @submit.prevent="submit">
            <div>
                <FieldLabel for="add-company-id">Entreprise</FieldLabel>
                <SelectInput
                    id="add-company-id"
                    v-model="form.company_id"
                    placeholder="Sélectionner une entreprise"
                    :options="companyOptions"
                    :disabled="noOptions"
                />
                <p
                    v-if="noOptions"
                    class="mt-1 text-xs text-slate-500"
                >
                    Toutes les entreprises sont déjà rattachées à ce conducteur.
                </p>
                <InputError :message="form.errors.company_id" />
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
                    :disabled="noOptions || form.company_id === null"
                >
                    Ajouter
                </Button>
            </div>
        </form>
    </Modal>
</template>
