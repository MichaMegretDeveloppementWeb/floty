<script setup lang="ts">
/**
 * Encart « Caractéristiques fiscales appliquées au calcul » de l'onglet
 * Fiscalité : matérialise la (les) VFC effective(s) sur l'année
 * calculée — un bloc par période effective (un seul en mono-VFC, N en
 * multi-VFC).
 *
 * Complémentaire de
 * {@link FullYearTaxBreakdownPanel.vue} qui détaille les tarifs et dûs
 * par segment ; ici on se concentre sur les **caractéristiques** de la
 * VFC (énergie, norme Euro, catégorie polluants, méthode CO₂, valeur
 * CO₂).
 */
import { Info } from 'lucide-vue-next';
import Card from '@/Components/Ui/Card/Card.vue';
import {
    energySourceLabel,
    euroStandardLabel,
    homologationMethodLabel,
    pollutantCategoryLabel,
} from '@/Utils/labels/vehicleEnumLabels';

type Segment = App.Data.User.Vehicle.VehicleFullYearTaxSegmentData;
type Vfc = App.Data.User.Vehicle.VehicleFiscalCharacteristicsData;

defineProps<{
    segments: Segment[];
}>();

function co2Display(vfc: Vfc): string {
    const wltp = vfc.co2Wltp;
    const nedc = vfc.co2Nedc;
    const hp = vfc.taxableHorsepower;

    if (wltp !== null) {
        return `${wltp} g/km (WLTP)`;
    }

    if (nedc !== null) {
        return `${nedc} g/km (NEDC)`;
    }

    if (hp !== null) {
        return `${hp} CV (PA)`;
    }

    return '—';
}

function formatDate(iso: string): string {
    const [year, month, day] = iso.split('-');

    return `${day}/${month}/${year}`;
}

function segmentPeriodLabel(seg: Segment): string {
    return `Du ${formatDate(seg.effectiveFromInYear)} au ${formatDate(seg.effectiveToInYear)}`;
}
</script>

<template>
    <Card>
        <template #header>
            <div class="flex items-start gap-2">
                <Info :size="16" :stroke-width="1.75" class="mt-0.5 shrink-0 text-blue-600" />
                <div>
                    <h2 class="text-sm font-semibold text-slate-900">
                        Caractéristiques appliquées au calcul
                    </h2>
                    <p class="mt-0.5 text-xs text-slate-500">
                        <template v-if="segments.length === 0">
                            Aucune VFC effective sur cette année.
                        </template>
                        <template v-else-if="segments.length === 1 && segments[0]">
                            Version VFC effective {{ segmentPeriodLabel(segments[0]) }}.
                            Historique complet sur l'onglet Vue d'ensemble.
                        </template>
                        <template v-else>
                            {{ segments.length }} versions VFC se succèdent sur l'année —
                            le calcul est segmenté pour appliquer la bonne version à chaque période.
                        </template>
                    </p>
                </div>
            </div>
        </template>

        <p v-if="segments.length === 0" class="text-sm italic text-slate-500">
            Aucune VFC enregistrée pour ce véhicule sur cette année. Le calcul
            utilise des valeurs par défaut.
        </p>

        <div v-else class="flex flex-col gap-4">
            <div
                v-for="(segment, index) in segments"
                :key="index"
                :class="segments.length > 1 ? 'rounded-lg border border-slate-200 bg-slate-50 p-3' : ''"
            >
                <p
                    v-if="segments.length > 1"
                    class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-500"
                >
                    {{ segmentPeriodLabel(segment) }}
                </p>
                <dl class="grid grid-cols-2 gap-x-6 gap-y-2 text-sm md:grid-cols-3">
                    <div>
                        <dt class="text-xs text-slate-500">Énergie</dt>
                        <dd class="font-medium text-slate-900">
                            {{ energySourceLabel[segment.vfc.energySource] }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500">Norme Euro</dt>
                        <dd class="font-medium text-slate-900">
                            {{ segment.vfc.euroStandard !== null ? euroStandardLabel[segment.vfc.euroStandard] : '—' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500">Catégorie polluants</dt>
                        <dd class="font-medium text-slate-900">
                            {{ pollutantCategoryLabel[segment.vfc.pollutantCategory] }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500">Méthode CO₂</dt>
                        <dd class="font-medium text-slate-900">
                            {{ homologationMethodLabel[segment.vfc.homologationMethod] }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500">CO₂</dt>
                        <dd class="font-medium text-slate-900">
                            {{ co2Display(segment.vfc) }}
                        </dd>
                    </div>
                </dl>
            </div>
        </div>
    </Card>
</template>
