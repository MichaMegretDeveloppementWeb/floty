<script setup lang="ts">
import { useOfficialLegalLinks } from '@/Composables/Shared/useOfficialLegalLinks';
import type { LegalReference } from '@/Composables/Shared/useOfficialLegalLinks';

const props = defineProps<{
    refs: App.Data.User.Fiscal.FiscalRuleListItemData['legalBasis'];
}>();

const { resolveAll } = useOfficialLegalLinks();

const links = resolveAll(props.refs as unknown as LegalReference[]);
</script>

<template>
    <p
        v-if="refs.length > 0"
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
