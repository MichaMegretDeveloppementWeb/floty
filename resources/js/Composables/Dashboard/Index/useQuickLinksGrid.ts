import {
    Building2,
    CalendarDays,
    FileText,
    Receipt,
} from 'lucide-vue-next';
import type { Component } from 'vue';
import CarIcon from '@/Components/Icons/CarIcon.vue';
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
};

/**
 * Liste statique des accès rapides du dashboard. Cartes uniformes en
 * grille 3 colonnes (5 items → ligne 1 pleine, ligne 2 partielle).
 */
export function useQuickLinksGrid(): { quickLinks: QuickLink[] } {
    const quickLinks: QuickLink[] = [
        {
            label: "Vue d'ensemble",
            description:
                "Heatmap annuelle des 52 semaines — la vue maîtresse pour attribuer et visualiser l'impact fiscal en temps réel.",
            href: planningIndexRoute.url(),
            icon: CalendarDays,
        },
        {
            label: 'Contrats',
            description:
                'Plages d\'attribution véhicule × entreprise — créer, modifier, supprimer (LCD per-contract).',
            href: contractsIndexRoute.url(),
            icon: FileText,
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
