<script setup lang="ts">
/**
 * Modal de sortie d'un driver d'une entreprise (workflow Q6).
 *
 * Workflow (chantier #3 multi-conducteurs) :
 * 1. L'utilisateur saisit la date de sortie.
 * 2. Le composable charge automatiquement les contrats à venir (start_date
 *    > leftAt) du driver dans cette company. Chaque ligne expose :
 *    - `currentDrivers` : conducteurs actuellement attachés au contrat
 *      (le sortant compris) · pour donner le contexte.
 *    - `candidates` : drivers actifs sur la période, hors tous les
 *      drivers déjà attachés.
 * 3a. Si 0 contrats → sortie directe (mode 'none').
 * 3b. Si ≥1 contrats → l'utilisateur choisit :
 *     - Mode 'detach'  : retire le sortant de tous les contrats à venir
 *       (les autres conducteurs présents sur ces contrats restent).
 *     - Mode 'replace' : pour chaque contrat, soit on retire juste le
 *       sortant (= `null`), soit on retire le sortant ET on attache un
 *       remplaçant en plus.
 * 4. Submit : construit `replacement_map = {contractId: driverId|null}`
 *    en mode replace ; sinon omet (Spatie Data refuse `[]` explicite).
 *
 * Symétrique : utilisé depuis la fiche Driver Show ET la fiche Company
 * Show · props neutres (driverId, companyId, fullName, companyName).
 */
import { useForm } from '@inertiajs/vue3';
import { Loader2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import Button from '@/Components/Ui/Button/Button.vue';
import DateInput from '@/Components/Ui/DateInput/DateInput.vue';
import FieldLabel from '@/Components/Ui/FieldLabel/FieldLabel.vue';
import InputError from '@/Components/Ui/InputError/InputError.vue';
import Modal from '@/Components/Ui/Modal/Modal.vue';
import Plate from '@/Components/Ui/Plate/Plate.vue';
import SelectInput from '@/Components/Ui/SelectInput/SelectInput.vue';
import { useApi } from '@/Composables/Shared/useApi';
import { futureContracts as futureContractsRoute } from '@/routes/user/drivers/memberships';
import { leave as leaveRoute } from '@/routes/user/drivers/memberships';
import { formatDateFr } from '@/Utils/format/formatDateFr';
import { contractTypeShortLabel } from '@/Utils/labels/contractEnumLabels';

type FutureContract = App.Data.User.Driver.FutureContractRowData;
type ResolutionMode = 'replace' | 'detach' | 'none';

const props = defineProps<{
    driverId: number;
    companyId: number;
    driverFullName: string;
    companyName: string;
}>();

const emit = defineEmits<{ close: [] }>();

const open = ref(true);
const api = useApi();

type FormShape = {
    left_at: string;
    future_contracts_resolution: ResolutionMode;
    replacement_map: Record<number, number | null>;
};

const form = useForm<FormShape>({
    left_at: new Date().toISOString().slice(0, 10),
    future_contracts_resolution: 'none',
    replacement_map: {},
});

// Liste auto-chargée des contrats à venir + replacement choisi par contrat.
// `loadingContracts` part à `true` : le watch debounce le 1er fetch de
// 250ms après l'ouverture, et on ne veut pas montrer un message
// transitoire « Aucune location à venir » avant que la requête réponde
// (sursaut UX visible côté utilisateur).
const futureContracts = ref<FutureContract[]>([]);
const loadingContracts = ref<boolean>(true);
const fetchError = ref<string | null>(null);
// Map locale contractId -> driverId | null. `null` = détacher ce contrat.
// `undefined` = pas encore choisi (= disabled submit en mode 'replace').
const replacementMap = ref<Record<number, number | null | undefined>>({});

let fetchTimer: ReturnType<typeof setTimeout> | null = null;

async function fetchFutureContracts(): Promise<void> {
    fetchError.value = null;

    if (!/^\d{4}-\d{2}-\d{2}$/.test(form.left_at)) {
        return;
    }

    loadingContracts.value = true;

    try {
        const response = await api.get<{ contracts: FutureContract[] }>(
            futureContractsRoute([props.driverId, props.companyId]).url,
            { leftAt: form.left_at },
        );
        futureContracts.value = response.contracts;
        // Reset des sélections · l'utilisateur doit re-choisir si la
        // date change (les candidats peuvent varier selon la période).
        replacementMap.value = {};

        // Si plus aucun contrat à résoudre, force le mode none.
        if (response.contracts.length === 0) {
            form.future_contracts_resolution = 'none';
        } else if (form.future_contracts_resolution === 'none') {
            form.future_contracts_resolution = 'detach';
        }
    } catch {
        fetchError.value
            = 'Impossible de charger les locations à venir. Veuillez réessayer.';
    } finally {
        loadingContracts.value = false;
    }
}

// Debounce 250ms sur changement de date · évite un fetch par caractère
watch(
    () => form.left_at,
    () => {
        if (fetchTimer !== null) {
            clearTimeout(fetchTimer);
        }

        fetchTimer = setTimeout(fetchFutureContracts, 250);
    },
    { immediate: true },
);

const hasFutureContracts = computed<boolean>(
    () => futureContracts.value.length > 0,
);

const replaceModeIncomplete = computed<boolean>(() => {
    if (form.future_contracts_resolution !== 'replace') {
        return false;
    }

    return futureContracts.value.some(
        (c) => replacementMap.value[c.contractId] === undefined,
    );
});

const submitDisabled = computed<boolean>(
    () => loadingContracts.value || replaceModeIncomplete.value,
);

const resolutionOptions = computed<
    Array<{ value: ResolutionMode; label: string }>
>(() => {
    const count = futureContracts.value.length;

    return [
        {
            value: 'detach',
            label: `Retirer le conducteur des ${count} location${count > 1 ? 's' : ''} à venir`,
        },
        {
            value: 'replace',
            label: 'Au cas par cas : retirer + ajouter un remplaçant si besoin',
        },
    ];
});

function candidateOptionsFor(contract: FutureContract): Array<{
    value: number | string;
    label: string;
}> {
    const options: Array<{ value: number | string; label: string }>
        = contract.candidates.map((c) => ({ value: c.id, label: c.fullName }));
    options.unshift({ value: '__detach__', label: '· Aucun remplaçant (juste retirer) ·' });

    return options;
}

function selectCandidate(
    contractId: number,
    rawValue: number | string | null,
): void {
    if (rawValue === '__detach__' || rawValue === null) {
        replacementMap.value[contractId] = null;

        return;
    }

    if (typeof rawValue === 'number') {
        replacementMap.value[contractId] = rawValue;
    }
}

function close(): void {
    open.value = false;
    emit('close');
}

function submit(): void {
    form.transform((data) => {
        const base = {
            left_at: data.left_at,
            future_contracts_resolution: data.future_contracts_resolution,
        };

        if (data.future_contracts_resolution !== 'replace') {
            // Spatie Data + Laravel rejettent `replacement_map: {}` explicite
            // en JSON (validation.required) · on omet la clé sauf en mode
            // 'replace' où elle porte le mapping.
            return base;
        }

        // Convertit la map locale (avec undefined possible) en plain object
        // {contractId: driverId|null} attendu par le DTO.
        const map: Record<number, number | null> = {};

        for (const [contractId, driverId] of Object.entries(
            replacementMap.value,
        )) {
            map[Number(contractId)] = driverId === undefined ? null : driverId;
        }

        return { ...base, replacement_map: map };
    }).patch(leaveRoute([props.driverId, props.companyId]).url, {
        preserveScroll: true,
        onSuccess: () => close(),
    });
}
</script>

<template>
    <Modal
        v-model:open="open"
        title="Sortir le conducteur de l'entreprise"
        size="lg"
        @close="emit('close')"
    >
        <p class="text-sm text-slate-700">
            Sortir <strong>{{ driverFullName }}</strong> de
            <strong>{{ companyName }}</strong
            >.
        </p>
        <p class="mt-2 text-xs text-slate-500">
            Cette action pose une date de sortie sur le rattachement.
            L'historique des locations passées est conservé.
        </p>

        <form class="mt-6 flex flex-col gap-4" @submit.prevent="submit">
            <div>
                <FieldLabel for="leave-left-at">Date de sortie</FieldLabel>
                <DateInput id="leave-left-at" v-model="form.left_at" />
                <InputError :message="form.errors.left_at" />
            </div>

            <!-- Loader pendant le fetch des contrats à venir -->
            <div
                v-if="loadingContracts"
                class="flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-500"
            >
                <Loader2
                    :size="14"
                    :stroke-width="2"
                    class="animate-spin"
                />
                Chargement des locations à venir…
            </div>

            <p
                v-else-if="fetchError !== null"
                class="text-xs text-rose-600"
            >
                {{ fetchError }}
            </p>

            <!-- Aucun contrat à venir -->
            <div
                v-else-if="!hasFutureContracts"
                class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-800"
            >
                Aucune location à venir après cette date · sortie directe sans
                résolution nécessaire.
            </div>

            <!-- ≥1 contrat à résoudre : sélecteur mode + table -->
            <template v-else>
                <div>
                    <FieldLabel for="leave-resolution">
                        Résolution des
                        {{ futureContracts.length }} location{{
                            futureContracts.length > 1 ? 's' : ''
                        }}
                        à venir
                    </FieldLabel>
                    <SelectInput
                        id="leave-resolution"
                        v-model="form.future_contracts_resolution"
                        :options="resolutionOptions"
                    />
                    <InputError
                        :message="form.errors.future_contracts_resolution"
                    />
                </div>

                <!-- Mode replace : table de contrats avec sélecteur par ligne -->
                <div
                    v-if="form.future_contracts_resolution === 'replace'"
                    class="flex flex-col gap-2 rounded-lg border border-slate-200 bg-white p-3"
                >
                    <p class="text-xs font-medium text-slate-700">
                        Pour chaque location : retirer {{ driverFullName }} et,
                        si besoin, ajouter un remplaçant
                    </p>
                    <div
                        v-for="contract in futureContracts"
                        :key="contract.contractId"
                        class="flex flex-col gap-2 rounded-md border border-slate-100 bg-slate-50 p-2 sm:flex-row sm:items-center"
                    >
                        <div class="flex flex-1 flex-col gap-0.5">
                            <div class="flex items-center gap-2">
                                <Plate :value="contract.vehicleLicensePlate" />
                                <span
                                    class="rounded bg-slate-200 px-1.5 py-0.5 text-[10px] font-semibold text-slate-700 uppercase"
                                >
                                    {{ contractTypeShortLabel[contract.contractType] }}
                                </span>
                            </div>
                            <p class="text-xs text-slate-500">
                                {{ formatDateFr(contract.startDate) }}
                                <span class="mx-1 text-slate-300">→</span>
                                {{ formatDateFr(contract.endDate) }}
                                <span class="mx-1 text-slate-300">·</span>
                                {{ contract.durationDays }} j
                            </p>
                            <p
                                v-if="contract.currentDrivers.length > 1"
                                class="text-[11px] text-slate-500"
                            >
                                Conducteurs actuels :
                                <span
                                    v-for="(d, i) in contract.currentDrivers"
                                    :key="d.id"
                                    :class="d.id === driverId ? 'font-medium text-rose-700 line-through' : ''"
                                >{{ d.fullName }}<span v-if="i < contract.currentDrivers.length - 1">, </span></span>
                            </p>
                        </div>
                        <div class="w-full sm:w-72">
                            <SelectInput
                                :model-value="
                                    replacementMap[contract.contractId] === null
                                        ? '__detach__'
                                        : (replacementMap[contract.contractId] ?? null)
                                "
                                :options="candidateOptionsFor(contract)"
                                placeholder="Choisir un remplaçant"
                                nullable
                                @update:model-value="
                                    (v) => selectCandidate(contract.contractId, v)
                                "
                            />
                            <p
                                v-if="contract.candidates.length === 0"
                                class="mt-1 text-[10px] text-amber-700"
                            >
                                Aucun remplaçant disponible · choisissez « Aucun remplaçant ».
                            </p>
                        </div>
                    </div>
                    <p
                        v-if="replaceModeIncomplete"
                        class="mt-1 text-xs text-amber-700"
                    >
                        Faites un choix pour chaque location avant de confirmer.
                    </p>
                </div>
            </template>

            <div class="flex justify-end gap-2">
                <Button variant="ghost" type="button" @click="close"
                    >Annuler</Button
                >
                <Button
                    type="submit"
                    variant="destructive"
                    :loading="form.processing"
                    :disabled="submitDisabled"
                >
                    Confirmer la sortie
                </Button>
            </div>
        </form>
    </Modal>
</template>
