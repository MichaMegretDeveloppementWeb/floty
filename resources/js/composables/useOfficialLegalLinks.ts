/**
 * Résolution des URLs canoniques vers les textes officiels français
 * (CIBS sur Légifrance, doctrine BOFiP-Impôts) à partir des références
 * structurées portées par chaque règle fiscale.
 *
 * Le format d'entrée correspond au champ `legal_basis` peuplé par
 * `FiscalRulesSeeder` côté backend.
 *
 * Choix d'URL :
 *  - **CIBS** : la recherche Légifrance par article est la seule URL
 *    stable sans avoir à connaître l'identifiant LEGIARTI propre à
 *    chaque article. Pattern :
 *    `https://www.legifrance.gouv.fr/search/code?...&query=L.+421-XXX`
 *    On force `tab_selection=code` pour cibler le code uniquement.
 *  - **BOFiP** : l'identifiant BOI sert directement de slug d'URL,
 *    `https://bofip.impots.gouv.fr/bofip/{IDENTIFIANT}` redirige vers
 *    la doctrine cible (le paragraphe `§ N` n'est pas accessible en
 *    ancre — on le laisse en libellé).
 *  - **Notice DGFiP** (formulaires 2857-FC-NOT-SD / 2858-FC-NOT-SD) :
 *    URL de recherche sur impots.gouv.fr.
 */

export type LegalReference = {
    type: 'CIBS' | 'BOFIP' | 'CGI' | 'NOTICE' | string;
    article?: string;
    reference?: string;
    paragraph?: string;
};

export type ResolvedLegalLink = {
    label: string;
    url: string;
    /** Description longue pour title="…" (accessibilité hover). */
    title: string;
};

const LEGIFRANCE_SEARCH = 'https://www.legifrance.gouv.fr/search/code';
const BOFIP_BASE = 'https://bofip.impots.gouv.fr/bofip';
const IMPOTS_SEARCH = 'https://www.impots.gouv.fr/recherche/all';

function legifranceSearchUrl(query: string): string {
    const params = new URLSearchParams({
        tab_selection: 'code',
        searchField: 'ALL',
        query,
        typeRecherche: 'date',
        isAdvancedResult: 'true',
    });
    return `${LEGIFRANCE_SEARCH}?${params.toString()}`;
}

export function resolveLegalLink(ref: LegalReference): ResolvedLegalLink | null {
    if (ref.type === 'CIBS' && ref.article) {
        return {
            label: `CIBS ${ref.article}`,
            url: legifranceSearchUrl(`CIBS ${ref.article}`),
            title: `Article ${ref.article} du Code des impositions sur les biens et services (Légifrance)`,
        };
    }

    if (ref.type === 'CGI' && ref.article) {
        return {
            label: `CGI ${ref.article}`,
            url: legifranceSearchUrl(`Code général des impôts ${ref.article}`),
            title: `Article ${ref.article} du Code général des impôts (Légifrance)`,
        };
    }

    if (ref.type === 'BOFIP' && ref.reference) {
        const para = ref.paragraph ? ` ${ref.paragraph}` : '';
        return {
            label: `${ref.reference}${para}`,
            url: `${BOFIP_BASE}/${encodeURIComponent(ref.reference)}`,
            title: `Doctrine BOFiP-Impôts ${ref.reference}${para}`,
        };
    }

    if (ref.type === 'NOTICE' && ref.reference) {
        const params = new URLSearchParams({ q: ref.reference });
        return {
            label: `Notice ${ref.reference}`,
            url: `${IMPOTS_SEARCH}?${params.toString()}`,
            title: `Notice DGFiP ${ref.reference} (impots.gouv.fr)`,
        };
    }

    // Repli : on retourne le libellé sans URL.
    const fallbackLabel =
        ref.article ?? ref.reference ?? ref.type ?? '';
    if (!fallbackLabel) return null;
    return {
        label: fallbackLabel,
        url: '',
        title: fallbackLabel,
    };
}

export function useOfficialLegalLinks(): {
    resolveLegalLink: typeof resolveLegalLink;
    resolveAll: (refs: LegalReference[]) => ResolvedLegalLink[];
} {
    const resolveAll = (refs: LegalReference[]): ResolvedLegalLink[] =>
        refs
            .map((r) => resolveLegalLink(r))
            .filter((l): l is ResolvedLegalLink => l !== null);

    return { resolveLegalLink, resolveAll };
}
