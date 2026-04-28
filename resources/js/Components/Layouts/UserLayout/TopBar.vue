<script setup lang="ts">
import { Menu } from 'lucide-vue-next';
import UserMenu from '@/Components/Layouts/UserLayout/UserMenu.vue';
import YearSelector from '@/Components/Layouts/UserLayout/YearSelector.vue';
import SearchInput from '@/Components/Ui/SearchInput/SearchInput.vue';
import { useTopBar } from '@/Composables/Layout/UserLayout/useTopBar';

const year = defineModel<number>('year', { required: true });

defineProps<{
    minYear: number;
    maxYear: number;
}>();

const emit = defineEmits<{
    'toggle-sidebar': [];
}>();

const { search, fullName, initials } = useTopBar();
</script>

<template>
    <header
        class="sticky top-0 z-10 flex h-16 items-center gap-3 border-b border-slate-200 bg-white px-4 md:gap-4 md:px-8"
    >
        <button
            type="button"
            aria-label="Ouvrir la navigation"
            class="flex size-9 shrink-0 items-center justify-center rounded-lg text-slate-600 transition-colors duration-[120ms] ease-out hover:bg-slate-100 hover:text-slate-900 focus-visible:ring-2 focus-visible:ring-slate-100 focus-visible:outline-none md:hidden"
            @click="emit('toggle-sidebar')"
        >
            <Menu :size="18" :stroke-width="1.75" />
        </button>

        <div class="min-w-0 flex-1">
            <SearchInput
                v-model="search"
                placeholder="Rechercher véhicule, entreprise…"
                aria-label="Recherche globale"
                :shortcut="['⌘', 'K']"
            />
        </div>

        <YearSelector v-model="year" :min="minYear" :max="maxYear" />

        <div class="hidden h-8 w-px bg-slate-200 md:block" aria-hidden="true" />

        <UserMenu
            :name="fullName"
            :initials="initials"
            role="Gestionnaire flotte"
        />
    </header>
</template>
