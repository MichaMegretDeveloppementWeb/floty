<script setup lang="ts">
import CompanyTag from '@/Components/Ui/CompanyTag/CompanyTag.vue';

defineProps<{
    entries: App.Data.User.Planning.WeekCompanyPresenceData[];
}>();
</script>

<template>
    <section v-if="entries.length > 0">
        <p class="eyebrow mb-2">Entreprises présentes</p>
        <ul class="flex flex-col gap-1">
            <li
                v-for="entry in entries"
                :key="entry.company.id"
                class="flex items-center justify-between gap-3 rounded-md bg-slate-50 px-3 py-2 text-sm"
            >
                <CompanyTag
                    :name="entry.company.shortCode"
                    :initials="entry.company.shortCode"
                    :color="entry.company.color"
                />
                <div class="flex-1 truncate text-slate-700">
                    {{ entry.company.legalName }}
                </div>
                <span class="font-mono text-xs text-slate-500">
                    {{ entry.days }} j
                </span>
            </li>
        </ul>
    </section>
</template>
