import { describe, expect, it } from 'vitest';
import { deriveTenantPalette, hexToOklch } from './tenant-theme';

describe('hexToOklch', () => {
    it('extracts a hue for a saturated red', () => {
        const { h, c } = hexToOklch('#FF0000');

        expect(h).toBeGreaterThan(20);
        expect(h).toBeLessThan(40);
        expect(c).toBeGreaterThan(0.2);
    });

    it('returns near-zero chroma for a neutral gray', () => {
        const { c } = hexToOklch('#808080');

        expect(c).toBeLessThan(0.01);
    });

    it('returns high lightness for near-white', () => {
        const { l } = hexToOklch('#FEFEFE');

        expect(l).toBeGreaterThan(0.95);
    });

    it('returns low lightness for near-black', () => {
        const { l } = hexToOklch('#0A0A0A');

        expect(l).toBeLessThan(0.15);
    });
});

describe('deriveTenantPalette', () => {
    it('clamps chroma to 0.15 even for a highly saturated input', () => {
        const palette = deriveTenantPalette('#FF0000');
        const chroma = Number(palette.accent.match(/oklch\(48% ([\d.]+)/)?.[1]);

        expect(chroma).toBeLessThanOrEqual(0.15);
    });

    it('always fixes accent lightness at 48% and hover at 40%, regardless of input lightness', () => {
        const pale = deriveTenantPalette('#FFFACD');
        const dark = deriveTenantPalette('#1A1A2E');

        expect(pale.accent).toMatch(/^oklch\(48% /);
        expect(pale.accentHover).toMatch(/^oklch\(40% /);
        expect(dark.accent).toMatch(/^oklch\(48% /);
        expect(dark.accentHover).toMatch(/^oklch\(40% /);
    });

    it('produces a light, low-chroma tint', () => {
        const palette = deriveTenantPalette('#2E86AB');

        expect(palette.tint).toMatch(/^oklch\(94% 0\.03 /);
    });

    it('never crashes on an achromatic input', () => {
        expect(() => deriveTenantPalette('#FFFFFF')).not.toThrow();
        expect(() => deriveTenantPalette('#000000')).not.toThrow();
    });
});
