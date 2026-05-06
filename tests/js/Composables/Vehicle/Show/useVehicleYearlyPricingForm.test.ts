import { describe, expect, it } from 'vitest';
import {
    buildPricingFormInitialState,
    isPricingFormValid,
    transformPricingFormToPayload,
} from '@/Composables/Vehicle/Show/useVehicleYearlyPricingForm';

type Pricing = App.Data.User.Vehicle.VehicleYearlyPricingData;

/**
 * Tests des helpers purs qui alimentent `useVehicleYearlyPricingForm`
 * (Phase 14.B facturation V1.2).
 *
 * Ces fonctions sont extraites du composable pour permettre un test
 * unitaire sans monter `useForm` Inertia. Pattern aligné sur
 * `countConflictDaysInRange` extrait de `useUnavailabilityForm`.
 */
describe('buildPricingFormInitialState', () => {
    it('initialise en mode création avec currentYear quand elle est disponible', () => {
        const state = buildPricingFormInitialState(null, [2023, 2024, 2025], 2024);

        expect(state).toEqual({
            year: 2024,
            dailyRateEuros: null,
            weeklyRateEuros: null,
            monthlyRateEuros: null,
        });
    });

    it('initialise en mode création avec la 1ʳᵉ année dispo si currentYear est déjà couverte', () => {
        // currentYear absent → fallback sur la 1ʳᵉ année de la liste
        const state = buildPricingFormInitialState(null, [2026, 2027], 2024);

        expect(state.year).toBe(2026);
    });

    it('retombe sur currentYear si la liste des années dispo est vide', () => {
        // Cas dégénéré : toutes les années sont déjà couvertes. Le bouton
        // « Ajouter » sera désactivé en amont, mais l'init ne doit pas
        // crasher.
        const state = buildPricingFormInitialState(null, [], 2024);

        expect(state.year).toBe(2024);
    });

    it('initialise en mode édition avec conversion cents → € sur les trois tarifs', () => {
        const editing: Pricing = {
            year: 2024,
            dailyRateCents: 9_000,
            weeklyRateCents: 50_000,
            monthlyRateCents: 180_000,
        };

        const state = buildPricingFormInitialState(editing, [2024, 2025], 2026);

        expect(state).toEqual({
            year: 2024,
            dailyRateEuros: 90,
            weeklyRateEuros: 500,
            monthlyRateEuros: 1800,
        });
    });

    it('mode édition : ignore availableYears et currentYear (l\'année est verrouillée sur celle du DTO)', () => {
        const editing: Pricing = {
            year: 2022,
            dailyRateCents: 8_000,
            weeklyRateCents: 45_000,
            monthlyRateCents: 160_000,
        };

        const state = buildPricingFormInitialState(editing, [2024, 2025, 2026], 2026);

        expect(state.year).toBe(2022);
    });
});

describe('isPricingFormValid', () => {
    const validForm = {
        year: 2024,
        dailyRateEuros: 90,
        weeklyRateEuros: 500,
        monthlyRateEuros: 1800,
    };

    it('renvoie true sur un formulaire complet et non en cours de soumission', () => {
        expect(isPricingFormValid(validForm, false)).toBe(true);
    });

    it('renvoie false pendant une soumission en cours (anti-double-clic)', () => {
        expect(isPricingFormValid(validForm, true)).toBe(false);
    });

    it('renvoie false si l\'année est ≤ 0', () => {
        expect(isPricingFormValid({ ...validForm, year: 0 }, false)).toBe(false);
        expect(isPricingFormValid({ ...validForm, year: -1 }, false)).toBe(false);
    });

    it('renvoie false si un des trois tarifs est null (champ vide)', () => {
        expect(isPricingFormValid({ ...validForm, dailyRateEuros: null }, false)).toBe(false);
        expect(isPricingFormValid({ ...validForm, weeklyRateEuros: null }, false)).toBe(false);
        expect(isPricingFormValid({ ...validForm, monthlyRateEuros: null }, false)).toBe(false);
    });

    it('renvoie false si un des tarifs est strictement négatif', () => {
        expect(isPricingFormValid({ ...validForm, dailyRateEuros: -1 }, false)).toBe(false);
        expect(isPricingFormValid({ ...validForm, weeklyRateEuros: -50 }, false)).toBe(false);
        expect(isPricingFormValid({ ...validForm, monthlyRateEuros: -100 }, false)).toBe(false);
    });

    it('autorise un tarif à 0 (cas véhicule de courtoisie / usage gratuit)', () => {
        // Cas légitime documenté dans le test backend
        // `permet_un_tarif_zero_pour_les_vehicules_en_usage_gratuit`.
        const allZero = {
            year: 2024,
            dailyRateEuros: 0,
            weeklyRateEuros: 0,
            monthlyRateEuros: 0,
        };

        expect(isPricingFormValid(allZero, false)).toBe(true);
    });
});

describe('transformPricingFormToPayload', () => {
    it('convertit les € en cents avec snake_case attendu par Spatie Data', () => {
        const payload = transformPricingFormToPayload({
            year: 2024,
            dailyRateEuros: 90,
            weeklyRateEuros: 500,
            monthlyRateEuros: 1800,
        });

        expect(payload).toEqual({
            year: 2024,
            daily_rate_cents: 9_000,
            weekly_rate_cents: 50_000,
            monthly_rate_cents: 180_000,
        });
    });

    it('utilise Math.round pour absorber les imprécisions IEEE 754 (1.10 € → 110 cents)', () => {
        // 1.10 * 100 === 110.00000000000001 en flottant ; Math.trunc()
        // retournerait 109. Math.round() est crucial sur cette ligne.
        const payload = transformPricingFormToPayload({
            year: 2024,
            dailyRateEuros: 1.1,
            weeklyRateEuros: 1.1,
            monthlyRateEuros: 1.1,
        });

        expect(payload.daily_rate_cents).toBe(110);
        expect(payload.weekly_rate_cents).toBe(110);
        expect(payload.monthly_rate_cents).toBe(110);
    });

    it('traite null comme 0 (sécurité ceinture, le caller a déjà filtré via isPricingFormValid)', () => {
        const payload = transformPricingFormToPayload({
            year: 2024,
            dailyRateEuros: null,
            weeklyRateEuros: null,
            monthlyRateEuros: null,
        });

        expect(payload.daily_rate_cents).toBe(0);
        expect(payload.weekly_rate_cents).toBe(0);
        expect(payload.monthly_rate_cents).toBe(0);
    });
});
