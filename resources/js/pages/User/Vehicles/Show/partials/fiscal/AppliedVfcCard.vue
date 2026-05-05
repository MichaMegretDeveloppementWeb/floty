<script setup lang="ts">
/**
 * Encart compact « Caractéristiques fiscales appliquées au calcul »
 * pour l'onglet Fiscalité de la fiche véhicule.
 *
 * **Pourquoi** : le panel Coût plein affiche les *valeurs* utilisées
 * (CO₂ g/km, catégorie polluants…) mais sans contexte de version.
 * Sans cet encart, l'utilisateur voit « 95 g/km » alors qu'il a vu
 * « 85 g/km » sur la carte Caractéristiques fiscales (Vue d'ensemble)
 * → confusion. Cet encart matérialise *quelle version* (period
 * effective_from → effective_to) a été utilisée pour le calcul.
 *
 * Pour l'historique complet → onglet Vue d'ensemble (modale historique).
 *
 * **Note technique** : le pipeline fiscal actuel utilise toujours la
 * VFC en vigueur au jour J peu importe l'année calculée
 * (cf. `FiscalPipeline::execute()`). Donc `appliedVfc` reflète la
 * réalité brute, même si conceptuellement on aurait pu attendre la
 * VFC effective à l'année. C'est un point à creuser ailleurs.
 */
import { Info } from 'lucide-vue-next';
import { computed } from 'vue';
import Card from '@/Components/Ui/Card/Card.vue';
import {
    energySourceLabel,
    euroStandardLabel,
    homologationMethodLabel,
    pollutantCategoryLabel,
} from '@/Utils/labels/vehicleEnumLabels';

type Vfc = App.Data.User.Vehicle.VehicleFiscalCharacteristicsData;

const props = defineProps<{
    appliedVfc: Vfc | null;
}>();

const co2Display = computed<string>(() => {
    const vfc = props.appliedVfc;

    if (vfc === null) {
        return '—';
    }

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
});

const periodDisplay = computed<string>(() => {
    const vfc = props.appliedVfc;

    if (vfc === null) {
        return 'Aucune VFC enregistrée';
    }

    const from = formatDate(vfc.effectiveFrom);
    const to = vfc.effectiveTo === null ? 'en cours' : formatDate(vfc.effectiveTo);

    return `du ${from} ${vfc.effectiveTo === null ? '(' + to + ')' : 'au ' + to}`;
});

function formatDate(iso: string): string {
    const [year, month, day] = iso.split('-');

    return `${day}/${month}/${year}`;
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
                        Version VFC effective {{ periodDisplay }}. Historique
                        complet sur l'onglet Vue d'ensemble.
                    </p>
                </div>
            </div>
        </template>

        <dl
            v-if="appliedVfc !== null"
            class="grid grid-cols-2 gap-x-6 gap-y-2 text-sm md:grid-cols-3"
        >
            <div>
                <dt class="text-xs text-slate-500">Énergie</dt>
                <dd class="font-medium text-slate-900">
                    {{ energySourceLabel[appliedVfc.energySource] }}
                </dd>
            </div>
            <div>
                <dt class="text-xs text-slate-500">Norme Euro</dt>
                <dd class="font-medium text-slate-900">
                    {{ appliedVfc.euroStandard !== null ? euroStandardLabel[appliedVfc.euroStandard] : '—' }}
                </dd>
            </div>
            <div>
                <dt class="text-xs text-slate-500">Catégorie polluants</dt>
                <dd class="font-medium text-slate-900">
                    {{ pollutantCategoryLabel[appliedVfc.pollutantCategory] }}
                </dd>
            </div>
            <div>
                <dt class="text-xs text-slate-500">Méthode CO₂</dt>
                <dd class="font-medium text-slate-900">
                    {{ homologationMethodLabel[appliedVfc.homologationMethod] }}
                </dd>
            </div>
            <div>
                <dt class="text-xs text-slate-500">CO₂</dt>
                <dd class="font-medium text-slate-900">
                    {{ co2Display }}
                </dd>
            </div>
        </dl>
        <p v-else class="text-sm italic text-slate-500">
            Aucune VFC enregistrée pour ce véhicule. Le calcul utilise des
            valeurs par défaut.
        </p>
    </Card>
</template>
