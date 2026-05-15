<script setup lang="ts">
import { Pencil, Plus, Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';
import Badge from '@/Components/Ui/Badge/Badge.vue';
import Button from '@/Components/Ui/Button/Button.vue';
import Card from '@/Components/Ui/Card/Card.vue';
import ConfirmModal from '@/Components/Ui/ConfirmModal/ConfirmModal.vue';
import {
    useVehiclePricingDeleteState,
    useVehiclePricingFormModalState,
} from '@/Composables/Vehicle/Show/useVehicleYearlyPricingForm';
import { formatEur } from '@/Utils/format/formatEur';
import VehiclePricingFormModal from './VehiclePricingFormModal.vue';

type Pricing = App.Data.User.Vehicle.VehicleYearlyPricingData;

const props = withDefaults(
    defineProps<{
        vehicleId: number;
        pricings: ReadonlyArray<Pricing>;
        /**
         * Refonte D5.10.W · mode flush sans Card wrapping ni header
         * interne (titre + description). Le bouton « Ajouter un tarif »
         * est conservé en haut à droite de la zone, aligné avec
         * l'eyebrow du tab parent.
         */
        unwrapped?: boolean;
    }>(),
    { unwrapped: false },
);

/**
 * Plage d'années proposée à la création : `[currentYear-3, currentYear+2]`.
 * On exclut les années déjà couvertes (la modale est purement créative ;
 * un pricing existant se modifie par le bouton crayon).
 */
const availableYears = computed<number[]>(() => {
    const currentYear = new Date().getFullYear();
    const candidates: number[] = [];

    for (let y = currentYear - 3; y <= currentYear + 2; y++) {
        candidates.push(y);
    }

    const covered = new Set(props.pricings.map((p) => p.year));

    return candidates.filter((y) => !covered.has(y));
});

const formState = useVehiclePricingFormModalState();
const deleteState = useVehiclePricingDeleteState(props.vehicleId);

const sortedPricings = computed<Pricing[]>(() =>
    [...props.pricings].sort((a, b) => a.year - b.year),
);
</script>

<template>
    <component :is="unwrapped ? 'div' : Card">
        <template v-if="!unwrapped" #header>
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-base font-semibold text-slate-900">
                        Tarifs jour / semaine / mois
                    </h2>
                    <p class="mt-0.5 text-xs text-slate-500">
                        Une ligne par année. Servent au calcul de la
                        facturation mensuelle (panachage automatique).
                    </p>
                </div>
                <Button
                    size="sm"
                    :disabled="availableYears.length === 0"
                    @click="formState.requestCreate()"
                >
                    <template #icon-left>
                        <Plus :size="14" :stroke-width="1.75" />
                    </template>
                    Ajouter un tarif
                </Button>
            </div>
        </template>

        <!--
            Action « Ajouter un tarif » en mode unwrapped · le tab parent
            fournit l'eyebrow `TARIFS ANNUELS` au-dessus, on positionne
            le bouton à droite, en-dessous, juste avant la table.
        -->
        <div v-if="unwrapped" class="mb-3 flex justify-end">
            <Button
                size="sm"
                :disabled="availableYears.length === 0"
                @click="formState.requestCreate()"
            >
                <template #icon-left>
                    <Plus :size="14" :stroke-width="1.75" />
                </template>
                Ajouter un tarif
            </Button>
        </div>

        <p
            v-if="sortedPricings.length === 0"
            class="text-sm text-slate-500 italic"
        >
            Aucun tarif renseigné pour ce véhicule.
        </p>

        <div v-else class="overflow-x-auto">
            <table class="w-full text-sm">
                <caption class="sr-only">
                    Tarifs annuels du véhicule
                </caption>
                <thead>
                    <tr class="text-left text-xs font-medium tracking-wider uppercase text-slate-500">
                        <th scope="col" class="py-2 pr-3 font-medium">Année</th>
                        <th scope="col" class="py-2 px-3 font-medium">Jour</th>
                        <th scope="col" class="py-2 px-3 font-medium">Semaine</th>
                        <th scope="col" class="py-2 px-3 font-medium">Mois</th>
                        <th scope="col" class="py-2 pl-3 text-right font-medium">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr
                        v-for="pricing in sortedPricings"
                        :key="pricing.year"
                        class="text-slate-800"
                    >
                        <td class="py-2.5 pr-3">
                            <Badge tone="slate">
                                {{ pricing.year }}
                            </Badge>
                        </td>
                        <td class="py-2.5 px-3 font-mono tabular-nums">
                            {{ formatEur(pricing.dailyRateCents / 100, 2) }}
                        </td>
                        <td class="py-2.5 px-3 font-mono tabular-nums">
                            {{ formatEur(pricing.weeklyRateCents / 100, 2) }}
                        </td>
                        <td class="py-2.5 px-3 font-mono tabular-nums">
                            {{ formatEur(pricing.monthlyRateCents / 100, 2) }}
                        </td>
                        <td class="py-2.5 pl-3">
                            <div class="flex items-center justify-end gap-1">
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    aria-label="Modifier"
                                    @click="formState.requestEdit(pricing)"
                                >
                                    <template #icon-left>
                                        <Pencil :size="14" :stroke-width="1.75" />
                                    </template>
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    aria-label="Supprimer"
                                    @click="deleteState.requestDelete(pricing)"
                                >
                                    <template #icon-left>
                                        <Trash2 :size="14" :stroke-width="1.75" />
                                    </template>
                                </Button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <VehiclePricingFormModal
            v-model:open="formState.open.value"
            :vehicle-id="props.vehicleId"
            :available-years="availableYears"
            :editing="formState.editing.value"
        />

        <ConfirmModal
            v-model:open="deleteState.open.value"
            :title="`Supprimer le tarif ${deleteState.deleting.value?.year ?? ''} ?`"
            message="Cette action est définitive. Vous pourrez recréer un tarif pour cette année plus tard si nécessaire."
            confirm-label="Supprimer"
            tone="danger"
            :loading="deleteState.processing.value"
            @confirm="deleteState.confirmDelete()"
        />
    </component>
</template>
