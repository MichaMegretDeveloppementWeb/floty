<script setup lang="ts">
/**
 * Contact card on the Company overview. Always rendered (with an empty
 * state message) because the DTO keeps contact fields optional.
 */
import { Mail, Phone, User } from 'lucide-vue-next';
import { computed } from 'vue';
import Card from '@/Components/Ui/Card/Card.vue';

type Company = App.Data.User.Company.CompanyDetailData;

const props = defineProps<{
    company: Company;
}>();

const hasAnyContact = computed<boolean>(
    () =>
        props.company.contactName !== null
        || props.company.contactEmail !== null
        || props.company.contactPhone !== null,
);
</script>

<template>
    <Card>
        <template #header>
            <h2 class="text-sm font-medium uppercase tracking-wide text-slate-500">
                Contact
            </h2>
        </template>

        <ul v-if="hasAnyContact" class="flex flex-col gap-2 text-sm text-slate-700">
            <li v-if="company.contactName" class="flex items-center gap-2">
                <User :size="14" :stroke-width="1.75" class="shrink-0 text-slate-400" />
                {{ company.contactName }}
            </li>
            <li v-if="company.contactEmail" class="flex items-center gap-2">
                <Mail :size="14" :stroke-width="1.75" class="shrink-0 text-slate-400" />
                <a
                    :href="`mailto:${company.contactEmail}`"
                    class="text-slate-700 underline-offset-2 hover:text-slate-900 hover:underline"
                >
                    {{ company.contactEmail }}
                </a>
            </li>
            <li v-if="company.contactPhone" class="flex items-center gap-2">
                <Phone :size="14" :stroke-width="1.75" class="shrink-0 text-slate-400" />
                <a
                    :href="`tel:${company.contactPhone}`"
                    class="text-slate-700 underline-offset-2 hover:text-slate-900 hover:underline"
                >
                    {{ company.contactPhone }}
                </a>
            </li>
        </ul>

        <p v-else class="text-sm italic text-slate-400">
            Aucun contact renseigné.
        </p>
    </Card>
</template>
