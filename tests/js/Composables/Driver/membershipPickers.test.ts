import { describe, expect, it } from 'vitest';
import {
    filterAvailableCompanies,
    filterAvailableDrivers,
} from '@/Composables/Driver/membershipPickers';

const companies = [
    { id: 1, shortCode: 'ACM', legalName: 'Acme SAS' },
    { id: 2, shortCode: 'BCO', legalName: 'Beta Corp' },
    { id: 3, shortCode: 'CGM', legalName: 'Gamma SARL' },
];

const drivers = [
    { id: 10, fullName: 'Marie Dupont', initials: 'MD' },
    { id: 11, fullName: 'Paul Martin', initials: 'PM' },
    { id: 12, fullName: 'Sophie Leroy', initials: 'SL' },
];

describe('filterAvailableCompanies', () => {
    it('mappe les options en {value, label} avec shortCode + legalName', () => {
        const result = filterAvailableCompanies(companies, []);

        expect(result).toEqual([
            { value: 1, label: 'ACM · Acme SAS' },
            { value: 2, label: 'BCO · Beta Corp' },
            { value: 3, label: 'CGM · Gamma SARL' },
        ]);
    });

    it('exclut les companies dont l\'id est dans excludedIds', () => {
        const result = filterAvailableCompanies(companies, [1, 3]);

        expect(result).toEqual([{ value: 2, label: 'BCO · Beta Corp' }]);
    });

    it('retourne un tableau vide quand toutes les companies sont exclues', () => {
        const result = filterAvailableCompanies(companies, [1, 2, 3]);

        expect(result).toEqual([]);
    });

    it('retourne un tableau vide quand la liste source est vide', () => {
        const result = filterAvailableCompanies([], [1, 2]);

        expect(result).toEqual([]);
    });

    it('ignore silencieusement les ids exclus inconnus', () => {
        // 999 n'existe pas dans la liste source — pas de side effect
        const result = filterAvailableCompanies(companies, [999, 1]);

        expect(result).toEqual([
            { value: 2, label: 'BCO · Beta Corp' },
            { value: 3, label: 'CGM · Gamma SARL' },
        ]);
    });
});

describe('filterAvailableDrivers', () => {
    it('mappe les options en {value, label} avec fullName comme label', () => {
        const result = filterAvailableDrivers(drivers, []);

        expect(result).toEqual([
            { value: 10, label: 'Marie Dupont' },
            { value: 11, label: 'Paul Martin' },
            { value: 12, label: 'Sophie Leroy' },
        ]);
    });

    it('exclut les drivers dont l\'id est dans excludedIds', () => {
        const result = filterAvailableDrivers(drivers, [10]);

        expect(result).toEqual([
            { value: 11, label: 'Paul Martin' },
            { value: 12, label: 'Sophie Leroy' },
        ]);
    });

    it('retourne un tableau vide quand tous les drivers sont exclus', () => {
        const result = filterAvailableDrivers(drivers, [10, 11, 12]);

        expect(result).toEqual([]);
    });
});
