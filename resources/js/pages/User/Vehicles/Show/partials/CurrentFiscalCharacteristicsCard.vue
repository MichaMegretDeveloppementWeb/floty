<script setup lang="ts">
import { computed } from 'vue';
import Badge from '@/Components/Ui/Badge/Badge.vue';
import Card from '@/Components/Ui/Card/Card.vue';
import { formatDateFr } from '@/Utils/format/formatDateFr';

const props = defineProps<{
    fiscal: App.Data.User.Vehicle.VehicleFiscalCharacteristicsData | null;
}>();

const co2Display = computed<string | null>(() => {
    const f = props.fiscal;

    if (!f) {
        return null;
    }

    if (f.co2Wltp !== null) {
        return `${f.co2Wltp} g/km (WLTP)`;
    }

    if (f.co2Nedc !== null) {
        return `${f.co2Nedc} g/km (NEDC)`;
    }

    if (f.taxableHorsepower !== null) {
        return `${f.taxableHorsepower} CV (PA)`;
    }

    return null;
});

const advancedFlags = computed<string[]>(() => {
    const f = props.fiscal;

    if (!f) {
        return [];
    }

    const candidates: { active: boolean; label: string }[] = [
        { active: f.handicapAccess, label: 'Accès handicap' },
        { active: f.n1PassengerTransport, label: 'N1 transport voyageurs' },
        { active: f.n1RemovableSecondRowSeat, label: 'N1 banquette amovible' },
        { active: f.m1SpecialUse, label: 'M1 usage spécial' },
        { active: f.n1SkiLiftUse, label: 'N1 remontée mécanique' },
        {
            active: f.affectedToExemptedActivityPercent > 0,
            label: `Activité exonérée ${f.affectedToExemptedActivityPercent}%`,
        },
    ];

    return candidates.filter((c) => c.active).map((c) => c.label);
});
</script>

<template>
    <Card>
        <template #header>
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-base font-semibold text-slate-900">
                        Caractéristiques fiscales actuelles
                    </h2>
                    <p v-if="props.fiscal" class="mt-0.5 text-xs text-slate-500">
                        Effective depuis le
                        {{ formatDateFr(props.fiscal.effectiveFrom) }}
                    </p>
                </div>
                <Badge v-if="props.fiscal" tone="emerald">Courante</Badge>
            </div>
        </template>

        <p
            v-if="!props.fiscal"
            class="text-sm text-slate-500 italic"
        >
            Aucune version fiscale active pour ce véhicule.
        </p>

        <dl
            v-else
            class="grid grid-cols-1 gap-x-8 gap-y-3 text-sm sm:grid-cols-2 lg:grid-cols-3"
        >
            <div class="flex flex-col">
                <dt class="text-xs text-slate-400 uppercase">
                    Catégorie de réception
                </dt>
                <dd class="font-medium text-slate-700">
                    {{ props.fiscal.receptionCategory }}
                </dd>
            </div>
            <div class="flex flex-col">
                <dt class="text-xs text-slate-400 uppercase">Type d'usage</dt>
                <dd class="font-medium text-slate-700">
                    {{ props.fiscal.vehicleUserType }}
                </dd>
            </div>
            <div class="flex flex-col">
                <dt class="text-xs text-slate-400 uppercase">Carrosserie</dt>
                <dd class="font-medium text-slate-700">
                    {{ props.fiscal.bodyType }}
                </dd>
            </div>
            <div class="flex flex-col">
                <dt class="text-xs text-slate-400 uppercase">Places assises</dt>
                <dd class="font-medium text-slate-700">
                    {{ props.fiscal.seatsCount }}
                </dd>
            </div>
            <div class="flex flex-col">
                <dt class="text-xs text-slate-400 uppercase">Énergie</dt>
                <dd class="font-medium text-slate-700">
                    {{ props.fiscal.energySource }}
                </dd>
            </div>
            <div
                v-if="props.fiscal.euroStandard"
                class="flex flex-col"
            >
                <dt class="text-xs text-slate-400 uppercase">Norme Euro</dt>
                <dd class="font-medium text-slate-700">
                    {{ props.fiscal.euroStandard }}
                </dd>
            </div>
            <div class="flex flex-col">
                <dt class="text-xs text-slate-400 uppercase">
                    Catégorie polluants
                </dt>
                <dd class="font-medium text-slate-700">
                    {{ props.fiscal.pollutantCategory }}
                </dd>
            </div>
            <div class="flex flex-col">
                <dt class="text-xs text-slate-400 uppercase">
                    Méthode d'homologation
                </dt>
                <dd class="font-medium text-slate-700">
                    {{ props.fiscal.homologationMethod }}
                </dd>
            </div>
            <div v-if="co2Display" class="flex flex-col">
                <dt class="text-xs text-slate-400 uppercase">
                    Émissions CO₂ / Puissance
                </dt>
                <dd class="font-medium text-slate-700">
                    {{ co2Display }}
                </dd>
            </div>
            <div
                v-if="props.fiscal.kerbMass !== null"
                class="flex flex-col"
            >
                <dt class="text-xs text-slate-400 uppercase">Masse à vide</dt>
                <dd class="font-medium text-slate-700">
                    {{ props.fiscal.kerbMass }} kg
                </dd>
            </div>
            <div
                v-if="props.fiscal.underlyingCombustionEngineType"
                class="flex flex-col"
            >
                <dt class="text-xs text-slate-400 uppercase">
                    Moteur thermique sous-jacent
                </dt>
                <dd class="font-medium text-slate-700">
                    {{ props.fiscal.underlyingCombustionEngineType }}
                </dd>
            </div>
        </dl>

        <div
            v-if="props.fiscal && advancedFlags.length > 0"
            class="mt-4 flex flex-wrap gap-2 border-t border-slate-100 pt-3"
        >
            <Badge
                v-for="flag in advancedFlags"
                :key="flag"
                tone="blue"
            >
                {{ flag }}
            </Badge>
        </div>
    </Card>
</template>
