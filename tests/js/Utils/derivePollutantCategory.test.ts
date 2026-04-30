import { describe, expect, it } from 'vitest';
import {
    derivePollutantCategory,
    requiresUnderlyingCombustionEngine,
} from '@/Utils/derivePollutantCategory';

/**
 * Mirroir TS de PollutantCategory::derive() côté backend.
 * Les deux implémentations DOIVENT rester strictement équivalentes —
 * tout changement de cascade côté PHP doit être répliqué ici (et
 * réciproquement). Les cas couverts ici sont alignés sur
 * `PollutantCategoryDeriveTest` côté backend.
 */
describe('derivePollutantCategory', () => {
    it('renvoie "e" pour une motorisation strictement non-thermique', () => {
        expect(derivePollutantCategory('electric', 'euro_6d_isc_fcm', null))
            .toBe('e');
        expect(derivePollutantCategory('hydrogen', null, null))
            .toBe('e');
        expect(derivePollutantCategory('electric_hydrogen', null, 'not_applicable'))
            .toBe('e');
    });

    it('renvoie "category_1" pour les allumages commandés Euro 5+', () => {
        expect(derivePollutantCategory('gasoline', 'euro_5', null))
            .toBe('category_1');
        expect(derivePollutantCategory('lpg', 'euro_6d', null))
            .toBe('category_1');
        expect(derivePollutantCategory('cng', 'euro_5b', null))
            .toBe('category_1');
        expect(derivePollutantCategory('e85', 'euro_6', null))
            .toBe('category_1');
    });

    it('renvoie "category_1" pour un hybride à sous-jacent essence Euro 5+', () => {
        expect(derivePollutantCategory('non_plugin_hybrid', 'euro_6', 'gasoline'))
            .toBe('category_1');
        expect(derivePollutantCategory('plugin_hybrid', 'euro_5', 'gasoline'))
            .toBe('category_1');
    });

    it('renvoie "most_polluting" pour un hybride à sous-jacent Diesel', () => {
        expect(derivePollutantCategory('non_plugin_hybrid', 'euro_6', 'diesel'))
            .toBe('most_polluting');
    });

    it('renvoie "most_polluting" pour un hybride sans sous-jacent renseigné', () => {
        expect(derivePollutantCategory('non_plugin_hybrid', 'euro_6', null))
            .toBe('most_polluting');
        expect(derivePollutantCategory('non_plugin_hybrid', 'euro_6', ''))
            .toBe('most_polluting');
    });

    it('renvoie "most_polluting" pour Diesel quelle que soit la norme', () => {
        expect(derivePollutantCategory('diesel', 'euro_6d_isc_fcm', null))
            .toBe('most_polluting');
        expect(derivePollutantCategory('diesel', 'euro_2', null))
            .toBe('most_polluting');
    });

    it('renvoie "most_polluting" pour essence pré-Euro 5', () => {
        expect(derivePollutantCategory('gasoline', 'euro_4', null))
            .toBe('most_polluting');
        expect(derivePollutantCategory('gasoline', 'euro_3', null))
            .toBe('most_polluting');
    });

    it('renvoie "most_polluting" pour essence sans norme renseignée', () => {
        expect(derivePollutantCategory('gasoline', null, null))
            .toBe('most_polluting');
        expect(derivePollutantCategory('gasoline', '', null))
            .toBe('most_polluting');
    });
});

describe('requiresUnderlyingCombustionEngine', () => {
    it('renvoie true pour les sources hybrides', () => {
        expect(requiresUnderlyingCombustionEngine('plugin_hybrid')).toBe(true);
        expect(requiresUnderlyingCombustionEngine('non_plugin_hybrid')).toBe(true);
        expect(requiresUnderlyingCombustionEngine('electric_hydrogen')).toBe(true);
    });

    it('renvoie false pour les autres sources', () => {
        expect(requiresUnderlyingCombustionEngine('gasoline')).toBe(false);
        expect(requiresUnderlyingCombustionEngine('diesel')).toBe(false);
        expect(requiresUnderlyingCombustionEngine('electric')).toBe(false);
        expect(requiresUnderlyingCombustionEngine('hydrogen')).toBe(false);
        expect(requiresUnderlyingCombustionEngine('lpg')).toBe(false);
        expect(requiresUnderlyingCombustionEngine('cng')).toBe(false);
        expect(requiresUnderlyingCombustionEngine('e85')).toBe(false);
    });
});
