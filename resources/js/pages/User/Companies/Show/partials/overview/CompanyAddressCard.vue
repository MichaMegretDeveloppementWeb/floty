<script setup lang="ts">
/**
 * Carte Adresse — affichage minimal, pas de lien externe (Maps retiré
 * car peu utile dans le contexte Floty).
 */
import { MapPin } from 'lucide-vue-next';
import { computed } from 'vue';
import Card from '@/Components/Ui/Card/Card.vue';

type Company = App.Data.User.Company.CompanyDetailData;

const props = defineProps<{
    company: Company;
}>();

const hasAnyAddressLine = computed<boolean>(
    () =>
        props.company.addressLine1 !== null
        || props.company.postalCode !== null
        || props.company.city !== null,
);
</script>

<template>
    <Card>
        <template #header>
            <h2 class="text-sm font-medium uppercase tracking-wide text-slate-500">
                Adresse
            </h2>
        </template>

        <p v-if="hasAnyAddressLine" class="flex items-start gap-2 text-sm leading-relaxed text-slate-700">
            <MapPin :size="14" :stroke-width="1.75" class="mt-0.5 shrink-0 text-slate-400" />
            <span>
                <template v-if="company.addressLine1">{{ company.addressLine1 }}<br></template>
                <template v-if="company.addressLine2">{{ company.addressLine2 }}<br></template>
                <template v-if="company.postalCode || company.city">
                    {{ company.postalCode ?? '' }} {{ company.city ?? '' }}<br>
                </template>
                {{ company.country }}
            </span>
        </p>

        <p v-else class="text-sm italic text-slate-400">
            Aucune adresse renseignée.
        </p>
    </Card>
</template>
