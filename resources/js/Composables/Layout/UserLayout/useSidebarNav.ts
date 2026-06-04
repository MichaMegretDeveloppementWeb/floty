import { usePage } from '@inertiajs/vue3';
import {
    BadgePercent,
    BellRing,
    Building2,
    CalendarDays,
    ClipboardCheck,
    FileCheck2,
    FileText,
    LayoutDashboard,
    Receipt,
    Settings,
    ShieldAlert,
    Users,
    Wallet,
} from 'lucide-vue-next';
import { computed } from 'vue';
import type { Component, ComputedRef, Ref } from 'vue';
import CarIcon from '@/Components/Icons/CarIcon.vue';
import { dashboard as dashboardRoute } from '@/routes/user';
import { index as companiesIndexRoute } from '@/routes/user/companies';
import { index as contractsIndexRoute } from '@/routes/user/contracts';
import { index as controlsIndexRoute } from '@/routes/user/controls';
import { index as declarationsIndexRoute } from '@/routes/user/declarations';
import { index as driversIndexRoute } from '@/routes/user/drivers';
import { index as fiscalRulesIndexRoute } from '@/routes/user/fiscal-rules';
import { index as invoicesIndexRoute } from '@/routes/user/invoices';
import { index as planningIndexRoute } from '@/routes/user/planning';
import { root as planningCompaniesRootRoute } from '@/routes/user/planning/companies';
import { index as rentalDiscountsIndexRoute } from '@/routes/user/rental-discounts';
import { edit as billingSettingsEditRoute } from '@/routes/user/settings/billing';
import { edit as controlRemindersEditRoute } from '@/routes/user/settings/control-reminders';
import { edit as fiscalRiskSettingsEditRoute } from '@/routes/user/settings/fiscal-risk';
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
 * Static sidebar nav sections plus state helpers: active item detection (URL prefix),
 * mobile drawer close, and the shared opacity/width transition utility class for
 * labels and section titles.
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
                    label: 'Vue par entreprise',
                    icon: Building2,
                    href: planningCompaniesRootRoute.url(),
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
                {
                    label: 'Conducteurs',
                    icon: Users,
                    href: driversIndexRoute.url(),
                },
                {
                    label: 'Locations',
                    icon: FileText,
                    href: contractsIndexRoute.url(),
                },
                {
                    label: 'Contrôles',
                    icon: ClipboardCheck,
                    href: controlsIndexRoute.url(),
                },
            ],
        },
        {
            title: 'Facturation',
            items: [
                {
                    label: 'Annexes de facture',
                    icon: Wallet,
                    href: invoicesIndexRoute.url(),
                },
                {
                    label: 'Réductions',
                    icon: BadgePercent,
                    href: rentalDiscountsIndexRoute.url(),
                },
            ],
        },
        {
            title: 'Fiscalité',
            items: [
                {
                    label: 'Déclarations',
                    icon: FileCheck2,
                    href: declarationsIndexRoute.url(),
                },
                {
                    label: 'Règles de calcul',
                    icon: Receipt,
                    href: fiscalRulesIndexRoute.url(),
                },
            ],
        },
        {
            title: 'Paramètres',
            items: [
                {
                    label: 'Émetteur',
                    icon: Settings,
                    href: billingSettingsEditRoute.url(),
                },
                {
                    label: 'Détection de risque',
                    icon: ShieldAlert,
                    href: fiscalRiskSettingsEditRoute.url(),
                },
                {
                    label: 'Rappels de contrôles',
                    icon: BellRing,
                    href: controlRemindersEditRoute.url(),
                },
            ],
        },
    ];

    const page = usePage();
    const currentPath = computed<string>(() => {
        const url = page.url;

        return typeof url === 'string' ? url : '';
    });

    /**
     * Active item = the one whose `href` is the longest prefix of the current path.
     * Avoids double-highlight when two items share a prefix
     * (e.g. `/app/planning` and `/app/planning/companies`: on `/app/planning/companies/1`
     * only the 2nd entry is marked active).
     */
    const activeHref = computed<string | null>(() => {
        const path = currentPath.value.split('?')[0] ?? '';
        let best: string | null = null;

        for (const section of sections) {
            for (const item of section.items) {
                const itemPath = item.href.split('?')[0] ?? '';

                const matches = path === itemPath
                    || path.startsWith(itemPath + '/');

                if (matches && (best === null || itemPath.length > best.length)) {
                    best = item.href;
                }
            }
        }

        return best;
    });

    const isActive = (href: string): boolean => activeHref.value === href;

    const closeDrawer = (): void => {
        open.value = false;
    };

    const labelClass =
        'whitespace-nowrap overflow-hidden opacity-100 max-w-[160px] md:opacity-0 md:max-w-0 md:group-hover/sidebar:opacity-100 md:group-hover/sidebar:max-w-[160px] wide:opacity-100 wide:max-w-[160px] transition-[opacity,max-width] duration-200 ease-out';

    return { sections, currentPath, isActive, closeDrawer, labelClass };
}
