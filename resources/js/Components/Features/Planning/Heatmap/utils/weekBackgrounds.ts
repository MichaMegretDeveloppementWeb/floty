import { CELLS_PER_YEAR, cellOriginForYear } from '@/Utils/Date/isoWeeks';

/**
 * Backgrounds CSS pour les 52 OU 53 cellules semaine de la grille heatmap ·
 * un par semaine ISO de l'année fiscale, alternant les mois pair/impair
 * pour matérialiser visuellement les frontières.
 *
 * SC12 (2026-05-18) · approche refondue · au lieu de positions absolute
 * en pixels (qui décrochaient des vraies cellules `basis-5 grow` à largeur
 * dynamique), on génère un overlay FLEX strictement synchronisé avec les
 * vraies cellules. Chaque cellule overlay a soit ·
 *   - une couleur uniforme (semaine entièrement dans 1 mois)
 *   - un linear-gradient (semaine qui chevauche 2 mois) · transition
 *     exactement au jour de bascule
 *
 * L'overlay est posé EN ARRIÈRE des vraies cellules · les cellules vides
 * `bg-white` opaques masquent le fond · l'utilisateur voit la transition
 * dans les zones libres (gaps 1px entre cellules, 14px au-dessus/en-dessous
 * des boutons h-7 dans chaque row h-56).
 *
 * Convention couleurs ·
 *   - Mois impairs (1, 3, 5, 7, 9, 11) → `rgb(241 245 249)` (slate-100)
 *   - Mois pairs (2, 4, 6, 8, 10, 12) → `transparent` (fond parent blanc)
 */

const COLOR_ODD = 'rgb(241 245 249)'; // slate-100
const COLOR_EVEN = 'transparent';

function colorForMonth(monthIdx: number): string {
    return monthIdx % 2 === 1 ? COLOR_ODD : COLOR_EVEN;
}

/**
 * Retourne 52 strings CSS `background` (une par semaine ISO de l'année).
 *
 * Algo · pour chaque semaine, on parcourt ses 7 jours. Si tous les jours
 * sont dans le même mois civil → couleur uniforme. Si la semaine change
 * de mois au jour D (1-indexé, lundi=1, dimanche=7) → gradient avec
 * transition à `(D-1) / 7 * 100%`.
 *
 * Cas particulier · 1ère semaine ISO de Y peut contenir des jours de
 * décembre Y-1 (ex. 2026 commence par 29/12/2025-04/01/2026) · on
 * traite le « mois » de chaque jour en tenant compte de l'année du jour
 * (déc Y-1 = mois 12 pair · jan Y = mois 1 impair).
 *
 * @return list de 52 strings CSS `background` à appliquer en :style="{ background: ... }"
 */
export function weekBackgroundsForYear(year: number): string[] {
    const origin = cellOriginForYear(year);
    const backgrounds: string[] = [];

    for (let week = 0; week < CELLS_PER_YEAR; week++) {
        // 7 jours de cette semaine, mois civil de chacun
        const months: number[] = [];
        for (let day = 0; day < 7; day++) {
            const date = new Date(origin);
            date.setUTCDate(origin.getUTCDate() + week * 7 + day);
            // month 1-indexed pour cohérence avec mois calendaire
            months.push(date.getUTCMonth() + 1);
        }

        // Si tous identiques → uniforme
        const firstMonth = months[0]!;
        const allSame = months.every((m) => m === firstMonth);

        if (allSame) {
            backgrounds.push(colorForMonth(firstMonth));
            continue;
        }

        // Sinon · trouver le 1er jour où le mois change (D 1-indexé)
        let transitionDayIdx = 0;
        for (let i = 1; i < 7; i++) {
            if (months[i] !== firstMonth) {
                transitionDayIdx = i;
                break;
            }
        }

        const month1 = firstMonth;
        const month2 = months[transitionDayIdx]!;
        const fraction = (transitionDayIdx / 7) * 100;

        // hard-stop linear-gradient · pas de fondu, transition nette
        const c1 = colorForMonth(month1);
        const c2 = colorForMonth(month2);

        backgrounds.push(
            `linear-gradient(to right, ${c1} ${fraction}%, ${c2} ${fraction}%)`,
        );
    }

    return backgrounds;
}
