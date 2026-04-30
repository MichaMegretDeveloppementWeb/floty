import { usePage } from '@inertiajs/vue3';
import {
    Building2,
    CalendarDays,
    FileText,
    LayoutDashboard,
    Receipt,
} from 'lucide-vue-next';
import { computed } from 'vue';
import type { Component, ComputedRef, Ref } from 'vue';
import CarIcon from '@/Components/Icons/CarIcon.vue';
import { dashboard as dashboardRoute } from '@/routes/user';
import { index as companiesIndexRoute } from '@/routes/user/companies';
import { index as contractsIndexRoute } from '@/routes/user/contracts';
import { index as fiscalRulesIndexRoute } from '@/routes/user/fiscal-rules';
import { index as planningIndexRoute } from '@/routes/user/planning';
import { index as vehiclesIndexRoute } from '@/routes/user/vehicles';

export type NavItem = {
    label: string;
    icon: Component;
    href: string;
};

export type NavSection = {
    title: string;
    items: NavItem[];
};

/**
 * Sections statiques de la navigation latérale + helpers d'état :
 * détection de l'item actif (préfixe URL), fermeture du drawer
 * mobile, et la classe utilitaire de transition opacité/largeur
 * partagée par les labels et les titres de section.
 */
export function useSidebarNav(open: Ref<boolean>): {
    sections: NavSection[];
    currentPath: ComputedRef<string>;
    isActive: (href: string) => boolean;
    closeDrawer: () => void;
    labelClass: string;
} {
    const sections: NavSection[] = [
        {
            title: "Vue d'ensemble",
            items: [
                {
                    label: 'Dashboard',
                    icon: LayoutDashboard,
                    href: dashboardRoute.url(),
                },
            ],
        },
        {
            title: 'Planning',
            items: [
                {
                    label: "Vue d'ensemble",
                    icon: CalendarDays,
                    href: planningIndexRoute.url(),
                },
                {
                    label: 'Contrats',
                    icon: FileText,
                    href: contractsIndexRoute.url(),
                },
            ],
        },
        {
            title: 'Données',
            items: [
                { label: 'Flotte', icon: CarIcon, href: vehiclesIndexRoute.url() },
                {
                    label: 'Entreprises',
                    icon: Building2,
                    href: companiesIndexRoute.url(),
                },
            ],
        },
        {
            title: 'Fiscalité',
            items: [
                {
                    label: 'Règles de calcul',
                    icon: Receipt,
                    href: fiscalRulesIndexRoute.url(),
                },
            ],
        },
    ];

    const page = usePage();
    const currentPath = computed<string>(() => {
        const url = page.url;

        return typeof url === 'string' ? url : '';
    });

    const isActive = (href: string): boolean =>
        currentPath.value.startsWith(href);

    const closeDrawer = (): void => {
        open.value = false;
    };

    const labelClass =
        'whitespace-nowrap overflow-hidden opacity-100 max-w-[160px] md:opacity-0 md:max-w-0 md:group-hover/sidebar:opacity-100 md:group-hover/sidebar:max-w-[160px] wide:opacity-100 wide:max-w-[160px] transition-[opacity,max-width] duration-200 ease-out';

    return { sections, currentPath, isActive, closeDrawer, labelClass };
}
