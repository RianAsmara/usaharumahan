import { describe, expect, it } from 'vitest';
import { formatRupiah } from './currency';

describe('formatRupiah', () => {
    it('formats whole rupiah amounts with a period thousands separator', () => {
        expect(formatRupiah(25000)).toBe('Rp 25.000');
    });

    it('formats amounts under one thousand without a separator', () => {
        expect(formatRupiah(500)).toBe('Rp 500');
    });

    it('formats large amounts with multiple separators', () => {
        expect(formatRupiah(1250000)).toBe('Rp 1.250.000');
    });

    it('rounds fractional amounts', () => {
        expect(formatRupiah(19999.6)).toBe('Rp 20.000');
    });
});
