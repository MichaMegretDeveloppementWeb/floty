<script setup lang="ts">
import Button from '@/Components/Ui/Button/Button.vue';
import InputError from '@/Components/Ui/InputError/InputError.vue';
import Modal from '@/Components/Ui/Modal/Modal.vue';
import { useVfcDeleteForm } from '@/Composables/Vehicle/Show/useVfcDeleteForm';
import { formatDateFr } from '@/Utils/format/formatDateFr';

type Vfc = App.Data.User.Vehicle.VehicleFiscalCharacteristicsData;

const props = defineProps<{
    deleting: Vfc | null;
}>();

const open = defineModel<boolean>('open', { required: true });

const {
    form,
    strategyOptions,
    canSubmit,
    submit,
} = useVfcDeleteForm(props, open);
</script>

<template>
    <Modal
        v-model:open="open"
        title="Supprimer la version fiscale"
        size="md"
    >
        <div class="flex flex-col gap-4">
            <p class="text-sm text-slate-700">
                Vous êtes sur le point de supprimer
                <span v-if="props.deleting" class="font-medium text-slate-900">
                    la version du {{ formatDateFr(props.deleting.effectiveFrom) }}
                    <template v-if="props.deleting.effectiveTo">
                        au {{ formatDateFr(props.deleting.effectiveTo) }}
                    </template>
                    <template v-else>
                        (courante)
                    </template>
                </span>.
                Cette action est irréversible.
            </p>

            <fieldset class="flex flex-col gap-2">
                <legend class="text-sm font-medium text-slate-700">
                    Que faire de la période supprimée ?
                </legend>
                <label
                    v-for="option in strategyOptions"
                    :key="option.value"
                    class="flex cursor-pointer items-start gap-3 rounded-lg border border-slate-200 bg-white px-3 py-2.5"
                >
                    <input
                        v-model="form.extension_strategy"
                        type="radio"
                        name="extension_strategy"
                        :value="option.value"
                        class="mt-1"
                    />
                    <span class="text-sm text-slate-800">{{ option.label }}</span>
                </label>
                <InputError v-if="form.errors.extension_strategy" :message="form.errors.extension_strategy" />
            </fieldset>
        </div>

        <template #footer>
            <Button
                variant="ghost"
                :disabled="form.processing"
                @click="open = false"
            >
                Annuler
            </Button>
            <Button
                variant="destructive"
                :loading="form.processing"
                :disabled="!canSubmit"
                @click="submit"
            >
                Confirmer la suppression
            </Button>
        </template>
    </Modal>
</template>
