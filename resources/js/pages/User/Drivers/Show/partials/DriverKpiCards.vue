<script setup lang="ts">
import { Building2, CalendarClock, FileText } from 'lucide-vue-next';
import { computed } from 'vue';
import StatCard from '@/Components/Ui/StatCard/StatCard.vue';
import { useDriverAnciennete } from '@/Composables/Driver/useDriverAnciennete';

const props = defineProps<{
    driver: App.Data.User.Driver.DriverData;
}>();

const activeCompaniesCount = computed<number>(
    () => props.driver.memberships.filter((m) => m.isCurrentlyActive).length,
);

const anciennete = computed<string>(() =>
    useDriverAnciennete(props.driver.memberships),
);
</script>

<template>
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <StatCard
            :value="props.driver.contractsCount"
            label="Contrats"
            caption="Tous statuts confondus"
            tone="blue"
        >
            <template #icon>
                <FileText :size="16" :stroke-width="1.75" />
            </template>
        </StatCard>

        <StatCard
            :value="activeCompaniesCount"
            label="Entreprises actives"
            :caption="`Sur ${props.driver.memberships.length} au total`"
            tone="emerald"
        >
            <template #icon>
                <Building2 :size="16" :stroke-width="1.75" />
            </template>
        </StatCard>

        <StatCard
            :value="anciennete"
            label="Ancienneté"
            caption="Depuis la première entrée"
            tone="slate"
        >
            <template #icon>
                <CalendarClock :size="16" :stroke-width="1.75" />
            </template>
        </StatCard>
    </div>
</template>
