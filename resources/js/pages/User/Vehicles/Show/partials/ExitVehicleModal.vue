<script setup lang="ts">
import Button from '@/Components/Ui/Button/Button.vue';
import DateInput from '@/Components/Ui/DateInput/DateInput.vue';
import InputError from '@/Components/Ui/InputError/InputError.vue';
import Modal from '@/Components/Ui/Modal/Modal.vue';
import SelectInput from '@/Components/Ui/SelectInput/SelectInput.vue';
import { useExitVehicleForm } from '@/Composables/Vehicle/Show/useExitVehicleForm';

const props = defineProps<{
    vehicleId: number;
    licensePlate: string;
}>();

const open = defineModel<boolean>('open', { required: true });

const {
    reasonOptions,
    today,
    form,
    canSubmit,
    submit,
} = useExitVehicleForm(props, open);
</script>

<template>
    <Modal
        v-model:open="open"
        title="Retirer ce véhicule de la flotte"
        :description="`Sortie définitive du véhicule ${props.licensePlate}. Le véhicule restera consultable dans son historique mais n'apparaîtra plus dans les vues actives.`"
        size="md"
    >
        <form class="flex flex-col gap-4" @submit.prevent="submit">
            <DateInput
                v-model="form.exit_date"
                label="Date de sortie"
                :max="today"
                :error="form.errors.exit_date"
                hint="Date à laquelle le véhicule a effectivement quitté la flotte. Ne peut pas être dans le futur."
                required
            />

            <SelectInput
                v-model="(form.exit_reason as App.Enums.Vehicle.VehicleExitReason | '')"
                label="Motif de sortie"
                :options="reasonOptions"
                placeholder="Sélectionner un motif"
                :error="form.errors.exit_reason"
                required
            />

            <div class="flex flex-col gap-1.5">
                <label
                    for="exit_note"
                    class="text-sm font-medium text-slate-500"
                >
                    Note (optionnel)
                </label>
                <textarea
                    id="exit_note"
                    v-model="form.note"
                    rows="3"
                    maxlength="2000"
                    placeholder="Précisions sur le contexte de la sortie."
                    class="w-full rounded-md border border-slate-200 px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100 focus:outline-none"
                />
                <InputError v-if="form.errors.note" :message="form.errors.note" />
            </div>

            <p
                class="rounded-lg border border-amber-200 bg-amber-50/60 px-3 py-2 text-xs leading-snug text-amber-800"
                role="note"
            >
                Si des contrats ou indisponibilités actifs débordent la date
                de sortie proposée, l'opération sera bloquée. Vous devrez
                d'abord raccourcir ou supprimer ces éléments.
            </p>
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
                variant="destructive"
                :loading="form.processing"
                :disabled="!canSubmit"
                @click="submit"
            >
                Confirmer le retrait
            </Button>
        </template>
    </Modal>
</template>
