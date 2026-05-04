<script setup lang="ts">
/**
 * Carte « Informations légales » — identité administrative de
 * référence (SIREN, SIRET). Lien INSEE généré à partir du SIREN
 * (annuaire public, pas de clé requise).
 */
import { ExternalLink } from 'lucide-vue-next';
import { computed } from 'vue';
import Card from '@/Components/Ui/Card/Card.vue';

type Company = App.Data.User.Company.CompanyDetailData;

const props = defineProps<{
    company: Company;
}>();

const sirenLink = computed<string | null>(() => {
    if (props.company.siren === null) {
        return null;
    }

    // Annuaire INSEE — recherche par SIREN, lien public
    return `https://annuaire-entreprises.data.gouv.fr/entreprise/${encodeURIComponent(props.company.siren)}`;
});

const hasAnyLegalInfo = computed<boolean>(
    () =>
        props.company.siren !== null
        || props.company.siret !== null,
);
</script>

<template>
    <Card>
        <template #header>
            <h2 class="text-sm font-medium uppercase tracking-wide text-slate-500">
                Informations légales
            </h2>
        </template>

        <dl v-if="hasAnyLegalInfo" class="flex flex-col gap-2.5 text-sm">
            <div v-if="company.legalName" class="flex flex-col gap-0.5">
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">
                    Raison sociale
                </dt>
                <dd class="text-slate-700">
                    {{ company.legalName }}
                </dd>
            </div>

            <div v-if="company.siren" class="flex flex-col gap-0.5">
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">
                    SIREN
                </dt>
                <dd class="flex flex-wrap items-center gap-2">
                    <span class="font-mono tabular-nums text-slate-700">
                        {{ company.siren }}
                    </span>
                    <a
                        v-if="sirenLink"
                        :href="sirenLink"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex items-center gap-1 text-xs text-slate-500 underline-offset-2 hover:text-slate-700 hover:underline"
                    >
                        Vérifier sur l'annuaire INSEE
                        <ExternalLink :size="11" :stroke-width="1.75" />
                    </a>
                </dd>
            </div>

            <div v-if="company.siret" class="flex flex-col gap-0.5">
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">
                    SIRET
                </dt>
                <dd class="font-mono tabular-nums text-slate-700">
                    {{ company.siret }}
                </dd>
            </div>
        </dl>

        <p v-else class="text-sm italic text-slate-400">
            Aucune information légale renseignée.
        </p>
    </Card>
</template>
