/**
 * Résolution des URLs canoniques vers les textes officiels français
 * (CIBS sur Légifrance, doctrine BOFiP-Impôts) à partir des références
 * structurées portées par chaque règle fiscale.
 *
 * Stratégie d'URL :
 *  - **CIBS** : chaque article CIBS a un identifiant LEGIARTI propre à
 *    une **version** de l'article (Légifrance distingue les versions
 *    modifiées par les lois successives). On stocke ici un mapping
 *    article → LEGIARTI **applicable à l'année fiscale courante**.
 *    L'URL `https://www.legifrance.gouv.fr/codes/article_lc/{LEGIARTI}`
 *    ouvre directement la bonne version sans paramètre de date.
 *
 *    Les LEGIARTI ont été collectés depuis Légifrance pour la version
 *    en vigueur au 01/01/2024. Pour les articles non modifiés depuis
 *    leur création (2022), le LEGIARTI v0 reste applicable.
 *
 *  - **BOFiP** : `bofip.impots.gouv.fr/bofip/{IDENTIFIANT}` redirige
 *    vers la doctrine cible. Le paragraphe `§ N` n'est pas accessible
 *    en ancre URL — on le garde en libellé pour aider la lecture.
 *  - **CGI** : pour le Code général des impôts, on pointe vers la
 *    table des matières (CGI a un autre LEGITEXT et notre seeder ne
 *    le référence pas pour 2024).
 *  - **NOTICE DGFiP** : recherche sur impots.gouv.fr.
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
    /** Description longue pour `title="…"` (accessibilité hover). */
    title: string;
};

const LEGIFRANCE_BASE = 'https://www.legifrance.gouv.fr';
const BOFIP_BASE = 'https://bofip.impots.gouv.fr/bofip';
const IMPOTS_SEARCH = 'https://www.impots.gouv.fr/recherche/all';

/**
 * Mapping article CIBS → LEGIARTI applicable au 01/01/2024.
 *
 * Source : Légifrance, sections du Code des impositions sur les biens
 * et services (LEGITEXT000044595989) consultées le 26/04/2026 pour la
 * date d'entrée en vigueur 01/01/2024 :
 *   - Paragraphe 3 « Tarifs CO₂ » LEGISCTA000044599231 → L. 421-119 à L. 421-132
 *   - Paragraphe 4 « Tarifs polluants » LEGISCTA000044599273 → L. 421-133 à L. 421-144
 *
 * Les barèmes WLTP/NEDC/PA (L. 421-120/121/122) ont été modifiés par
 * la LF 2024 (LOI n° 2023-1322 art. 97), d'où des LEGIARTI distincts
 * de la version originale 2022. Les exonérations n'ont pas été
 * touchées en 2024, leur LEGIARTI v0 reste applicable.
 */
const CIBS_ARTICLE_LEGIARTI_2024: Record<string, string> = {
    'L. 421-2': 'LEGIARTI000048844510',
    'L. 421-119': 'LEGIARTI000048802414',
    'L. 421-120': 'LEGIARTI000048844602',
    'L. 421-121': 'LEGIARTI000048844592',
    'L. 421-122': 'LEGIARTI000048844579',
    'L. 421-123': 'LEGIARTI000044602975',
    'L. 421-124': 'LEGIARTI000044602971',
    'L. 421-125': 'LEGIARTI000044602969',
    'L. 421-126': 'LEGIARTI000044602965',
    'L. 421-127': 'LEGIARTI000044602963',
    'L. 421-128': 'LEGIARTI000044602959',
    'L. 421-129': 'LEGIARTI000044602957',
    'L. 421-130': 'LEGIARTI000044602953',
    'L. 421-131': 'LEGIARTI000044602951',
    'L. 421-132': 'LEGIARTI000044602949',
    'L. 421-133': 'LEGIARTI000048844554',
    'L. 421-134': 'LEGIARTI000048844542',
    'L. 421-135': 'LEGIARTI000048844528',
    'L. 421-136': 'LEGIARTI000044602935',
    'L. 421-138': 'LEGIARTI000044602927',
    'L. 421-139': 'LEGIARTI000044602925',
    'L. 421-140': 'LEGIARTI000044602921',
    'L. 421-141': 'LEGIARTI000044602919',
    'L. 421-142': 'LEGIARTI000044602915',
    'L. 421-143': 'LEGIARTI000044602913',
    'L. 421-144': 'LEGIARTI000044602911',
};

const CIBS_LEGITEXT = 'LEGITEXT000044595989';
const CGI_LEGITEXT = 'LEGITEXT000006069577';

function cibsUrl(article: string): string {
    const normalized = article.replace(/\s+/g, ' ').trim();
    const legiarti = CIBS_ARTICLE_LEGIARTI_2024[normalized];
    if (legiarti) {
        return `${LEGIFRANCE_BASE}/codes/article_lc/${legiarti}`;
    }
    // Fallback : page d'accueil du CIBS (rare — uniquement si un
    // nouvel article apparaît dans le seeder sans avoir été ajouté
    // dans le mapping ci-dessus).
    return `${LEGIFRANCE_BASE}/codes/texte_lc/${CIBS_LEGITEXT}`;
}

function cgiUrl(): string {
    return `${LEGIFRANCE_BASE}/codes/texte_lc/${CGI_LEGITEXT}`;
}

export function resolveLegalLink(
    ref: LegalReference,
): ResolvedLegalLink | null {
    if (ref.type === 'CIBS' && ref.article) {
        return {
            label: `CIBS ${ref.article}`,
            url: cibsUrl(ref.article),
            title: `Article ${ref.article} du Code des impositions sur les biens et services — version applicable au 01/01/2024 (Légifrance)`,
        };
    }

    if (ref.type === 'CGI' && ref.article) {
        return {
            label: `CGI ${ref.article}`,
            url: cgiUrl(),
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

    const fallbackLabel = ref.article ?? ref.reference ?? ref.type ?? '';
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
