<script setup lang="ts">
import RuleCard from './RuleCard.vue';

type Rule = App.Data.User.Fiscal.FiscalRuleListItemData;

defineProps<{
    title: string;
    subtitle: string;
    codes: string[];
    rulesByCode: Record<string, Rule>;
}>();
</script>

<template>
    <section class="flex flex-col gap-12">
        <header>
            <h2 class="text-lg font-semibold tracking-tight text-slate-900">
                {{ title }}
            </h2>
            <p class="mt-0.5 text-sm text-slate-600">{{ subtitle }}</p>
        </header>

        <ul class="flex flex-col gap-12">
            <RuleCard
                v-for="code in codes"
                :key="code"
                :code="code"
                :rule="rulesByCode[code]"
            />
        </ul>
    </section>
</template>
