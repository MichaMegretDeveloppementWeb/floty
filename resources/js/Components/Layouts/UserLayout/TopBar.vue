<script setup lang="ts">
import { Menu } from 'lucide-vue-next';
import UserMenu from '@/Components/Layouts/UserLayout/UserMenu.vue';
import SearchInput from '@/Components/Ui/SearchInput/SearchInput.vue';
import { useTopBar } from '@/Composables/Layout/UserLayout/useTopBar';

// Chantier J (ADR-0020) : le sélecteur d'année global a été retiré.
// Chaque page consommatrice gère désormais son sélecteur local.

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
                placeholder="Recherche bientôt disponible"
                aria-label="Recherche globale (bientôt disponible)"
                disabled
            />
        </div>

        <UserMenu
            :name="fullName"
            :initials="initials"
            role="Gestionnaire flotte"
        />
    </header>
</template>
