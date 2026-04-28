/**
 * Mapping `CompanyColor` → classe Tailwind `bg-company-{color}` —
 * util partagé pour éviter la duplication entre les composants
 * d'affichage du domaine Entreprise (CompanyTag, timeline, breakdown).
 *
 * Les classes `bg-company-*` sont définies dans le design system
 * Tailwind (cf. design-system/tokens.css).
 */

type CompanyColor = App.Enums.Company.CompanyColor;

const colorBgClass: Record<CompanyColor, string> = {
    indigo: 'bg-company-indigo',
    emerald: 'bg-company-emerald',
    amber: 'bg-company-amber',
    rose: 'bg-company-rose',
    violet: 'bg-company-violet',
    teal: 'bg-company-teal',
    orange: 'bg-company-orange',
    cyan: 'bg-company-cyan',
};

export function companyColorBgClass(color: CompanyColor): string {
    return colorBgClass[color];
}
