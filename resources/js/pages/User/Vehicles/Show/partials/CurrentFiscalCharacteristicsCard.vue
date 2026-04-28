<script setup lang="ts">
import { computed } from 'vue';
import Badge from '@/Components/Ui/Badge/Badge.vue';
import Card from '@/Components/Ui/Card/Card.vue';
import { formatDateFr } from '@/Utils/format/formatDateFr';

const props = defineProps<{
    fiscal: App.Data.User.Vehicle.VehicleFiscalCharacteristicsData | null;
}>();

const co2Display = computed<{ value: string; label: string } | null>(() => {
    const f = props.fiscal;

    if (!f) {
        return null;
    }

    if (f.co2Wltp !== null) {
        return { value: `${f.co2Wltp} g/km`, label: 'CO₂ WLTP' };
    }

    if (f.co2Nedc !== null) {
        return { value: `${f.co2Nedc} g/km`, label: 'CO₂ NEDC' };
    }

    if (f.taxableHorsepower !== null) {
        return { value: `${f.taxableHorsepower} CV`, label: 'Puissance admin.' };
    }

    return null;
});

const stats = computed<{ value: string; label: string }[]>(() => {
    const f = props.fiscal;

    if (!f) {
        return [];
    }

    const items: { value: string; label: string }[] = [
        { value: f.receptionCategory, label: 'Catégorie réception' },
        { value: f.vehicleUserType, label: "Type d'usage" },
        { value: f.bodyType, label: 'Carrosserie' },
        { value: f.energySource, label: 'Énergie' },
        { value: f.homologationMethod, label: 'Méthode homologation' },
        { value: f.pollutantCategory, label: 'Catégorie polluants' },
        { value: `${f.seatsCount}`, label: 'Places assises' },
    ];

    if (f.euroStandard) {
        items.push({ value: f.euroStandard, label: 'Norme Euro' });
    }

    if (co2Display.value) {
        items.push(co2Display.value);
    }

    if (f.kerbMass !== null) {
        items.push({ value: `${f.kerbMass} kg`, label: 'Masse à vide' });
    }

    return items;
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
                        Caractéristiques fiscales actives
                    </h2>
                    <p v-if="props.fiscal" class="mt-0.5 text-xs text-slate-500">
                        Effective depuis le
                        {{ formatDateFr(props.fiscal.effectiveFrom) }}
                    </p>
                </div>
            </div>
        </template>

        <p
            v-if="!props.fiscal"
            class="text-sm text-slate-500 italic"
        >
            Aucune version fiscale active pour ce véhicule.
        </p>

        <div
            v-else
            class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4"
        >
            <div
                v-for="stat in stats"
                :key="stat.label"
                class="flex flex-col gap-0.5 rounded-lg bg-slate-50/70 px-3 py-2.5"
            >
                <p
                    class="text-xs font-medium tracking-wide text-slate-500 uppercase"
                >
                    {{ stat.label }}
                </p>
                <p class="text-sm font-semibold text-slate-900">
                    {{ stat.value }}
                </p>
            </div>
        </div>

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
