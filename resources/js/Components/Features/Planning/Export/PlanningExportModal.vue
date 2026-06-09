<script setup lang="ts">
/**
 * Planning export modal · pick the vehicles (checklist + select-all) and
 * the mode (full weekly data vs vehicle sheet), then stream the PDF back.
 *
 * The vehicle list is the page's currently FILTERED list, so the export
 * only ever offers what is visible. Amounts are recomputed server-side ·
 * this modal sends ids + year + mode + scope only.
 */
import { computed, ref, watch } from 'vue';
import Button from '@/Components/Ui/Button/Button.vue';
import CheckboxInput from '@/Components/Ui/CheckboxInput/CheckboxInput.vue';
import Modal from '@/Components/Ui/Modal/Modal.vue';
import { useFileDownload } from '@/Composables/Shared/useFileDownload';
import { exportMethod as planningExportRoute } from '@/routes/user/planning';

type ExportableVehicle = {
    id: number;
    licensePlate: string;
    brand: string;
    model: string;
};

type ExportMode = App.Enums.Planning.PlanningExportMode;

const props = defineProps<{
    vehicles: readonly ExportableVehicle[];
    year: number;
    companyId?: number | null;
}>();

const open = defineModel<boolean>('open', { required: true });

const { downloadViaPost } = useFileDownload();

const selectedIds = ref<number[]>([]);
const mode = ref<ExportMode>('complete');
const isExporting = ref<boolean>(false);

// (Re)initialise the selection to all visible vehicles each time the modal opens.
watch(open, (isOpen) => {
    if (isOpen) {
        selectedIds.value = props.vehicles.map((vehicle) => vehicle.id);
        mode.value = 'complete';
        isExporting.value = false;
    }
});

const allSelected = computed<boolean>({
    get: () =>
        props.vehicles.length > 0 &&
        selectedIds.value.length === props.vehicles.length,
    set: (checked) => {
        selectedIds.value = checked
            ? props.vehicles.map((vehicle) => vehicle.id)
            : [];
    },
});

const selectedCount = computed<number>(() => selectedIds.value.length);

function isSelected(id: number): boolean {
    return selectedIds.value.includes(id);
}

function setSelected(id: number, checked: boolean): void {
    if (checked) {
        if (!selectedIds.value.includes(id)) {
            selectedIds.value = [...selectedIds.value, id];
        }

        return;
    }

    selectedIds.value = selectedIds.value.filter((current) => current !== id);
}

async function submit(): Promise<void> {
    if (selectedCount.value === 0 || isExporting.value) {
        return;
    }

    isExporting.value = true;

    try {
        await downloadViaPost(
            planningExportRoute.url(),
            {
                vehicle_ids: selectedIds.value,
                year: props.year,
                mode: mode.value,
                company_id: props.companyId ?? null,
            },
            `floty-planning-${props.year}.pdf`,
        );

        open.value = false;
    } catch {
        // Toast already raised by useFileDownload · keep the modal open.
    } finally {
        isExporting.value = false;
    }
}
</script>

<template>
    <Modal
        v-model:open="open"
        title="Exporter le planning"
        description="Choisissez les véhicules et le type de données à exporter en PDF."
        size="lg"
    >
        <div class="flex flex-col gap-5">
            <fieldset class="flex flex-col gap-2">
                <legend class="mb-1 text-sm font-medium text-slate-900">
                    Type d'export
                </legend>
                <label
                    class="flex cursor-pointer items-start gap-2.5 rounded-lg border border-slate-200 p-3 transition-colors hover:bg-slate-50"
                >
                    <input
                        v-model="mode"
                        type="radio"
                        value="complete"
                        class="mt-0.5 size-4 cursor-pointer"
                    />
                    <span class="flex flex-col gap-0.5">
                        <span class="text-base leading-tight text-slate-900"
                            >Données complètes</span
                        >
                        <span class="text-xs text-slate-500"
                            >Ligne véhicule + répartition hebdomadaire
                            d'utilisation (jours par semaine).</span
                        >
                    </span>
                </label>
                <label
                    class="flex cursor-pointer items-start gap-2.5 rounded-lg border border-slate-200 p-3 transition-colors hover:bg-slate-50"
                >
                    <input
                        v-model="mode"
                        type="radio"
                        value="vehicle"
                        class="mt-0.5 size-4 cursor-pointer"
                    />
                    <span class="flex flex-col gap-0.5">
                        <span class="text-base leading-tight text-slate-900"
                            >Données véhicule</span
                        >
                        <span class="text-xs text-slate-500"
                            >Fiche par véhicule : caractéristiques fiscales
                            principales et montants.</span
                        >
                    </span>
                </label>
            </fieldset>

            <div class="flex flex-col gap-2">
                <div
                    class="flex items-center justify-between border-b border-slate-200 pb-2"
                >
                    <CheckboxInput
                        v-model="allSelected"
                        label="Tout sélectionner"
                        :disabled="vehicles.length === 0"
                    />
                    <span class="text-xs text-slate-500 tabular-nums">
                        {{ selectedCount }} /
                        {{ vehicles.length }} sélectionné{{
                            selectedCount > 1 ? 's' : ''
                        }}
                    </span>
                </div>

                <div
                    v-if="vehicles.length > 0"
                    class="flex max-h-72 flex-col gap-2 overflow-y-auto pr-1"
                >
                    <CheckboxInput
                        v-for="vehicle in vehicles"
                        :key="vehicle.id"
                        :model-value="isSelected(vehicle.id)"
                        :label="`${vehicle.licensePlate} · ${vehicle.brand} ${vehicle.model}`"
                        @update:model-value="
                            (checked) => setSelected(vehicle.id, checked)
                        "
                    />
                </div>
                <p v-else class="py-4 text-sm text-slate-500">
                    Aucun véhicule à exporter dans la liste actuelle.
                </p>
            </div>
        </div>

        <template #footer>
            <Button variant="ghost" @click="open = false">Annuler</Button>
            <Button
                variant="primary"
                :loading="isExporting"
                :disabled="selectedCount === 0"
                @click="submit"
            >
                Exporter le PDF
            </Button>
        </template>
    </Modal>
</template>
