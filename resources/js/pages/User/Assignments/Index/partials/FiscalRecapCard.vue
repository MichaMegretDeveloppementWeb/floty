<script setup lang="ts">
import Button from '@/Components/Ui/Button/Button.vue';
import { useFiscalYear } from '@/Composables/Shared/useFiscalYear';
import { formatEur } from '@/Utils/format/formatEur';

type FiscalPreview = App.Data.User.Fiscal.FiscalPreviewData;

defineProps<{
    selectedVehicleLabel: string | null;
    selectedCompanyLabel: string | null;
    selectedDates: string[];
    preview: FiscalPreview | null;
    previewLoading: boolean;
    submitting: boolean;
    canSubmit: boolean;
}>();

defineEmits<{
    submit: [];
}>();

const { daysInYear: daysInFiscalYear } = useFiscalYear();
</script>

<template>
    <aside class="flex flex-col gap-4">
        <section
            class="flex flex-col gap-3 rounded-xl border border-slate-200 bg-white p-5"
        >
            <p class="eyebrow">Récapitulatif</p>

            <dl class="flex flex-col gap-2 text-sm">
                <div class="flex justify-between gap-3">
                    <dt class="text-slate-500">Véhicule</dt>
                    <dd class="truncate text-right font-medium text-slate-900">
                        {{ selectedVehicleLabel ?? '—' }}
                    </dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-slate-500">Entreprise</dt>
                    <dd class="truncate text-right font-medium text-slate-900">
                        {{ selectedCompanyLabel ?? '—' }}
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
                    <span class="text-slate-600">Nouveaux jours pour ce couple</span>
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
                <div class="mt-1 flex justify-between border-t border-slate-100 pt-2">
                    <span class="text-slate-600">
                        Taxe CO₂ ({{ preview.after.co2Method }})
                    </span>
                    <span class="font-mono text-slate-900">
                        {{ formatEur(preview.after.co2Due, 2) }}
                    </span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-600">Taxe polluants</span>
                    <span class="font-mono text-slate-900">
                        {{ formatEur(preview.after.pollutantsDue, 2) }}
                    </span>
                </div>
                <div
                    class="mt-1 flex justify-between border-t border-slate-100 pt-2 text-base"
                >
                    <span class="font-medium text-slate-900">
                        Total annuel du couple
                    </span>
                    <span class="font-mono font-semibold text-slate-900">
                        {{ formatEur(preview.after.totalDue, 2) }}
                    </span>
                </div>
                <div
                    v-if="preview.incrementalDue > 0"
                    class="flex justify-between text-xs text-slate-500"
                >
                    <span>dont induit par cette attribution</span>
                    <span class="font-mono">
                        +{{ formatEur(preview.incrementalDue, 2) }}
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
                @click="$emit('submit')"
            >
                Créer {{ selectedDates.length }} attribution{{
                    selectedDates.length > 1 ? 's' : ''
                }}
            </Button>
        </section>
    </aside>
</template>
