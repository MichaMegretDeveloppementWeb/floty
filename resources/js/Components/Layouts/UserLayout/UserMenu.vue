<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { onClickOutside, onKeyStroke } from '@vueuse/core';
import { ChevronDown, LogOut } from 'lucide-vue-next';
import { ref, useTemplateRef } from 'vue';
import { logout as logoutRoute } from '@/routes';

defineProps<{
    name: string;
    initials: string;
    role?: string;
}>();

const open = ref<boolean>(false);
const rootRef = useTemplateRef<HTMLDivElement>('rootRef');

const close = (): void => {
    open.value = false;
};

const toggle = (): void => {
    open.value = !open.value;
};

const logout = (): void => {
    close();
    router.post(logoutRoute.url());
};

onClickOutside(rootRef, close);

onKeyStroke('Escape', () => {
    if (open.value) {
        close();
    }
});
</script>

<template>
    <div ref="rootRef" class="relative">
        <button
            type="button"
            :aria-expanded="open"
            aria-haspopup="menu"
            :aria-label="`Menu de ${name}`"
            class="inline-flex items-center gap-2 rounded-lg p-1.5 text-slate-700 transition-colors duration-[120ms] ease-out hover:bg-slate-50 focus-visible:ring-2 focus-visible:ring-slate-100 focus-visible:outline-none sm:px-2"
            @click="toggle"
        >
            <span
                class="flex size-7 items-center justify-center rounded-full bg-slate-200 font-mono text-xs font-semibold text-slate-700"
                aria-hidden="true"
            >
                {{ initials }}
            </span>
            <span class="hidden text-base font-medium sm:inline">
                {{ name }}
            </span>
            <ChevronDown
                :size="14"
                :stroke-width="1.75"
                class="hidden text-slate-400 sm:block"
                aria-hidden="true"
            />
        </button>
        <div
            v-if="open"
            role="menu"
            class="absolute top-full right-0 z-20 mt-1.5 w-56 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-lg"
        >
            <div class="border-b border-slate-100 px-4 py-3">
                <p class="text-base font-medium text-slate-900">
                    {{ name }}
                </p>
                <p v-if="role" class="text-xs text-slate-500">
                    {{ role }}
                </p>
            </div>
            <ul class="py-1.5">
                <li>
                    <button
                        type="button"
                        role="menuitem"
                        class="flex w-full items-center gap-2.5 px-4 py-2 text-left text-base text-slate-700 hover:bg-slate-50 hover:text-slate-900"
                        @click="logout"
                    >
                        <LogOut
                            :size="14"
                            :stroke-width="1.75"
                            class="text-slate-400"
                            aria-hidden="true"
                        />
                        Déconnexion
                    </button>
                </li>
            </ul>
        </div>
    </div>
</template>
