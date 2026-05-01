<script setup lang="ts">
import Button from '@/Components/Ui/Button/Button.vue';
import Modal from '@/Components/Ui/Modal/Modal.vue';
import { useReactivateVehicleForm } from '@/Composables/Vehicle/Show/useReactivateVehicleForm';
import { formatDateFr } from '@/Utils/format/formatDateFr';
import { vehicleExitReasonLabel } from '@/Utils/labels/vehicleEnumLabels';

const props = defineProps<{
    vehicleId: number;
    licensePlate: string;
    exitDate: string;
    exitReason: App.Enums.Vehicle.VehicleExitReason;
}>();

const open = defineModel<boolean>('open', { required: true });

const { form, submit } = useReactivateVehicleForm(props, open);
</script>

<template>
    <Modal
        v-model:open="open"
        title="Réactiver ce véhicule"
        size="md"
    >
        <div class="flex flex-col gap-3 text-sm text-slate-700">
            <p>
                Vous êtes sur le point de réactiver le véhicule
                <span class="font-semibold text-slate-900">{{ props.licensePlate }}</span>.
            </p>
            <p>
                Sortie actuelle&nbsp;: motif
                <span class="font-medium">{{ vehicleExitReasonLabel[props.exitReason] }}</span>,
                effective le
                <span class="font-medium">{{ formatDateFr(props.exitDate) }}</span>.
            </p>
            <p class="rounded-lg border border-slate-200 bg-slate-50/60 px-3 py-2 text-xs leading-snug text-slate-600">
                Après réactivation, le véhicule redeviendra disponible pour
                de nouveaux contrats et indisponibilités. Son historique
                fiscal et ses contrats antérieurs sont conservés.
            </p>
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
                :loading="form.processing"
                @click="submit"
            >
                Confirmer la réactivation
            </Button>
        </template>
    </Modal>
</template>
