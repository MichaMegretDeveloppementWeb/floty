/**
 * Résolution des URLs canoniques vers les textes officiels français
 * (CIBS sur Légifrance, doctrine BOFiP-Impôts) à partir des références
 * structurées portées par chaque règle fiscale.
 *
 * **Stratégie d'URL CIBS** : un LEGIARTI Légifrance représente une
 * **version** d'un article (chaque modification législative en crée une
 * nouvelle), mais Légifrance accepte aussi une URL datée qui résout
 * automatiquement la version applicable à cette date :
 *
 *     /codes/article_lc/{LEGIARTI}/{YYYY-MM-DD}
 *
 * On utilise systématiquement le format daté avec le 1er juin de
 * l'année fiscale courante (milieu d'année — sécurise les transitions
 * de barème au 01/01). Sans date, Légifrance ouvre la dernière version
 * (peut être 2026+ alors qu'on parle de la fiscalité 2024).
 *
 * **BOFiP** : `bofip.impots.gouv.fr/bofip/{IDENTIFIANT}` → la doctrine
 * porte sa propre date dans son identifiant (ex: …-20240710), pas de
 * versionnage à gérer côté URL.
 *
 * **CGI** : table des matières (le seeder ne référence pas le CGI en
 * 2024).
 *
 * **NOTICE DGFiP** : recherche sur impots.gouv.fr.
 */

import { useFiscalYear } from '@/composables/useFiscalYear';
import { computed, type ComputedRef } from 'vue';

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
 * Mapping article CIBS → LEGIARTI.
 *
 * Chaque LEGIARTI ci-dessous est un identifiant **stable d'article**
 * (pas de version). Légifrance résout la version applicable à la date
 * passée en URL — on combine ces LEGIARTI avec une date dérivée de
 * l'année fiscale courante (cf. `articleUrlForYear`).
 *
 * Source : Légifrance, sections du Code des impositions sur les biens
 * et services (LEGITEXT000044595989) — paragraphes 3 et 4 (taxes CO₂
 * et polluants), articles L. 421-2 et L. 421-93 à L. 421-167.
 */
const CIBS_ARTICLE_LEGIARTI: Record<string, string> = {
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

/**
 * Date pivot pour résoudre la version d'un article : 1er juin de
 * l'année fiscale courante. On évite le 01/01 (jour exact des
 * transitions législatives) pour rester dans une fenêtre stable.
 */
function pivotDateFor(year: number): string {
    return `${year}-06-01`;
}

function cibsUrlFor(article: string, year: number): string {
    const normalized = article.replace(/\s+/g, ' ').trim();
    const legiarti = CIBS_ARTICLE_LEGIARTI[normalized];
    if (legiarti) {
        return `${LEGIFRANCE_BASE}/codes/article_lc/${legiarti}/${pivotDateFor(year)}`;
    }
    return `${LEGIFRANCE_BASE}/codes/texte_lc/${CIBS_LEGITEXT}`;
}

function cgiUrl(): string {
    return `${LEGIFRANCE_BASE}/codes/texte_lc/${CGI_LEGITEXT}`;
}

function resolveLegalLinkFor(
    ref: LegalReference,
    year: number,
): ResolvedLegalLink | null {
    if (ref.type === 'CIBS' && ref.article) {
        return {
            label: `CIBS ${ref.article}`,
            url: cibsUrlFor(ref.article, year),
            title: `Article ${ref.article} du Code des impositions sur les biens et services — version applicable au 01/01/${year} (Légifrance)`,
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

export type UseOfficialLegalLinksReturn = {
    /** Année fiscale courante effectivement utilisée pour résoudre les versions. */
    fiscalYear: ComputedRef<number>;
    /** Résout une référence vers son lien officiel (utilise l'année courante). */
    resolveLegalLink: (ref: LegalReference) => ResolvedLegalLink | null;
    /** Résout un tableau de références. */
    resolveAll: (refs: LegalReference[]) => ResolvedLegalLink[];
};

export function useOfficialLegalLinks(): UseOfficialLegalLinksReturn {
    const { currentYear } = useFiscalYear();

    const resolveLegalLink = (
        ref: LegalReference,
    ): ResolvedLegalLink | null => resolveLegalLinkFor(ref, currentYear.value);

    const resolveAll = (refs: LegalReference[]): ResolvedLegalLink[] =>
        refs
            .map((r) => resolveLegalLink(r))
            .filter((l): l is ResolvedLegalLink => l !== null);

    return {
        fiscalYear: computed(() => currentYear.value),
        resolveLegalLink,
        resolveAll,
    };
}
