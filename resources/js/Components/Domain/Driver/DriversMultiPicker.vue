<script setup lang="ts">
/**
 * Sélecteur multi-conducteurs pour le formulaire Contract (chantier #3
 * multi-conducteurs). Une ligne `<DriverSelector>` par driver sélectionné
 * + un bouton « Ajouter un conducteur » qui pousse une nouvelle ligne.
 *
 * Filtrage : chaque ligne exclut les drivers déjà choisis dans les
 * autres lignes (pas de duplicate). Émet `update:modelValue` avec un
 * `number[]` (l'ordre est l'ordre d'ajout).
 *
 * Disabled tant que `companyId + startDate + endDate` ne sont pas tous
 * renseignés (relayé à `<DriverSelector>`).
 */
import { computed, ref, watch } from 'vue';
import DriverSelector from './DriverSelector.vue';

const props = defineProps<{
    modelValue: number[];
    companyId: number | null;
    startDate: string | null;
    endDate: string | null;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: number[]];
}>();

// État local : liste de slots (chaque slot = un id sélectionné, ou null
// si la ligne vient d'être ajoutée et n'a pas encore de choix).
const slots = ref<(number | null)[]>(props.modelValue.length > 0 ? [...props.modelValue] : []);

// Synchro descendante : si le parent met à jour `modelValue` (ex. reset
// après submit), on aligne nos slots. On ignore l'effet de bord qui
// boucle vers le parent : on compare avant d'écraser.
watch(
    () => props.modelValue,
    (next) => {
        const current = slots.value.filter((v): v is number => v !== null);
        const same = next.length === current.length && next.every((v, i) => v === current[i]);

        if (!same) {
            slots.value = [...next];
        }
    },
);

// Émission vers le parent : on filtre les nulls (lignes en cours d'édition).
function emitChange(): void {
    const ids = slots.value.filter((v): v is number => v !== null);
    emit('update:modelValue', ids);
}

function selectorModel(index: number): { value: number | null; update: (v: number | null) => void } {
    return {
        value: slots.value[index] ?? null,
        update(v) {
            slots.value[index] = v;
            emitChange();
        },
    };
}

function addSlot(): void {
    slots.value.push(null);
}

function removeSlot(index: number): void {
    slots.value.splice(index, 1);
    emitChange();
}

// Pour chaque ligne, exclure les ids déjà choisis sur les **autres**
// lignes - on ne veut pas qu'un même conducteur apparaisse 2× sur le
// même contrat.
function excludedIdsForRow(index: number): number[] {
    return slots.value
        .map((v, i) => (i !== index && v !== null ? v : null))
        .filter((v): v is number => v !== null);
}

const canAddMore = computed<boolean>(() => {
    // Pas de garde-fou strict : on autorise jusqu'à ce que toutes les
    // lignes existantes aient une valeur (sinon on créerait des lignes
    // vides en série). L'utilisateur peut ajouter autant qu'il veut tant
    // que la dernière est remplie.
    if (slots.value.length === 0) {
        return true;
    }

    return slots.value.every((v) => v !== null);
});
</script>

<template>
    <div class="flex flex-col gap-2">
        <div
            v-for="(_, index) in slots"
            :key="index"
            class="flex items-start gap-2"
        >
            <div class="flex-1">
                <DriverSelector
                    :model-value="selectorModel(index).value"
                    :company-id="companyId"
                    :start-date="startDate"
                    :end-date="endDate"
                    :excluded-ids="excludedIdsForRow(index)"
                    @update:model-value="selectorModel(index).update"
                />
            </div>
            <button
                type="button"
                class="flex h-9 items-center justify-center rounded-md border border-slate-200 px-2 text-xs font-medium text-slate-600 hover:border-rose-300 hover:bg-rose-50 hover:text-rose-700"
                aria-label="Retirer ce conducteur"
                @click="removeSlot(index)"
            >
                Retirer
            </button>
        </div>

        <div>
            <button
                type="button"
                :disabled="!canAddMore"
                class="inline-flex items-center gap-1 rounded-md border border-dashed border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:border-indigo-300 hover:bg-indigo-50 hover:text-indigo-700 disabled:cursor-not-allowed disabled:opacity-50"
                @click="addSlot"
            >
                + Ajouter un conducteur
            </button>
            <p
                v-if="slots.length === 0"
                class="mt-1 text-xs text-slate-500"
            >
                Aucun conducteur désigné. Cliquez pour en ajouter un (ou
                plusieurs) à cette location.
            </p>
        </div>
    </div>
</template>
