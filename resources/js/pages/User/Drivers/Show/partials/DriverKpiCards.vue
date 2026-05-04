<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Building2, FileText } from 'lucide-vue-next';
import { computed } from 'vue';
import StatCard from '@/Components/Ui/StatCard/StatCard.vue';
import { index as contractsIndexRoute } from '@/routes/user/contracts';

const props = defineProps<{
    driver: App.Data.User.Driver.DriverData;
}>();

const activeCompaniesCount = computed<number>(
    () => props.driver.memberships.filter((m) => m.isCurrentlyActive).length,
);

const contractsHref = computed<string>(() =>
    contractsIndexRoute.url({ query: { driverId: props.driver.id } }),
);
</script>

<template>
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <Link
            :href="contractsHref"
            class="block rounded-xl transition-shadow duration-[120ms] ease-out hover:shadow-md"
        >
            <StatCard
                :value="props.driver.contractsCount"
                label="Contrats"
                caption="Voir les contrats du conducteur"
                tone="blue"
            >
                <template #icon>
                    <FileText :size="16" :stroke-width="1.75" />
                </template>
            </StatCard>
        </Link>

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
    </div>
</template>
