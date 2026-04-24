<script setup lang="ts">
import SearchInput from '@/Components/Ui/SearchInput/SearchInput.vue';
import UserMenu from '@/Components/Layouts/UserLayout/UserMenu.vue';
import YearSelector from '@/Components/Layouts/UserLayout/YearSelector.vue';
import { ref } from 'vue';

const year = defineModel<number>('year', { required: true });
const search = ref<string>('');
</script>

<template>
    <header
        class="sticky top-0 z-10 flex h-16 items-center gap-4 border-b border-slate-200 bg-white px-8"
    >
        <div class="flex-1">
            <SearchInput
                v-model="search"
                placeholder="Rechercher véhicule, entreprise…"
                aria-label="Recherche globale"
                :shortcut="['⌘', 'K']"
            />
        </div>
        <YearSelector v-model="year" />
        <div class="h-8 w-px bg-slate-200" aria-hidden="true" />
        <UserMenu
            name="R. Martin"
            initials="RM"
            role="Gestionnaire flotte"
        />
    </header>
</template>
