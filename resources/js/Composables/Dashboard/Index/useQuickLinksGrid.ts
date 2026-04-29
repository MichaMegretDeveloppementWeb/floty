import {
    Building2,
    CalendarCheck,
    CalendarDays,
    FileText,
    Receipt,
} from 'lucide-vue-next';
import type { Component } from 'vue';
import CarIcon from '@/Components/Icons/CarIcon.vue';
import { index as assignmentsIndexRoute } from '@/routes/user/assignments';
import { index as companiesIndexRoute } from '@/routes/user/companies';
import { index as contractsIndexRoute } from '@/routes/user/contracts';
import { index as fiscalRulesIndexRoute } from '@/routes/user/fiscal-rules';
import { index as planningIndexRoute } from '@/routes/user/planning';
import { index as vehiclesIndexRoute } from '@/routes/user/vehicles';

export type QuickLink = {
    label: string;
    description: string;
    href: string;
    icon: Component;
    featured?: boolean;
};

/**
 * Liste statique des accès rapides du dashboard. La carte « Vue
 * d'ensemble » est marquée `featured` (occupe 2 colonnes en grille).
 */
export function useQuickLinksGrid(): { quickLinks: QuickLink[] } {
    const quickLinks: QuickLink[] = [
        {
            label: "Vue d'ensemble",
            description:
                "Heatmap annuelle des 52 semaines — la vue maîtresse pour attribuer et visualiser l'impact fiscal en temps réel.",
            href: planningIndexRoute.url(),
            icon: CalendarDays,
            featured: true,
        },
        {
            label: 'Contrats',
            description:
                'Plages d\'attribution véhicule × entreprise — créer, modifier, supprimer (LCD per-contract).',
            href: contractsIndexRoute.url(),
            icon: FileText,
        },
        {
            label: 'Attribution rapide',
            description:
                'Sélectionner un véhicule, une entreprise et plusieurs dates en une passe.',
            href: assignmentsIndexRoute.url(),
            icon: CalendarCheck,
        },
        {
            label: 'Flotte',
            description:
                'Véhicules enregistrés, caractéristiques fiscales et taxes annuelles.',
            href: vehiclesIndexRoute.url(),
            icon: CarIcon,
        },
        {
            label: 'Entreprises',
            description:
                'Clients utilisateurs de la flotte, jours cumulés et taxes par entreprise.',
            href: companiesIndexRoute.url(),
            icon: Building2,
        },
        {
            label: 'Règles de calcul',
            description:
                'Comprendre comment Floty calcule les taxes CO₂ et polluants — barèmes, exonérations, cadre.',
            href: fiscalRulesIndexRoute.url(),
            icon: Receipt,
        },
    ];

    return { quickLinks };
}
