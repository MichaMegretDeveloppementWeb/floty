<script setup lang="ts">
/**
 * Page Paramètres > Détection de risque fiscal (Phase 11 D1, ADR-0015
 * § D7 rev. 1.1). Singleton : un seul jeu de seuils pour toute
 * l'application, qui pilote la grille de classification des risques
 * (R-LCD-CHAIN moyen / R-LCD-CHAIN-FORT élevé) lors du recalcul
 * d'une déclaration annuelle.
 *
 * Ces seuils n'altèrent PAS les déclarations déjà émises ; à la
 * prochaine régénération, la nouvelle grille s'applique.
 */
import { Head, useForm } from '@inertiajs/vue3';
import { ShieldAlert } from 'lucide-vue-next';
import { computed } from 'vue';
import UserLayout from '@/Components/Layouts/UserLayout.vue';
import Button from '@/Components/Ui/Button/Button.vue';
import Card from '@/Components/Ui/Card/Card.vue';
import NumberInput from '@/Components/Ui/NumberInput/NumberInput.vue';
import { update as updateRoute } from '@/routes/user/settings/fiscal-risk';

const props = defineProps<{
    settings: App.Data.User.FiscalRiskSettings.FiscalRiskSettingsData;
}>();

const form = useForm({
    maxInterval: props.settings.maxInterval,
    thresholdLow: props.settings.thresholdLow,
    thresholdHigh: props.settings.thresholdHigh,
    countHigh: props.settings.countHigh,
});

// Le projet n'embarque pas de fichier de traduction Laravel : les
// erreurs de validation reviennent en clés brutes (« validation.gt.numeric »).
// On double les règles backend critiques côté Vue pour afficher un
// message clair en français.
const localErrors = computed<Record<string, string>>(() => {
    const errors: Record<string, string> = {};

    if (form.maxInterval !== null && form.maxInterval < 1) {
        errors.max_interval = 'L\'intervalle doit être d\'au moins 1 jour.';
    }

    if (form.thresholdLow !== null && form.thresholdLow < 0) {
        errors.threshold_low = 'Le seuil bas doit être positif ou nul.';
    }

    if (form.thresholdHigh !== null && form.thresholdLow !== null && form.thresholdHigh <= form.thresholdLow) {
        errors.threshold_high = 'Le seuil haut doit être strictement supérieur au seuil bas.';
    }

    if (form.countHigh !== null && form.countHigh < 1) {
        errors.count_high = 'Au moins 1 contrat est requis.';
    }

    return errors;
});

function fieldError(name: string): string | undefined {
    return localErrors.value[name] ?? form.errors[name as keyof typeof form.errors];
}

function submit(): void {
    if (Object.keys(localErrors.value).length > 0) {
        return;
    }

    form.transform((data) => ({
        max_interval: data.maxInterval,
        threshold_low: data.thresholdLow,
        threshold_high: data.thresholdHigh,
        count_high: data.countHigh,
    })).post(updateRoute.url(), {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Paramètres · Détection de risque" />

    <UserLayout>
        <div class="m-auto flex w-full max-w-[48em] flex-col gap-6">
            <header class="flex items-start gap-3">
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-amber-50 text-amber-600">
                    <ShieldAlert :size="22" :stroke-width="1.75" />
                </div>
                <div class="flex flex-col gap-1">
                    <p class="text-xs font-medium tracking-wider text-slate-500 uppercase">
                        Paramètres
                    </p>
                    <h1 class="text-2xl font-semibold text-slate-900">
                        Détection de risque fiscal
                    </h1>
                    <p class="text-sm text-slate-500">
                        Seuils pilotant la classification automatique des contrats LCD
                        en zones de risque (R-LCD-CHAIN moyen, R-LCD-CHAIN-FORT élevé)
                        lors du recalcul d'une déclaration annuelle. Les déclarations
                        déjà émises ne sont pas modifiées rétroactivement.
                    </p>
                </div>
            </header>

            <form class="flex flex-col gap-6" @submit.prevent="submit">
                <Card>
                    <template #header>
                        <h2 class="text-base font-semibold text-slate-900">
                            Chaînage des contrats
                        </h2>
                    </template>

                    <div class="flex flex-col gap-4">
                        <NumberInput
                            v-model="form.maxInterval"
                            label="Intervalle maximal entre 2 LCD"
                            hint="Au-delà de cette durée, deux contrats successifs ne sont plus considérés comme chaînés. Les contrats LLD intercalés (sur n'importe quel véhicule) sont silencieusement ignorés · ils n'affectent pas la chaîne LCD."
                            :min="1"
                            :error="fieldError('max_interval')"
                        >
                            <template #unit>jours</template>
                        </NumberInput>
                    </div>
                </Card>

                <Card>
                    <template #header>
                        <h2 class="text-base font-semibold text-slate-900">
                            Seuils de durée cumulée
                        </h2>
                    </template>

                    <div class="flex flex-col gap-4">
                        <NumberInput
                            v-model="form.thresholdLow"
                            label="Seuil bas"
                            hint="Durée cumulée d'une chaîne LCD à partir de laquelle elle bascule en zone de risque (R-LCD-CHAIN, niveau moyen)."
                            :min="0"
                            :error="fieldError('threshold_low')"
                        >
                            <template #unit>jours</template>
                        </NumberInput>

                        <NumberInput
                            v-model="form.thresholdHigh"
                            label="Seuil haut"
                            hint="Durée cumulée à partir de laquelle la chaîne LCD bascule en zone de risque élevé (R-LCD-CHAIN-FORT)."
                            :min="1"
                            :error="fieldError('threshold_high')"
                        >
                            <template #unit>jours</template>
                        </NumberInput>
                    </div>
                </Card>

                <Card>
                    <template #header>
                        <h2 class="text-base font-semibold text-slate-900">
                            Récidive
                        </h2>
                    </template>

                    <NumberInput
                        v-model="form.countHigh"
                        label="Nombre de contrats successifs"
                        hint="Nombre minimum de contrats LCD chaînés pour basculer immédiatement en risque élevé, indépendamment de la durée."
                        :min="1"
                        :error="fieldError('count_high')"
                    >
                        <template #unit>contrats</template>
                    </NumberInput>
                </Card>

                <div class="flex justify-end">
                    <Button
                        :loading="form.processing"
                        @click="submit"
                    >
                        Enregistrer
                    </Button>
                </div>
            </form>
        </div>
    </UserLayout>
</template>
