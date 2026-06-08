import {
    Building2,
    CalendarDays,
    Car,
    FileText,
    Receipt,
    Users,
} from 'lucide-vue-next';
import type { Component } from 'vue';
import { index as companiesIndexRoute } from '@/routes/user/companies';
import { index as contractsIndexRoute } from '@/routes/user/contracts';
import { index as driversIndexRoute } from '@/routes/user/drivers';
import { index as fiscalRulesIndexRoute } from '@/routes/user/fiscal-rules';
import { index as planningIndexRoute } from '@/routes/user/planning';
import { index as vehiclesIndexRoute } from '@/routes/user/vehicles';

export type QuickLink = {
    label: string;
    description: string;
    href: string;
    icon: Component;
};

/**
 * Static list of dashboard quick links. Uniform cards in a 3-column grid.
 */
export function useQuickLinksGrid(): { quickLinks: QuickLink[] } {
    const quickLinks: QuickLink[] = [
        {
            label: "Vue d'ensemble",
            description:
                "Heatmap annuelle des 52 semaines, la vue maîtresse pour attribuer et visualiser l'impact fiscal en temps réel.",
            href: planningIndexRoute.url(),
            icon: CalendarDays,
        },
        {
            label: 'Locations',
            description:
                'Plages d\'attribution véhicule × entreprise. Créer, modifier, supprimer (LCD per-contract).',
            href: contractsIndexRoute.url(),
            icon: FileText,
        },
        {
            label: 'Flotte',
            description:
                'Véhicules enregistrés, caractéristiques fiscales et taxes annuelles.',
            href: vehiclesIndexRoute.url(),
            icon: Car,
        },
        {
            label: 'Entreprises',
            description:
                'Clients utilisateurs de la flotte, jours cumulés et taxes par entreprise.',
            href: companiesIndexRoute.url(),
            icon: Building2,
        },
        {
            label: 'Conducteurs',
            description:
                "Conducteurs rattachés à une ou plusieurs entreprises avec dates d'entrée/sortie.",
            href: driversIndexRoute.url(),
            icon: Users,
        },
        {
            label: 'Règles de calcul',
            description:
                'Comprendre comment Floty calcule les taxes CO₂ et polluants : barèmes, exonérations, cadre.',
            href: fiscalRulesIndexRoute.url(),
            icon: Receipt,
        },
    ];

    return { quickLinks };
}
