<script setup lang="ts">
import {
    BarChart3,
    Building2,
    CalendarCheck,
    CalendarRange,
    Car,
    LayoutDashboard,
    Receipt,
    Table2,
    Users,
    type LucideIcon,
} from 'lucide-vue-next';

type NavItem = {
    label: string;
    icon: LucideIcon;
    href: string;
    active?: boolean;
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
                href: '#dashboard',
                active: true,
            },
        ],
    },
    {
        title: 'Planning',
        items: [
            {
                label: 'Vue globale',
                icon: CalendarRange,
                href: '#planning-global',
            },
            {
                label: 'Par entreprise',
                icon: Building2,
                href: '#planning-companies',
            },
            {
                label: 'Par véhicule',
                icon: Car,
                href: '#planning-vehicles',
            },
            {
                label: 'Saisie hebdo',
                icon: CalendarCheck,
                href: '#planning-weekly',
            },
        ],
    },
    {
        title: 'Données',
        items: [
            { label: 'Flotte', icon: Table2, href: '#fleet' },
            {
                label: 'Entreprises & conducteurs',
                icon: Users,
                href: '#companies',
            },
        ],
    },
    {
        title: 'Fiscalité',
        items: [
            {
                label: 'Déclarations',
                icon: Receipt,
                href: '#declarations',
            },
            { label: 'Analytics', icon: BarChart3, href: '#analytics' },
        ],
    },
];

const closeDrawer = (): void => {
    open.value = false;
};
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
            'group/sidebar fixed inset-y-0 left-0 z-30 flex flex-col overflow-hidden border-r border-slate-200 bg-white',
            'transition-[transform,width] duration-200 ease-out',
            'w-60',
            open ? 'translate-x-0' : '-translate-x-full',
            'md:translate-x-0 md:w-16 md:hover:w-60',
            'wide:w-60',
        ]"
    >
        <div
            class="flex items-center gap-3 border-b border-slate-100 py-5 pl-[18px]"
        >
            <div
                class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-slate-900 font-semibold text-white"
                aria-hidden="true"
            >
                F
            </div>
            <div class="flex flex-col leading-tight whitespace-nowrap">
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
                    class="eyebrow mb-1.5 px-[18px] whitespace-nowrap text-slate-400 transition-opacity duration-150 ease-out md:opacity-0 md:group-hover/sidebar:opacity-100 wide:opacity-100"
                >
                    {{ section.title }}
                </p>
                <ul class="flex flex-col">
                    <li v-for="item in section.items" :key="item.label">
                        <a
                            :href="item.href"
                            :aria-current="item.active ? 'page' : undefined"
                            :class="[
                                'flex items-center gap-3 py-2 pl-[22px] text-base transition-colors duration-[120ms] ease-out',
                                item.active
                                    ? 'border-l-2 border-slate-900 bg-slate-50 font-medium text-slate-900'
                                    : 'border-l-2 border-transparent text-slate-600 hover:bg-slate-50 hover:text-slate-900',
                            ]"
                            @click="closeDrawer"
                        >
                            <component
                                :is="item.icon"
                                :size="16"
                                :stroke-width="1.75"
                                class="shrink-0"
                                aria-hidden="true"
                            />
                            <span class="whitespace-nowrap">
                                {{ item.label }}
                            </span>
                        </a>
                    </li>
                </ul>
            </div>
        </nav>

        <div
            class="flex items-center gap-3 border-t border-slate-100 py-4 pl-[18px]"
        >
            <div
                class="flex size-8 shrink-0 items-center justify-center rounded-full bg-slate-200 font-mono text-xs font-semibold text-slate-700"
                aria-hidden="true"
            >
                RM
            </div>
            <div class="flex flex-col leading-tight whitespace-nowrap">
                <p class="text-base font-medium text-slate-900">
                    R. Martin
                </p>
                <p class="text-xs text-slate-500">Gestionnaire flotte</p>
            </div>
        </div>
    </aside>
</template>
