/**
 * Durée d'un contrat en jours, bornes incluses (start + end).
 *
 * Utilisé par les pages Create/Edit Contracts pour le recap live ·
 * mutualisé Lot 7 D07 (F-40-010, ex-duplication identique entre les
 * 2 pages).
 *
 * Retourne `null` si l'une des dates est vide ou si end < start ·
 * permet aux composants consommateurs d'afficher l'état "à compléter"
 * sans branche conditionnelle complexe côté `<template>`.
 *
 * Cohérent avec `Contract::countDaysInYear` côté backend qui
 * compte aussi inclusivement (start..end inclus).
 */
export function computeContractDurationDays(
    startDate: string,
    endDate: string,
): number | null {
    if (!startDate || !endDate) {
        return null;
    }

    const start = new Date(startDate);
    const end = new Date(endDate);
    const days = Math.floor((end.getTime() - start.getTime()) / (1000 * 60 * 60 * 24)) + 1;

    return days > 0 ? days : null;
}
