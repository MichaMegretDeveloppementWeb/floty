<script setup lang="ts">
import MultiDatePicker from '@/Components/Features/Planning/MultiDatePicker.vue';
import UserLayout from '@/Components/Layouts/UserLayout.vue';
import Button from '@/Components/Ui/Button/Button.vue';
import SelectInput from '@/Components/Ui/SelectInput/SelectInput.vue';
import { useFiscalYear } from '@/Composables/Shared/useFiscalYear';
import { getJson, postJson } from '@/lib/http';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

type VehicleOption = App.Data.User.Vehicle.VehicleOptionData;
type CompanyOption = App.Data.User.Company.CompanyOptionData;

const props = defineProps<{
    vehicles: VehicleOption[];
    companies: CompanyOption[];
}>();

const { currentYear: fiscalYear, daysInYear: daysInFiscalYear } =
    useFiscalYear();

const selectedVehicleId = ref<number | null>(null);
const selectedCompanyId = ref<number | null>(null);
const selectedDates = ref<string[]>([]);

// Pour griser les dates où le véhicule est déjà attribué (toutes entreprises).
const busyDatesForVehicle = ref<string[]>([]);
const pairDatesForCouple = ref<string[]>([]);

type FiscalPreview = App.Data.User.Fiscal.FiscalPreviewData;

const preview = ref<FiscalPreview | null>(null);
const previewLoading = ref(false);
const submitting = ref(false);

const vehicleOptions = computed(() =>
    props.vehicles.map((v) => ({
        value: String(v.id),
        label: v.label,
    })),
);

const companyOptions = computed(() =>
    props.companies.map((c) => ({
        value: String(c.id),
        label: `${c.shortCode} — ${c.legalName}`,
    })),
);

const vehicleIdString = computed({
    get: () =>
        selectedVehicleId.value !== null
            ? String(selectedVehicleId.value)
            : '',
    set: (v: string) => {
        selectedVehicleId.value = v === '' ? null : Number(v);
    },
});

const companyIdString = computed({
    get: () =>
        selectedCompanyId.value !== null
            ? String(selectedCompanyId.value)
            : '',
    set: (v: string) => {
        selectedCompanyId.value = v === '' ? null : Number(v);
    },
});

const selectedVehicleLabel = computed((): string | null => {
    if (selectedVehicleId.value === null) return null;
    return (
        props.vehicles.find((v) => v.id === selectedVehicleId.value)?.label ??
        null
    );
});

// Dès qu'on change de véhicule, récupérer ses dates déjà attribuées.
watch(selectedVehicleId, async (vehicleId) => {
    selectedDates.value = [];
    preview.value = null;
    busyDatesForVehicle.value = [];
    pairDatesForCouple.value = [];
    if (vehicleId === null) return;
    try {
        const data = await getJson<{
            vehicleBusyDates: string[];
            pairDates: Record<string, string[]>;
        }>('/app/assignments/vehicle-dates', {
            vehicleId,
            year: fiscalYear.value,
        });
        busyDatesForVehicle.value = data.vehicleBusyDates;
        if (selectedCompanyId.value !== null) {
            pairDatesForCouple.value =
                data.pairDates[String(selectedCompanyId.value)] ?? [];
        }
    } catch {
        /* silent */
    }
});

watch(selectedCompanyId, async () => {
    preview.value = null;
    if (selectedVehicleId.value === null) return;
    try {
        const data = await getJson<{
            vehicleBusyDates: string[];
            pairDates: Record<string, string[]>;
        }>('/app/assignments/vehicle-dates', {
            vehicleId: selectedVehicleId.value,
            year: fiscalYear.value,
        });
        pairDatesForCouple.value =
            selectedCompanyId.value !== null
                ? (data.pairDates[String(selectedCompanyId.value)] ?? [])
                : [];
    } catch {
        /* silent */
    }
});

// Calendrier : on grise les jours occupés SAUF ceux déjà attribués au couple
// courant (on les ré-affiche dans un état « existant » plutôt que désactivé).
const disabledDates = computed((): string[] => {
    const pairSet = new Set(pairDatesForCouple.value);
    return busyDatesForVehicle.value.filter((d) => !pairSet.has(d));
});

// Preview fiscal en temps réel.
let debounceHandle: number | null = null;
watch([selectedVehicleId, selectedCompanyId, selectedDates], () => {
    if (debounceHandle) window.clearTimeout(debounceHandle);
    debounceHandle = window.setTimeout(() => void fetchPreview(), 200);
});

async function fetchPreview(): Promise<void> {
    if (
        selectedVehicleId.value === null ||
        selectedCompanyId.value === null ||
        selectedDates.value.length === 0
    ) {
        preview.value = null;
        return;
    }
    previewLoading.value = true;
    try {
        preview.value = await postJson<FiscalPreview>(
            '/app/planning/preview-taxes',
            {
                vehicleId: selectedVehicleId.value,
                companyId: selectedCompanyId.value,
                dates: selectedDates.value,
            },
        );
    } catch {
        preview.value = null;
    } finally {
        previewLoading.value = false;
    }
}

async function submit(): Promise<void> {
    if (
        selectedVehicleId.value === null ||
        selectedCompanyId.value === null ||
        selectedDates.value.length === 0
    ) {
        return;
    }
    submitting.value = true;
    try {
        await postJson('/app/planning/assignments', {
            vehicleId: selectedVehicleId.value,
            companyId: selectedCompanyId.value,
            dates: selectedDates.value,
        });
        // On renvoie sur la vue d'ensemble pour visualiser la densité à jour.
        router.visit('/app/planning');
    } finally {
        submitting.value = false;
    }
}

const formatEur = (value: number): string =>
    new Intl.NumberFormat('fr-FR', {
        style: 'currency',
        currency: 'EUR',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })
        .format(value)
        .replace(/ | /g, ' ');

const canSubmit = computed(
    (): boolean =>
        selectedVehicleId.value !== null &&
        selectedCompanyId.value !== null &&
        selectedDates.value.length > 0 &&
        !submitting.value,
);
</script>

<template>
    <Head title="Attribution rapide" />

    <UserLayout>
        <div class="flex flex-col gap-6">
            <header>
                <p class="eyebrow mb-1">Planning</p>
                <h1
                    class="text-2xl font-semibold tracking-tight text-slate-900 md:text-3xl"
                >
                    Attribution rapide · {{ fiscalYear }}
                </h1>
                <p class="mt-1 text-base text-slate-600">
                    Un véhicule, une entreprise, un ou plusieurs jours
                    — tout en une passe. Pour une attribution contextuelle
                    à partir d'une semaine précise, utilisez plutôt la
                    vue d'ensemble et cliquez sur la cellule voulue.
                </p>
            </header>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1fr)_400px]">
                <!-- Colonne gauche : sélecteurs + calendrier -->
                <section
                    class="flex flex-col gap-4 rounded-xl border border-slate-200 bg-white p-5"
                >
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                        <SelectInput
                            v-model="vehicleIdString"
                            label="Véhicule"
                            placeholder="Choisir un véhicule…"
                            :options="vehicleOptions"
                        />
                        <SelectInput
                            v-model="companyIdString"
                            label="Entreprise utilisatrice"
                            placeholder="Choisir une entreprise…"
                            :options="companyOptions"
                        />
                    </div>

                    <div
                        v-if="selectedVehicleId === null"
                        class="rounded-lg bg-slate-50 p-6 text-center text-sm text-slate-500"
                    >
                        Sélectionnez un véhicule pour accéder au calendrier.
                    </div>
                    <MultiDatePicker
                        v-else
                        v-model:selected="selectedDates"
                        :year="fiscalYear"
                        :disabled-dates="disabledDates"
                        :current-pair-dates="pairDatesForCouple"
                    />
                </section>

                <!-- Colonne droite : preview fiscal + bouton -->
                <aside class="flex flex-col gap-4">
                    <section
                        class="flex flex-col gap-3 rounded-xl border border-slate-200 bg-white p-5"
                    >
                        <p class="eyebrow">Récapitulatif</p>

                        <dl class="flex flex-col gap-2 text-sm">
                            <div class="flex justify-between gap-3">
                                <dt class="text-slate-500">Véhicule</dt>
                                <dd
                                    class="truncate text-right font-medium text-slate-900"
                                >
                                    {{ selectedVehicleLabel ?? '—' }}
                                </dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-slate-500">Entreprise</dt>
                                <dd
                                    class="truncate text-right font-medium text-slate-900"
                                >
                                    {{
                                        selectedCompanyId !== null
                                            ? companies.find(
                                                  (c) =>
                                                      c.id ===
                                                      selectedCompanyId,
                                              )?.legalName
                                            : '—'
                                    }}
                                </dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-slate-500">Jours sélectionnés</dt>
                                <dd class="font-mono text-slate-900">
                                    {{ selectedDates.length }}
                                </dd>
                            </div>
                        </dl>

                        <div
                            v-if="preview"
                            class="mt-2 flex flex-col gap-1.5 border-t border-slate-100 pt-3 text-sm"
                        >
                            <p class="eyebrow mb-0 text-blue-700">
                                Taxes induites par cette attribution
                            </p>
                            <div class="flex justify-between">
                                <span class="text-slate-600">
                                    Nouveaux jours pour ce couple
                                </span>
                                <span class="font-mono text-slate-900">
                                    +{{ preview.newDaysCount }} j
                                </span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-600">Cumul futur</span>
                                <span class="font-mono text-slate-900">
                                    {{ preview.futureCumul }} j / {{ daysInFiscalYear }}
                                </span>
                            </div>
                            <div
                                v-if="preview.after.exemptionReasons.length > 0"
                                class="mt-1 flex flex-col gap-1 text-xs text-emerald-700"
                            >
                                <p
                                    v-for="(reason, i) in preview.after.exemptionReasons"
                                    :key="i"
                                    class="rounded-md bg-emerald-50 px-2 py-1"
                                >
                                    ✓ {{ reason }}
                                </p>
                            </div>
                            <div
                                class="mt-1 flex justify-between border-t border-slate-100 pt-2"
                            >
                                <span class="text-slate-600">
                                    Taxe CO₂ ({{ preview.after.co2Method }})
                                </span>
                                <span class="font-mono text-slate-900">
                                    {{ formatEur(preview.after.co2Due) }}
                                </span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-600">Taxe polluants</span>
                                <span class="font-mono text-slate-900">
                                    {{ formatEur(preview.after.pollutantsDue) }}
                                </span>
                            </div>
                            <div
                                class="mt-1 flex justify-between border-t border-slate-100 pt-2 text-base"
                            >
                                <span class="font-medium text-slate-900">
                                    Total annuel du couple
                                </span>
                                <span
                                    class="font-mono font-semibold text-slate-900"
                                >
                                    {{ formatEur(preview.after.totalDue) }}
                                </span>
                            </div>
                            <div
                                v-if="preview.incrementalDue > 0"
                                class="flex justify-between text-xs text-slate-500"
                            >
                                <span>dont induit par cette attribution</span>
                                <span class="font-mono">
                                    +{{ formatEur(preview.incrementalDue) }}
                                </span>
                            </div>
                        </div>
                        <div
                            v-else-if="previewLoading"
                            class="mt-2 text-xs text-slate-500"
                        >
                            Calcul en cours…
                        </div>

                        <Button
                            type="button"
                            block
                            :loading="submitting"
                            :disabled="!canSubmit"
                            @click="submit"
                        >
                            Créer {{ selectedDates.length }} attribution{{
                                selectedDates.length > 1 ? 's' : ''
                            }}
                        </Button>
                    </section>
                </aside>
            </div>
        </div>
    </UserLayout>
</template>
