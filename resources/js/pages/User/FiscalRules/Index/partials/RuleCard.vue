<script setup lang="ts">
import Badge from '@/Components/Ui/Badge/Badge.vue';
import { useRuleCard } from '@/Composables/FiscalRule/Index/useRuleCard';
import { formatDateFr } from '@/Utils/format/formatDateFr';
import RuleBracketsFlat from './RuleBracketsFlat.vue';
import RuleBracketsProgressive from './RuleBracketsProgressive.vue';
import RuleExample from './RuleExample.vue';
import RuleLegalReferences from './RuleLegalReferences.vue';

type Rule = App.Data.User.Fiscal.FiscalRuleListItemData;

const props = defineProps<{
    code: string;
    rule: Rule | undefined;
}>();

const { taxLabel, taxBadgeTone, content } = useRuleCard({ rule: props.rule });
</script>

<template>
    <article
        class="rounded-xl border border-slate-200 bg-white p-6"
        :class="rule && !rule.isActive ? 'opacity-75' : ''"
    >
        <!-- Meta ligne · code + période + taxes -->
        <div class="mb-3 flex flex-wrap items-center gap-x-3 gap-y-1.5">
            <span class="font-mono text-[11px] font-semibold tracking-wide text-slate-500">
                {{ code }}
            </span>
            <span
                v-if="rule"
                class="font-mono text-[11px] text-slate-400"
            >
                {{ formatDateFr(rule.applicabilityStartInYear) }} → {{ formatDateFr(rule.applicabilityEndInYear) }}
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
            <span
                v-if="rule && !rule.isActive"
                class="rounded-md border border-slate-200 bg-slate-50 px-1.5 py-0.5 text-[10px] font-medium text-slate-500"
            >
                Non appliqué dans Floty
            </span>
        </div>

        <!-- Titre + pitch -->
        <h3 class="text-lg font-semibold tracking-tight text-slate-900">
            {{ content?.title }}
        </h3>
        <p class="mt-1.5 text-[15px] leading-relaxed text-slate-600">
            {{ content?.pitch }}
        </p>

        <!-- Condition / Effet -->
        <div
            v-if="content?.appliesWhen || content?.effect"
            class="mt-4 flex flex-col gap-2 rounded-lg border border-slate-100 bg-slate-50/60 p-4 text-[15px]"
        >
            <div v-if="content?.appliesWhen" class="flex gap-3">
                <span class="w-12 shrink-0 pt-0.5 font-mono text-[10px] font-semibold tracking-wider text-slate-500 uppercase">
                    Si
                </span>
                <span class="text-slate-700">{{ content.appliesWhen }}</span>
            </div>
            <div v-if="content?.effect" class="flex gap-3">
                <span class="w-12 shrink-0 pt-0.5 font-mono text-[10px] font-semibold tracking-wider text-slate-500 uppercase">
                    Alors
                </span>
                <span class="text-slate-700">{{ content.effect }}</span>
            </div>
        </div>

        <!-- Body -->
        <p
            v-if="content?.body"
            class="mt-4 text-[15px] leading-relaxed text-slate-600"
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
