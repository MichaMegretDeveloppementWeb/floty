<script setup lang="ts">
/**
 * Renders a Company option for SearchableSelect dropdowns.
 * Colored short-code dot plus the legal name.
 */
import { computed } from 'vue';

type CompanyColor = App.Enums.Company.CompanyColor;

const props = defineProps<{
    company: App.Data.User.Company.CompanyOptionData;
}>();

const dotClasses = computed<string>(() => {
    const color = (props.company.color ?? 'indigo') as CompanyColor;

    switch (color) {
        case 'indigo':
            return 'bg-company-indigo';
        case 'emerald':
            return 'bg-company-emerald';
        case 'amber':
            return 'bg-company-amber';
        case 'rose':
            return 'bg-company-rose';
        case 'violet':
            return 'bg-company-violet';
        case 'teal':
            return 'bg-company-teal';
        case 'orange':
            return 'bg-company-orange';
        case 'cyan':
            return 'bg-company-cyan';
        default: {
            const _exhaustive: never = color;

            throw new Error(`Couleur non gérée : ${_exhaustive as string}`);
        }
    }
});
</script>

<template>
    <div class="flex min-w-0 items-center gap-2">
        <span
            :class="[
                'flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-[10px] font-semibold tracking-tight text-white uppercase',
                dotClasses,
            ]"
            aria-hidden="true"
        >
            {{ company.shortCode }}
        </span>
        <span class="truncate text-sm text-slate-900">
            {{ company.legalName }}
        </span>
    </div>
</template>
