<script setup lang="ts">
/**
 * Carte Adresse — format respiré + lien Maps généré à la volée à
 * partir des champs renseignés.
 */
import { ExternalLink, MapPin } from 'lucide-vue-next';
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

const mapsUrl = computed<string | null>(() => {
    if (!hasAnyAddressLine.value) {
        return null;
    }

    const parts = [
        props.company.addressLine1,
        props.company.addressLine2,
        [props.company.postalCode, props.company.city].filter((p) => p !== null && p !== '').join(' '),
        props.company.country,
    ]
        .filter((p): p is string => typeof p === 'string' && p !== '');

    return `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(parts.join(', '))}`;
});
</script>

<template>
    <Card>
        <template #header>
            <h2 class="text-sm font-medium uppercase tracking-wide text-slate-500">
                Adresse
            </h2>
        </template>

        <div v-if="hasAnyAddressLine" class="flex flex-col gap-3">
            <p class="flex items-start gap-2 text-sm leading-relaxed text-slate-700">
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

            <a
                v-if="mapsUrl"
                :href="mapsUrl"
                target="_blank"
                rel="noopener noreferrer"
                class="inline-flex items-center gap-1 self-start text-xs text-slate-500 underline-offset-2 hover:text-slate-700 hover:underline"
            >
                Voir sur Maps
                <ExternalLink :size="11" :stroke-width="1.75" />
            </a>
        </div>

        <p v-else class="text-sm italic text-slate-400">
            Aucune adresse renseignée.
        </p>
    </Card>
</template>
