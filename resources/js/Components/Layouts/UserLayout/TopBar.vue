<script setup lang="ts">
import { Menu, Search } from 'lucide-vue-next';
import UserMenu from '@/Components/Layouts/UserLayout/UserMenu.vue';
import Kbd from '@/Components/Ui/Kbd/Kbd.vue';
import { useCommandPalette } from '@/Composables/GlobalSearch/useCommandPalette';
import { useTopBar } from '@/Composables/Layout/UserLayout/useTopBar';

// Chantier J (ADR-0023) : le sélecteur d'année global a été retiré.
// Chaque page consommatrice gère désormais son sélecteur local.

const emit = defineEmits<{
    'toggle-sidebar': [];
}>();

const { fullName, initials } = useTopBar();
const palette = useCommandPalette();
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
            <!-- Trigger de la palette de recherche globale ⌘K (V1.1).
                Faux input stylé (button) · le focus réel est posé sur
                l'input interne de `<CommandPalette>` à l'ouverture.
                Garde la même bbox/aspect que `<SearchInput>` pour ne
                pas casser le rythme visuel du TopBar. -->
            <button
                type="button"
                aria-label="Ouvrir la recherche (⌘ K)"
                class="flex w-full items-center gap-3 rounded-lg border border-slate-200 bg-white py-2 pr-2 pl-3 text-left text-base leading-tight text-slate-400 transition-colors duration-[120ms] ease-out hover:border-slate-300 hover:text-slate-500 focus-visible:border-slate-400 focus-visible:shadow-[0_0_0_3px_var(--color-slate-100)] focus-visible:outline-none"
                @click="palette.open()"
            >
                <Search
                    :size="14"
                    :stroke-width="1.75"
                    class="shrink-0"
                    aria-hidden="true"
                />
                <span class="min-w-0 flex-1 truncate">Rechercher...</span>
                <span
                    class="hidden shrink-0 items-center gap-1 md:flex"
                    aria-hidden="true"
                >
                    <Kbd>⌘</Kbd>
                    <Kbd>K</Kbd>
                </span>
            </button>
        </div>

        <UserMenu
            :name="fullName"
            :initials="initials"
            role="Gestionnaire flotte"
        />
    </header>
</template>
