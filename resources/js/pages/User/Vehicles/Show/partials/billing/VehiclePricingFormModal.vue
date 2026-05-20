<script setup lang="ts">
import { computed } from 'vue';
import Button from '@/Components/Ui/Button/Button.vue';
import Modal from '@/Components/Ui/Modal/Modal.vue';
import NumberInput from '@/Components/Ui/NumberInput/NumberInput.vue';
import SelectInput from '@/Components/Ui/SelectInput/SelectInput.vue';
import { useVehicleYearlyPricingForm } from '@/Composables/Vehicle/Show/useVehicleYearlyPricingForm';

type Pricing = App.Data.User.Vehicle.VehicleYearlyPricingData;

const props = defineProps<{
    vehicleId: number;
    availableYears: ReadonlyArray<number>;
    editing: Pricing | null;
}>();

const open = defineModel<boolean>('open', { required: true });

const {
    form,
    isEditing,
    yearOptions,
    canSubmit,
    submit,
} = useVehicleYearlyPricingForm(props, open);

// Spatie Data emits errors keyed by the snake_case payload (see
// `transformPricingFormToPayload`), not the camelCase form keys.
type RateErrors = {
    year?: string;
    daily_rate_cents?: string;
    weekly_rate_cents?: string;
    monthly_rate_cents?: string;
};

const errors = computed<RateErrors>(() => form.errors as RateErrors);

const title = computed<string>(() =>
    isEditing.value && props.editing
        ? `Modifier le tarif ${props.editing.year}`
        : 'Ajouter un tarif annuel',
);
</script>

<template>
    <Modal
        v-model:open="open"
        :title="title"
        description="Tarifs hors taxes facturables au client. Le calcul mensuel optimisera automatiquement le panachage jour / semaine / mois en fonction des jours d'attribution réels."
        size="sm"
    >
        <form class="flex flex-col gap-4" @submit.prevent="submit">
            <SelectInput
                v-model="form.year"
                label="Année"
                :options="[...yearOptions]"
                :error="errors.year"
                :disabled="isEditing"
                required
            />

            <NumberInput
                v-model="form.dailyRateEuros"
                label="Tarif journalier"
                placeholder="0,00"
                :error="errors.daily_rate_cents"
                :min="0"
                :step="0.01"
                required
            >
                <template #unit>€</template>
            </NumberInput>

            <NumberInput
                v-model="form.weeklyRateEuros"
                label="Tarif hebdomadaire"
                placeholder="0,00"
                :error="errors.weekly_rate_cents"
                :min="0"
                :step="0.01"
                required
            >
                <template #unit>€</template>
            </NumberInput>

            <NumberInput
                v-model="form.monthlyRateEuros"
                label="Tarif mensuel"
                placeholder="0,00"
                hint="Saisir 0 pour les véhicules en usage gratuit (courtoisie interne)."
                :error="errors.monthly_rate_cents"
                :min="0"
                :step="0.01"
                required
            >
                <template #unit>€</template>
            </NumberInput>
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
