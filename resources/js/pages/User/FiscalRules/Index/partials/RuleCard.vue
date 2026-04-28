<script setup lang="ts">
import Badge from '@/Components/Ui/Badge/Badge.vue';
import StatusPill from '@/Components/Ui/StatusPill/StatusPill.vue';
import { useRuleCard } from '@/Composables/FiscalRule/Index/useRuleCard';
import RuleBracketsFlat from './RuleBracketsFlat.vue';
import RuleBracketsProgressive from './RuleBracketsProgressive.vue';
import RuleExample from './RuleExample.vue';
import RuleLegalReferences from './RuleLegalReferences.vue';

type Rule = App.Data.User.Fiscal.FiscalRuleListItemData;

const props = defineProps<{
    code: string;
    rule: Rule | undefined;
}>();

const { taxLabel, taxBadgeTone, content } = useRuleCard(props);
</script>

<template>
    <article
        class="rounded-xl border border-slate-200 bg-white p-5 transition-shadow duration-[120ms] ease-out hover:shadow-sm"
        :class="rule && !rule.isActive ? 'opacity-70' : ''"
    >
        <div class="mb-2 flex flex-wrap items-center gap-2">
            <span class="font-mono text-xs font-semibold text-slate-500">
                {{ code }}
            </span>
            <Badge
                v-if="rule?.taxesConcerned.length"
                :tone="taxBadgeTone(rule.taxesConcerned)"
            >
                <template
                    v-for="(tax, i) in rule.taxesConcerned"
                    :key="tax"
                >
                    {{ taxLabel[tax] ?? tax
                    }}<span
                        v-if="i < rule.taxesConcerned.length - 1"
                    >·</span>
                </template>
            </Badge>
            <StatusPill
                v-if="rule && !rule.isActive"
                tone="slate"
            >
                Non applicable dans l'application
            </StatusPill>
        </div>

        <h3 class="text-base font-semibold text-slate-900">
            {{ content?.title }}
        </h3>
        <p class="mt-1 text-base leading-relaxed text-slate-700">
            {{ content?.pitch }}
        </p>

        <!-- Condition / Effet -->
        <div
            v-if="content?.appliesWhen || content?.effect"
            class="mt-3 flex flex-col gap-2 rounded-lg bg-slate-50 p-3 text-base"
        >
            <div v-if="content?.appliesWhen" class="flex gap-2">
                <span class="w-16 shrink-0 pt-0.5 font-mono text-xs font-semibold text-slate-500">
                    Si
                </span>
                <span class="text-slate-700">{{ content.appliesWhen }}</span>
            </div>
            <div v-if="content?.effect" class="flex gap-2">
                <span class="w-16 shrink-0 pt-0.5 font-mono text-xs font-semibold text-slate-500">
                    Alors
                </span>
                <span class="text-slate-700">{{ content.effect }}</span>
            </div>
        </div>

        <!-- Body -->
        <p
            v-if="content?.body"
            class="mt-3 text-base leading-relaxed text-slate-600"
        >
            {{ content.body }}
        </p>

        <RuleBracketsProgressive
            v-if="content?.progressiveBrackets"
            :brackets="content.progressiveBrackets"
        />
        <RuleBracketsFlat
            v-if="content?.flatBrackets"
            :brackets="content.flatBrackets"
        />
        <RuleExample
            v-if="content?.example"
            :example="content.example"
        />
        <RuleLegalReferences
            v-if="rule"
            :refs="rule.legalBasis"
        />
    </article>
</template>
