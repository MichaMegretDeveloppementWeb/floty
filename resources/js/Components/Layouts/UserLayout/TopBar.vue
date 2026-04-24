<script setup lang="ts">
import SearchInput from '@/Components/Ui/SearchInput/SearchInput.vue';
import UserMenu from '@/Components/Layouts/UserLayout/UserMenu.vue';
import YearSelector from '@/Components/Layouts/UserLayout/YearSelector.vue';
import { Menu } from 'lucide-vue-next';
import { ref } from 'vue';

const year = defineModel<number>('year', { required: true });

const emit = defineEmits<{
    'toggle-sidebar': [];
}>();

const search = ref<string>('');
</script>

<template>
    <header
        class="sticky top-0 z-10 flex h-16 items-center gap-3 border-b border-slate-200 bg-white px-4 md:gap-4 md:px-8"
    >
        <button
            type="button"
            aria-label="Ouvrir la navigation"
            class="flex size-9 shrink-0 items-center justify-center rounded-lg text-slate-600 transition-colors duration-[120ms] ease-out hover:bg-slate-100 hover:text-slate-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-100 md:hidden"
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

        <YearSelector v-model="year" />

        <div
            class="hidden h-8 w-px bg-slate-200 md:block"
            aria-hidden="true"
        />

        <UserMenu
            name="R. Martin"
            initials="RM"
            role="Gestionnaire flotte"
        />
    </header>
</template>
