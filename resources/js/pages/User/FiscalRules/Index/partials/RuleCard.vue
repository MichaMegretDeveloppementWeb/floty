<script setup lang="ts">
import { ArrowUpRight } from 'lucide-vue-next';
import Badge from '@/Components/Ui/Badge/Badge.vue';
import { useRuleCard } from '@/Composables/FiscalRule/Index/useRuleCard';
import { formatDateFr } from '@/Utils/format/formatDateFr';
import RuleBracketsFlat from './RuleBracketsFlat.vue';
import RuleBracketsProgressive from './RuleBracketsProgressive.vue';
import RuleExample from './RuleExample.vue';
import RuleLegalReferences from './RuleLegalReferences.vue';

type Rule = App.Data.User.Fiscal.FiscalRuleListItemData;
type RelatedRule = { code: string; title: string };

const props = defineProps<{
    code: string;
    rule: Rule | undefined;
    relatedRules?: RelatedRule[];
    isFlashed?: boolean;
}>();

const emit = defineEmits<{
    'navigate-to-rule': [code: string];
}>();

const { taxLabel, taxBadgeTone, content } = useRuleCard({ rule: props.rule });
</script>

<template>
    <article
        :id="`rule-${code}`"
        class="overflow-hidden rounded-xl border border-slate-200 bg-white transition-shadow duration-300 ease-out"
        :class="[
            rule && !rule.isActive ? 'opacity-75' : '',
            isFlashed ? 'shadow-[0_0_0_3px_rgb(252_211_77_/_0.5)]' : '',
        ]"
    >
        <!-- Strip header · pill code + période + taxes + status -->
        <div
            class="flex flex-wrap items-center gap-x-3 gap-y-2 border-b border-slate-100 bg-slate-50/60 px-6 py-3"
        >
            <span
                class="rounded-md bg-white px-2 py-1 font-mono text-xs font-semibold tracking-wide text-slate-700 ring-1 ring-slate-200 ring-inset"
            >
                {{ code }}
            </span>
            <span
                v-if="rule"
                class="font-mono text-xs text-slate-500"
            >
                {{ formatDateFr(rule.applicabilityStartInYear) }}
                <span class="text-slate-300">→</span>
                {{ formatDateFr(rule.applicabilityEndInYear) }}
            </span>

            <!-- Spacer pour pousser taxes + status à droite sur grand écran -->
            <div class="ml-auto flex flex-wrap items-center gap-2">
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
                    class="rounded-md border border-amber-200 bg-amber-50 px-2 py-0.5 text-[10px] font-medium tracking-wide text-amber-700 uppercase"
                >
                    Non appliqué
                </span>
            </div>
        </div>

        <!-- Corps de la carte -->
        <div class="p-6">
            <!-- Titre + pitch -->
            <h3 class="text-base font-semibold tracking-tight text-slate-900">
                {{ content?.title }}
            </h3>
            <p class="mt-2 text-sm leading-relaxed text-slate-600">
                {{ content?.pitch }}
            </p>

            <!-- Condition / Effet -->
            <div
                v-if="content?.appliesWhen || content?.effect"
                class="mt-6 flex flex-col gap-2.5 rounded-lg border border-slate-100 bg-slate-50/60 p-4 text-sm"
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
                class="mt-6 text-sm leading-relaxed text-slate-600"
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

            <!-- Cross-références pédagogiques · bien distinct des refs légales -->
            <div
                v-if="relatedRules && relatedRules.length > 0"
                class="mt-6 flex flex-wrap items-center gap-2"
            >
                <span class="eyebrow">Voir aussi</span>
                <button
                    v-for="related in relatedRules"
                    :key="related.code"
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-md border border-slate-200 bg-white px-2.5 py-1 text-xs font-medium text-slate-700 transition-colors duration-[120ms] ease-out cursor-pointer hover:border-slate-400 hover:bg-slate-50 hover:text-slate-900"
                    :title="`Aller à la règle ${related.code} · ${related.title}`"
                    @click="emit('navigate-to-rule', related.code)"
                >
                    <span class="font-mono text-[10px] text-slate-500">
                        {{ related.code }}
                    </span>
                    <span>{{ related.title }}</span>
                    <ArrowUpRight :size="12" :stroke-width="2" class="text-slate-400" aria-hidden="true" />
                </button>
            </div>

            <RuleLegalReferences
                v-if="rule"
                :refs="rule.legalBasis"
            />
        </div>
    </article>
</template>
