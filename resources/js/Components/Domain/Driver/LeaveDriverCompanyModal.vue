<script setup lang="ts">
/**
 * Modal for removing a driver from a company.
 * Captures the leaving date, auto-fetches upcoming contracts, and lets the
 * user choose detach-all, per-contract replacement, or direct exit when none.
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

// Starts true so the empty-state message does not flash before the first fetch.
const futureContracts = ref<FutureContract[]>([]);
const loadingContracts = ref<boolean>(true);
const fetchError = ref<string | null>(null);
// contractId -> driverId | null (detach) | undefined (not chosen yet, disables submit).
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
            futureContractsRoute.url([props.driverId, props.companyId]),
            { leftAt: form.left_at },
        );
        futureContracts.value = response.contracts;
        // Reset selections; candidates may change with the new date.
        replacementMap.value = {};

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

// Debounce date changes by 250 ms to avoid one fetch per keystroke.
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
            label: `Détacher de l'entreprise et des ${count} location${count > 1 ? 's' : ''} à venir`,
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
            // Omit replacement_map entirely; Spatie Data rejects {} as required.
            return base;
        }

        // Build a plain {contractId: driverId|null} map for the DTO.
        const map: Record<number, number | null> = {};

        for (const [contractId, driverId] of Object.entries(
            replacementMap.value,
        )) {
            map[Number(contractId)] = driverId === undefined ? null : driverId;
        }

        return { ...base, replacement_map: map };
    }).patch(leaveRoute.url([props.driverId, props.companyId]), {
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

            <div
                v-else-if="!hasFutureContracts"
                class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-800"
            >
                Aucune location à venir après cette date · sortie directe sans
                résolution nécessaire.
            </div>

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
