<script setup lang="ts">
import Button from '@/Components/Ui/Button/Button.vue';
import DateInput from '@/Components/Ui/DateInput/DateInput.vue';
import Modal from '@/Components/Ui/Modal/Modal.vue';
import SelectInput from '@/Components/Ui/SelectInput/SelectInput.vue';
import TextInput from '@/Components/Ui/TextInput/TextInput.vue';
import { useVfcEditForm } from '@/Composables/Vehicle/Show/useVfcEditForm';
import FiscalCharacteristicsSection from '@/pages/User/Vehicles/Edit/partials/FiscalCharacteristicsSection.vue';

type Vfc = App.Data.User.Vehicle.VehicleFiscalCharacteristicsData;

const props = defineProps<{
    editing: Vfc | null;
    options: App.Data.User.Vehicle.VehicleFormOptionsData;
}>();

const open = defineModel<boolean>('open', { required: true });

const {
    form,
    changeReasonOptions,
    isOtherChange,
    canSubmit,
    submit,
} = useVfcEditForm(props, open);
</script>

<template>
    <Modal
        v-model:open="open"
        title="Modifier la version fiscale"
        description="Édition libre des bornes et des champs fiscaux. Le moteur ajuste automatiquement les versions adjacentes en cas de chevauchement ou de trou."
        size="lg"
    >
        <form class="flex flex-col gap-5" @submit.prevent="submit">
            <section class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <DateInput
                    v-model="form.effective_from"
                    label="Date de début"
                    :error="form.errors.effective_from"
                    required
                />
                <DateInput
                    v-model="form.effective_to"
                    label="Date de fin"
                    hint="Laisser vide pour transformer cette version en version courante."
                    :error="form.errors.effective_to"
                />
            </section>

            <FiscalCharacteristicsSection :form="form" :options="props.options" />

            <section class="flex flex-col gap-4 rounded-xl border border-slate-200 bg-slate-50/50 p-4">
                <p class="eyebrow">Motif du changement</p>
                <SelectInput
                    v-model="form.change_reason"
                    label="Motif"
                    :options="changeReasonOptions"
                    :error="form.errors.change_reason"
                    required
                />
                <TextInput
                    v-if="isOtherChange"
                    v-model="form.change_note"
                    label="Note explicative"
                    hint="Précisez la nature du changement (motif « Autre changement »)."
                    :error="form.errors.change_note"
                    required
                />
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
                Enregistrer
            </Button>
        </template>
    </Modal>
</template>
