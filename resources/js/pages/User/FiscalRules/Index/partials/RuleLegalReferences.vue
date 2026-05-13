<script setup lang="ts">
/**
 * Rend la liste des références légales d'une règle fiscale (Phase 13
 * D5.11 · ADR-0022 finalisée).
 *
 * **Source de vérité** · les URLs viennent directement du backend, qui
 * les lit depuis les classes PHP de chaque règle. Plus aucune
 * construction d'URL côté front · ce composant lit simplement
 * `entry.url` et fabrique un label cosmétique pour l'affichage.
 *
 * Les entrées sans `url` (cas rare où une référence légale n'aurait pas
 * encore été auditée) sont rendues en texte non-cliquable.
 */
import { computed } from 'vue';

type LegalRef = {
    type?: string;
    article?: string;
    reference?: string;
    paragraph?: string;
    url?: string;
    consulted_at?: string;
};

const props = defineProps<{
    refs: App.Data.User.Fiscal.FiscalRuleListItemData['legalBasis'];
}>();

type Display = {
    label: string;
    title: string;
    url: string;
};

function buildLabel(entry: LegalRef): string {
    if (entry.type === 'CIBS' && entry.article) {
        return `CIBS ${entry.article}`;
    }

    if (entry.type === 'CGI' && entry.article) {
        const para = entry.paragraph ? ` (${entry.paragraph}°)` : '';

        return `CGI ${entry.article}${para}`;
    }

    if (entry.type === 'BOFIP' && entry.reference) {
        return entry.paragraph
            ? `${entry.reference} ${entry.paragraph}`
            : entry.reference;
    }

    if (entry.type === 'NOTICE' && entry.reference) {
        return `Notice ${entry.reference}`;
    }

    return entry.article ?? entry.reference ?? entry.type ?? '';
}

function buildTitle(entry: LegalRef): string {
    if (entry.type === 'CIBS' && entry.article) {
        return `Article ${entry.article} du Code des impositions sur les biens et services (Légifrance)`;
    }

    if (entry.type === 'CGI' && entry.article) {
        const para = entry.paragraph ? `, ${entry.paragraph}°` : '';

        return `Article ${entry.article}${para} du Code général des impôts (Légifrance)`;
    }

    if (entry.type === 'BOFIP' && entry.reference) {
        const para = entry.paragraph ? ` ${entry.paragraph}` : '';

        return `Doctrine BOFiP-Impôts ${entry.reference}${para}`;
    }

    if (entry.type === 'NOTICE' && entry.reference) {
        return `Notice DGFiP ${entry.reference} (impots.gouv.fr)`;
    }

    return buildLabel(entry);
}

const links = computed<Display[]>(() =>
    (props.refs as unknown as LegalRef[])
        .map((entry) => ({
            label: buildLabel(entry),
            title: buildTitle(entry),
            url: entry.url ?? '',
        }))
        .filter((d) => d.label !== ''),
);
</script>

<template>
    <p
        v-if="links.length > 0"
        class="mt-3 flex flex-wrap items-center gap-x-1 gap-y-0.5 font-mono text-xs text-slate-500"
    >
        <template v-for="(link, idx) in links" :key="idx">
            <a
                v-if="link.url"
                :href="link.url"
                :title="link.title"
                target="_blank"
                rel="noopener noreferrer"
                class="text-slate-600 underline decoration-slate-300 underline-offset-2 transition-colors duration-[120ms] ease-out hover:text-slate-900 hover:decoration-slate-600"
            >
                {{ link.label }}
            </a>
            <span v-else>{{ link.label }}</span>
            <span
                v-if="idx < links.length - 1"
                class="text-slate-300"
                aria-hidden="true"
            >
                ·
            </span>
        </template>
    </p>
</template>
