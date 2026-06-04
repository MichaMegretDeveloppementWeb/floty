import { CELLS_PER_YEAR, cellOriginForYear } from '@/Utils/date/isoWeeks';

/**
 * One CSS background per heatmap week cell, alternating month parity.
 * Same-month weeks get a solid color, cross-month weeks a hard-stop linear-gradient.
 */

const COLOR_ODD = 'rgb(241 245 249)'; // slate-100
const COLOR_EVEN = 'transparent';

function colorForMonth(monthIdx: number): string {
    return monthIdx % 2 === 1 ? COLOR_ODD : COLOR_EVEN;
}

/** Returns one CSS background string per heatmap week cell of the year. */
export function weekBackgroundsForYear(year: number): string[] {
    const origin = cellOriginForYear(year);
    const backgrounds: string[] = [];

    for (let week = 0; week < CELLS_PER_YEAR; week++) {
        const months: number[] = [];

        for (let day = 0; day < 7; day++) {
            const date = new Date(origin);
            date.setUTCDate(origin.getUTCDate() + week * 7 + day);
            months.push(date.getUTCMonth() + 1);
        }

        const firstMonth = months[0]!;
        const allSame = months.every((m) => m === firstMonth);

        if (allSame) {
            backgrounds.push(colorForMonth(firstMonth));
            continue;
        }

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

        const c1 = colorForMonth(month1);
        const c2 = colorForMonth(month2);

        backgrounds.push(
            `linear-gradient(to right, ${c1} ${fraction}%, ${c2} ${fraction}%)`,
        );
    }

    return backgrounds;
}
