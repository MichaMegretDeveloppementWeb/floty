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
 * l'année fiscale courante (milieu d'année - sécurise les transitions
 * de barème au 01/01). Sans date, Légifrance ouvre la dernière version
 * (peut être 2026+ alors qu'on parle de la fiscalité 2024).
 *
 * **BOFiP** : pointage direct vers la version datée du permalink interne
 * de la doctrine, pour ouvrir l'utilisateur exactement sur le document
 * applicable à l'année fiscale en cours (et non sur la dernière version
 * en vigueur, parfois postérieure à l'exercice considéré).
 *
 * Format : `https://bofip.impots.gouv.fr/bofip/{documentId}/identifiant=
 * {IDENTIFIANT}-{YYYYMMDD}`
 *
 * `documentId` (ex. `13932-PGP.html`) est l'identifiant interne stable
 * de la doctrine (un par doctrine). `{YYYYMMDD}` est la date de mise en
 * vigueur de la version applicable à l'année fiscale donnée. Ces couples
 * sont mappés explicitement dans `BOFIP_DOCUMENTS` ci-dessous (audités
 * via la frise chronologique de chaque doctrine).
 *
 * Si la référence demandée n'est pas dans le map, fallback sur la page
 * de recherche BOFiP filtrée sur l'identifiant.
 *
 * **CGI** : table des matières (le seeder ne référence pas le CGI en
 * 2024).
 *
 * **NOTICE DGFiP** : recherche sur impots.gouv.fr.
 */

import { computed, toValue } from 'vue';
import type { ComputedRef, MaybeRefOrGetter } from 'vue';

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
const BOFIP_SEARCH = 'https://bofip.impots.gouv.fr/search/result';
const IMPOTS_SEARCH = 'https://www.impots.gouv.fr/recherche/all';

/**
 * Mapping doctrine BOFiP → identifiant interne du document + versions
 * datées par année fiscale + ancres HTML par paragraphe.
 *
 * **Pourquoi des ancres explicites** : la BOFiP attribue à chaque
 * paragraphe un id arbitraire (slug textuel + suffixe numérique) qui
 * n'a aucun rapport avec le numéro de paragraphe et qui change à chaque
 * révision de la doctrine. Pour permettre un scroll direct à § 50, on
 * mappe explicitement le numéro de paragraphe vers l'id HTML de la
 * version concernée. Ces ancres ont été extraites par inspection DOM
 * Chrome live de la page (`document.querySelectorAll('p[id]')`).
 *
 * `BOI-AIS-MOB-10-30-10` (Dispositions communes — Taxes sur l'affectation
 * des véhicules à des fins économiques) :
 * - Version 2024-07-10 → 2025-05-28 : `20240710`. Vérifié contenir
 *   § 50/60/190 sur les indispos réductrices avec leurs ancres exactes.
 */
type BofipVersion = {
    date: string;
    anchors: Record<string, string>;
};

const BOFIP_DOCUMENTS: Record<string, { documentId: string; versionsByYear: Record<number, BofipVersion> }> = {
    'BOI-AIS-MOB-10-30-10': {
        documentId: '13932-PGP.html',
        versionsByYear: {
            2024: {
                date: '20240710',
                anchors: {
                    '50': 'il_en_ressort_qu_9929',
                    '60': '60_1148',
                    '170': '170_4136',
                    '190': '180_4641',
                },
            },
        },
    },
};

/**
 * Mapping article CIBS → LEGIARTI.
 *
 * Chaque LEGIARTI ci-dessous est un identifiant **stable d'article**
 * (pas de version). Légifrance résout la version applicable à la date
 * passée en URL - on combine ces LEGIARTI avec une date dérivée de
 * l'année fiscale courante (cf. `articleUrlForYear`).
 *
 * Tous les LEGIARTI ont été audités sémantiquement (numéro + contenu
 * de l'article) par navigation Chrome live le 2026-05-06 sur la version
 * applicable au 2024-06-01.
 *
 * Source : Légifrance, Code des impositions sur les biens et services
 * (LEGITEXT000044595989). Articles couverts :
 * - L. 131-1 (règle d'arrondi half-up, livre I) ;
 * - L. 421-2 (définition véhicule de tourisme M1/N1) ;
 * - L. 421-6 (définition de la méthode WLTP) ;
 * - L. 421-94 à L. 421-99 (assujettissement, fait générateur, indispos
 *   réductrices L. 421-96, redevable en cas de location L. 421-99) ;
 * - L. 421-105, L. 421-107, L. 421-110, L. 421-111 (proportion annuelle,
 *   coefficient pondérateur, minoration 15 000 €) ;
 * - L. 421-119 et L. 421-119-1 (règle générale tarifs CO₂ et bascule
 *   WLTP/NEDC/PA) ;
 * - L. 421-120 à L. 421-144 (tarifs et exonérations CO₂ + polluants) ;
 * - L. 421-164 (état récapitulatif annuel).
 */
const CIBS_ARTICLE_LEGIARTI: Record<string, string> = {
    'L. 131-1': 'LEGIARTI000044604185',
    'L. 421-2': 'LEGIARTI000048844510',
    'L. 421-6': 'LEGIARTI000044603307',
    'L. 421-94': 'LEGIARTI000051214931',
    'L. 421-95': 'LEGIARTI000051214924',
    'L. 421-96': 'LEGIARTI000044603053',
    'L. 421-99': 'LEGIARTI000044603043',
    'L. 421-105': 'LEGIARTI000048637679',
    'L. 421-107': 'LEGIARTI000044603019',
    'L. 421-110': 'LEGIARTI000046196651',
    'L. 421-111': 'LEGIARTI000044603007',
    'L. 421-119': 'LEGIARTI000044602987',
    'L. 421-119-1': 'LEGIARTI000048802414',
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
    'L. 421-164': 'LEGIARTI000051214908',
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

/**
 * URL canonique pour une doctrine BOFiP, pointant vers la version
 * applicable à l'année fiscale courante avec un anchor sur le paragraphe
 * cité (s'il est mappé). Sinon fallback sur la page de recherche.
 */
function bofipUrlFor(reference: string, year: number, paragraph?: string): string {
    const doc = BOFIP_DOCUMENTS[reference];

    if (doc !== undefined) {
        const version = doc.versionsByYear[year];

        if (version !== undefined) {
            const baseUrl = `${BOFIP_BASE}/${doc.documentId}/identifiant=${reference}-${version.date}`;
            const paragraphNumber = paragraph?.match(/(\d+)/)?.[1];
            const anchor = paragraphNumber !== undefined ? version.anchors[paragraphNumber] : undefined;

            return anchor !== undefined ? `${baseUrl}#${anchor}` : baseUrl;
        }
    }

    const params = new URLSearchParams({
        type_recherche: 'simple',
        query: reference,
    });

    return `${BOFIP_SEARCH}?${params.toString()}`;
}

function resolveLegalLinkFor(
    ref: LegalReference,
    year: number,
): ResolvedLegalLink | null {
    if (ref.type === 'CIBS' && ref.article) {
        return {
            label: `CIBS ${ref.article}`,
            url: cibsUrlFor(ref.article, year),
            title: `Article ${ref.article} du Code des impositions sur les biens et services, version applicable au 01/01/${year} (Légifrance)`,
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
            url: bofipUrlFor(ref.reference, year, ref.paragraph),
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

    if (!fallbackLabel) {
        return null;
    }

    return {
        label: fallbackLabel,
        url: '',
        title: fallbackLabel,
    };
}

export type UseOfficialLegalLinksReturn = {
    /** Année fiscale utilisée pour résoudre les versions. */
    fiscalYear: ComputedRef<number>;
    /** Résout une référence vers son lien officiel. */
    resolveLegalLink: (ref: LegalReference) => ResolvedLegalLink | null;
    /** Résout un tableau de références. */
    resolveAll: (refs: LegalReference[]) => ResolvedLegalLink[];
};

/**
 * Chantier J (ADR-0020) : l'année est désormais passée en argument
 * (par la page consommatrice qui gère son sélecteur local) plutôt que
 * lue depuis un état global supprimé.
 */
export function useOfficialLegalLinks(
    year: MaybeRefOrGetter<number>,
): UseOfficialLegalLinksReturn {
    const yearRef = computed<number>(() => toValue(year));

    const resolveLegalLink = (ref: LegalReference): ResolvedLegalLink | null =>
        resolveLegalLinkFor(ref, yearRef.value);

    const resolveAll = (refs: LegalReference[]): ResolvedLegalLink[] =>
        refs
            .map((r) => resolveLegalLink(r))
            .filter((l): l is ResolvedLegalLink => l !== null);

    return {
        fiscalYear: yearRef,
        resolveLegalLink,
        resolveAll,
    };
}
