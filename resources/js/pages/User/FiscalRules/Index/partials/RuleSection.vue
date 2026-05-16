<script setup lang="ts">
import RuleCard from './RuleCard.vue';

type Rule = App.Data.User.Fiscal.FiscalRuleListItemData;
type RelatedRule = { code: string; title: string };

defineProps<{
    title: string;
    subtitle: string;
    codes: string[];
    rulesByCode: Record<string, Rule>;
    relatedRulesFor: (code: string) => RelatedRule[];
    navigateToRule: (code: string) => void;
    flashedRuleCode: string | null;
}>();
</script>

<template>
    <section class="flex flex-col gap-6">
        <header class="flex flex-col gap-1.5 border-l-2 border-slate-200 pl-4">
            <p class="eyebrow">Section</p>
            <h2 class="text-xl font-semibold tracking-tight text-slate-900">
                {{ title }}
            </h2>
            <p class="max-w-3xl text-sm leading-relaxed text-slate-600">
                {{ subtitle }}
            </p>
        </header>

        <ul v-if="codes.length > 0" class="flex flex-col gap-8">
            <li v-for="code in codes" :key="code">
                <RuleCard
                    :code="code"
                    :rule="rulesByCode[code]"
                    :related-rules="relatedRulesFor(code)"
                    :is-flashed="flashedRuleCode === code"
                    @navigate-to-rule="navigateToRule"
                />
            </li>
        </ul>

        <p
            v-else
            class="rounded-lg border border-dashed border-slate-200 px-4 py-3 text-sm italic text-slate-400"
        >
            Aucune règle dans cette section pour cet exercice.
        </p>
    </section>
</template>
