import { describe, expect, it } from 'vitest';
import { findMatchingVariant, isValueAvailable } from './variant-resolution';
import type { Variant } from './variant-resolution';

const sizeSmall: Variant = {
    id: 'v-s',
    price: 10000,
    salePrice: null,
    optionValueIds: ['size-s'],
    stock: 5,
};
const sizeMediumOutOfStock: Variant = {
    id: 'v-m',
    price: 10000,
    salePrice: null,
    optionValueIds: ['size-m'],
    stock: 0,
};
const sizeLarge: Variant = {
    id: 'v-l',
    price: 12000,
    salePrice: null,
    optionValueIds: ['size-l'],
    stock: 3,
};
const variants = [sizeSmall, sizeMediumOutOfStock, sizeLarge];

describe('findMatchingVariant', () => {
    it('returns null when not every option has a selection', () => {
        expect(findMatchingVariant(variants, {}, ['size'])).toBeNull();
    });

    it('returns the variant matching the full selection', () => {
        const result = findMatchingVariant(variants, { size: 'size-l' }, [
            'size',
        ]);

        expect(result?.id).toBe('v-l');
    });

    it('resolves a single-variant, zero-option product unconditionally', () => {
        const simple: Variant = {
            id: 'v-simple',
            price: 5000,
            salePrice: null,
            optionValueIds: [],
            stock: 2,
        };

        expect(findMatchingVariant([simple], {}, [])?.id).toBe('v-simple');
    });
});

describe('isValueAvailable', () => {
    it('is true for a value with at least one in-stock variant', () => {
        expect(isValueAvailable(variants, 'size', 'size-s', {})).toBe(true);
    });

    it('is false for a value whose only variant is out of stock', () => {
        expect(isValueAvailable(variants, 'size', 'size-m', {})).toBe(false);
    });

    it('accounts for other currently-selected option values', () => {
        const colorRedSmall: Variant = {
            id: 'v-red-s',
            price: 9000,
            salePrice: null,
            optionValueIds: ['color-red', 'size-s'],
            stock: 4,
        };
        const colorRedMediumOutOfStock: Variant = {
            id: 'v-red-m',
            price: 9000,
            salePrice: null,
            optionValueIds: ['color-red', 'size-m'],
            stock: 0,
        };
        const multiVariants = [colorRedSmall, colorRedMediumOutOfStock];

        expect(
            isValueAvailable(multiVariants, 'size', 'size-s', {
                color: 'color-red',
            }),
        ).toBe(true);
        expect(
            isValueAvailable(multiVariants, 'size', 'size-m', {
                color: 'color-red',
            }),
        ).toBe(false);
    });
});
