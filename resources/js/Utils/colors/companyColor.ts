/**
 * Maps `CompanyColor` to the Tailwind `bg-company-{color}` utility.
 * The classes are declared in the design-system Tailwind config.
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
