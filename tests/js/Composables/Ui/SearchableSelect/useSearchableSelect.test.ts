import { describe, expect, it, vi } from 'vitest';
import { ref } from 'vue';
import {
    filterOptions,
    useSearchableSelect,
} from '@/Composables/Ui/SearchableSelect/useSearchableSelect';
import type { SelectOption } from '@/Composables/Ui/SearchableSelect/useSearchableSelect';

const sampleOptions: readonly SelectOption[] = [
    { value: 1, label: 'Alpha' },
    { value: 2, label: 'Beta' },
    { value: 3, label: 'Gamma' },
];

function setup(initialModel: string | number | null = null) {
    const rootRef = ref<HTMLElement | null>(null);
    const options = ref<readonly SelectOption[]>(sampleOptions);
    const modelValue = ref<string | number | null>(initialModel);
    const onSelect = vi.fn((value: string | number): void => {
        modelValue.value = value;
    });

    const ctx = useSearchableSelect(rootRef, options, modelValue, onSelect);

    return { ctx, modelValue, onSelect };
}

describe('filterOptions', () => {
    const opts: readonly SelectOption[] = [
        { value: 1, label: 'Apple' },
        { value: 2, label: 'banana' },
        { value: 3, label: 'cherry' },
    ];

    it('returns all options for empty query', () => {
        expect(filterOptions(opts, '')).toEqual(opts);
    });

    it('returns all options for whitespace-only query', () => {
        expect(filterOptions(opts, '   ')).toEqual(opts);
    });

    it('filters case-insensitively', () => {
        expect(filterOptions(opts, 'APP')).toEqual([opts[0]]);
        expect(filterOptions(opts, 'an')).toEqual([opts[1]]);
    });

    it('returns empty array when no match', () => {
        expect(filterOptions(opts, 'xyz')).toEqual([]);
    });

    it('returns a copy (not the input array)', () => {
        const result = filterOptions(opts, '');
        expect(result).not.toBe(opts);
        expect(result).toEqual(opts);
    });
});

describe('useSearchableSelect', () => {
    it('starts closed', () => {
        const { ctx } = setup();
        expect(ctx.isOpen.value).toBe(false);
    });

    it('open() initialises highlightedIndex on selected option', () => {
        const { ctx } = setup(2); // Beta is at index 1
        ctx.open();
        expect(ctx.isOpen.value).toBe(true);
        expect(ctx.highlightedIndex.value).toBe(1);
    });

    it('open() defaults highlightedIndex to 0 when no selection', () => {
        const { ctx } = setup(null);
        ctx.open();
        expect(ctx.highlightedIndex.value).toBe(0);
    });

    it('open() defaults highlightedIndex to 0 when selected value not in options', () => {
        const { ctx } = setup(999);
        ctx.open();
        expect(ctx.highlightedIndex.value).toBe(0);
    });

    it('close() resets isOpen, query and highlightedIndex', () => {
        const { ctx } = setup();
        ctx.open();
        ctx.query.value = 'foo';
        ctx.highlightedIndex.value = 2;
        ctx.close();
        expect(ctx.isOpen.value).toBe(false);
        expect(ctx.query.value).toBe('');
        expect(ctx.highlightedIndex.value).toBe(0);
    });

    it('toggle() flips state', () => {
        const { ctx } = setup();
        ctx.toggle();
        expect(ctx.isOpen.value).toBe(true);
        ctx.toggle();
        expect(ctx.isOpen.value).toBe(false);
    });

    it('selectedOption tracks modelValue', () => {
        const { ctx, modelValue } = setup(null);
        expect(ctx.selectedOption.value).toBeNull();

        modelValue.value = 2;
        expect(ctx.selectedOption.value).toEqual({ value: 2, label: 'Beta' });
    });

    it('keyboard ArrowDown increments highlightedIndex', () => {
        const { ctx } = setup();
        ctx.open();
        expect(ctx.highlightedIndex.value).toBe(0);

        ctx.onKeyDown(new KeyboardEvent('keydown', { key: 'ArrowDown' }));
        expect(ctx.highlightedIndex.value).toBe(1);
    });

    it('keyboard ArrowDown clamps at last option', () => {
        const { ctx } = setup();
        ctx.open();
        ctx.highlightedIndex.value = 2; // last (Gamma)

        ctx.onKeyDown(new KeyboardEvent('keydown', { key: 'ArrowDown' }));
        expect(ctx.highlightedIndex.value).toBe(2);
    });

    it('keyboard ArrowUp decrements highlightedIndex', () => {
        const { ctx } = setup();
        ctx.open();
        ctx.highlightedIndex.value = 2;

        ctx.onKeyDown(new KeyboardEvent('keydown', { key: 'ArrowUp' }));
        expect(ctx.highlightedIndex.value).toBe(1);
    });

    it('keyboard ArrowUp clamps at 0', () => {
        const { ctx } = setup();
        ctx.open();

        ctx.onKeyDown(new KeyboardEvent('keydown', { key: 'ArrowUp' }));
        expect(ctx.highlightedIndex.value).toBe(0);
    });

    it('keyboard Enter selects the highlighted option and closes', () => {
        const { ctx, onSelect } = setup();
        ctx.open();
        ctx.highlightedIndex.value = 1; // Beta (value: 2)

        ctx.onKeyDown(new KeyboardEvent('keydown', { key: 'Enter' }));

        expect(onSelect).toHaveBeenCalledWith(2);
        expect(ctx.isOpen.value).toBe(false);
    });

    it('keyboard Escape closes', () => {
        const { ctx } = setup();
        ctx.open();

        ctx.onKeyDown(new KeyboardEvent('keydown', { key: 'Escape' }));
        expect(ctx.isOpen.value).toBe(false);
    });

    it('keyboard events ignored when closed', () => {
        const { ctx } = setup();
        // pas de open()

        ctx.onKeyDown(new KeyboardEvent('keydown', { key: 'ArrowDown' }));
        expect(ctx.isOpen.value).toBe(false);
        expect(ctx.highlightedIndex.value).toBe(0);
    });

    it('selectByIndex calls onSelect with option value and closes', () => {
        const { ctx, onSelect } = setup();
        ctx.open();
        ctx.selectByIndex(2); // Gamma (value: 3)

        expect(onSelect).toHaveBeenCalledWith(3);
        expect(ctx.isOpen.value).toBe(false);
    });

    it('selectByIndex ignores out-of-bounds index', () => {
        const { ctx, onSelect } = setup();
        ctx.open();
        ctx.selectByIndex(99);

        expect(onSelect).not.toHaveBeenCalled();
        expect(ctx.isOpen.value).toBe(true);
    });
});
