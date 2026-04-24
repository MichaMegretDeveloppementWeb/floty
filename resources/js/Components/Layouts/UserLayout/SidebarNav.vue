<script setup lang="ts">
import FlotyMark from '@/Components/Brand/FlotyMark.vue';
import { usePage } from '@inertiajs/vue3';
import {
    Building2,
    CalendarCheck,
    CalendarDays,
    Car,
    LayoutDashboard,
    Receipt,
    type LucideIcon,
} from 'lucide-vue-next';
import { computed } from 'vue';

type NavItem = {
    label: string;
    icon: LucideIcon;
    href: string;
};

type NavSection = {
    title: string;
    items: NavItem[];
};

defineProps<{
    activePath?: string;
}>();

const open = defineModel<boolean>('open', { default: false });

const sections: NavSection[] = [
    {
        title: "Vue d'ensemble",
        items: [
            {
                label: 'Dashboard',
                icon: LayoutDashboard,
                href: '/app/dashboard',
            },
        ],
    },
    {
        title: 'Planning',
        items: [
            {
                label: 'Vue d\'ensemble',
                icon: CalendarDays,
                href: '/app/planning',
            },
            {
                label: 'Attributions',
                icon: CalendarCheck,
                href: '/app/assignments',
            },
        ],
    },
    {
        title: 'Données',
        items: [
            { label: 'Flotte', icon: Car, href: '/app/vehicles' },
            {
                label: 'Entreprises',
                icon: Building2,
                href: '/app/companies',
            },
        ],
    },
    {
        title: 'Fiscalité',
        items: [
            {
                label: 'Règles de calcul',
                icon: Receipt,
                href: '/app/fiscal-rules',
            },
        ],
    },
];

const page = usePage();
const currentPath = computed((): string => {
    const url = page.url;
    return typeof url === 'string' ? url : '';
});

const isActive = (href: string): boolean => currentPath.value.startsWith(href);

const closeDrawer = (): void => {
    open.value = false;
};

const labelClass =
    'whitespace-nowrap overflow-hidden opacity-100 max-w-[160px] md:opacity-0 md:max-w-0 md:group-hover/sidebar:opacity-100 md:group-hover/sidebar:max-w-[160px] wide:opacity-100 wide:max-w-[160px] transition-[opacity,max-width] duration-200 ease-out';
</script>

<template>
    <div
        v-if="open"
        class="fixed inset-0 z-20 bg-slate-900/40 md:hidden"
        aria-hidden="true"
        @click="closeDrawer"
    />

    <aside
        :class="[
            'group/sidebar fixed inset-y-0 left-0 z-30 flex flex-col overflow-y-auto overflow-x-hidden border-r border-slate-200 bg-white',
            'transition-[transform,width] duration-200 ease-out',
            'w-60',
            open ? 'translate-x-0' : '-translate-x-full',
            'md:translate-x-0 md:w-16 md:hover:w-60',
            'wide:w-60',
        ]"
    >
        <div
            class="flex items-center gap-3 border-b border-slate-100 py-5 pl-4 text-slate-900"
        >
            <FlotyMark :size="32" class="shrink-0" />
            <div :class="['flex flex-col leading-tight', labelClass]">
                <p class="text-base font-semibold text-slate-900">
                    Floty
                </p>
                <p class="text-xs text-slate-500">Flotte partagée</p>
            </div>
        </div>

        <nav class="flex-1 overflow-y-auto py-4">
            <div
                v-for="section in sections"
                :key="section.title"
                class="mb-5"
            >
                <p
                    class="eyebrow mb-1.5 overflow-hidden px-6 whitespace-nowrap text-slate-400 opacity-100 transition-opacity duration-200 ease-out md:opacity-0 md:group-hover/sidebar:opacity-100 wide:opacity-100"
                >
                    {{ section.title }}
                </p>
                <ul class="flex flex-col">
                    <li v-for="item in section.items" :key="item.label">
                        <a
                            :href="item.href"
                            :aria-current="isActive(item.href) ? 'page' : undefined"
                            :class="[
                                'relative flex items-center gap-3 px-6 py-2 text-base transition-colors duration-[120ms] ease-out',
                                isActive(item.href)
                                    ? 'bg-slate-50 font-medium text-slate-900'
                                    : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900',
                            ]"
                            @click="closeDrawer"
                        >
                            <span
                                v-if="isActive(item.href)"
                                aria-hidden="true"
                                class="absolute top-0 bottom-0 left-0 w-0.5 bg-slate-900"
                            />
                            <component
                                :is="item.icon"
                                :size="16"
                                :stroke-width="1.75"
                                class="shrink-0"
                                aria-hidden="true"
                            />
                            <span :class="labelClass">{{ item.label }}</span>
                        </a>
                    </li>
                </ul>
            </div>
        </nav>

        <div
            class="flex items-center gap-3 border-t border-slate-100 py-4 pl-4"
        >
            <div
                class="flex size-8 shrink-0 items-center justify-center rounded-full bg-slate-200 font-mono text-xs font-semibold text-slate-700"
                aria-hidden="true"
            >
                RM
            </div>
            <div :class="['flex flex-col leading-tight', labelClass]">
                <p class="text-base font-medium text-slate-900">
                    R. Martin
                </p>
                <p class="text-xs text-slate-500">Gestionnaire flotte</p>
            </div>
        </div>
    </aside>
</template>
