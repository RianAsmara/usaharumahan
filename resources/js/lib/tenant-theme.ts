export const DEFAULT_TENANT_COLOR = '#C2703D';

function srgbToLinear(channel: number): number {
    return channel <= 0.04045
        ? channel / 12.92
        : Math.pow((channel + 0.055) / 1.055, 2.4);
}

/**
 * sRGB hex -> OKLCH, using the standard OKLab conversion matrices
 * (Björn Ottosson, "A perceptual color space for image processing").
 */
export function hexToOklch(hex: string): { l: number; c: number; h: number } {
    const normalized = hex.replace('#', '');
    const r = srgbToLinear(parseInt(normalized.slice(0, 2), 16) / 255);
    const g = srgbToLinear(parseInt(normalized.slice(2, 4), 16) / 255);
    const b = srgbToLinear(parseInt(normalized.slice(4, 6), 16) / 255);

    const lCone = 0.4122214708 * r + 0.5363325363 * g + 0.0514459929 * b;
    const mCone = 0.2119034982 * r + 0.6806995451 * g + 0.1073969566 * b;
    const sCone = 0.0883024619 * r + 0.2817188376 * g + 0.6299787005 * b;

    const lRoot = Math.cbrt(lCone);
    const mRoot = Math.cbrt(mCone);
    const sRoot = Math.cbrt(sCone);

    const l = 0.2104542553 * lRoot + 0.793617785 * mRoot - 0.0040720468 * sRoot;
    const a = 1.9779984951 * lRoot - 2.428592205 * mRoot + 0.4505937099 * sRoot;
    const bLab =
        0.0259040371 * lRoot + 0.7827717662 * mRoot - 0.808675766 * sRoot;

    const c = Math.sqrt(a * a + bLab * bLab);
    let h = (Math.atan2(bLab, a) * 180) / Math.PI;

    if (h < 0) {
        h += 360;
    }

    return { l, c, h };
}

export type TenantPalette = {
    accent: string;
    accentHover: string;
    tint: string;
};

/**
 * Derives the storefront's tenant-branded tokens from a seller-chosen hex
 * color. Lightness and chroma are fixed/clamped per role so any hex a
 * UMKM seller picks — pastel, neon, near-black — still renders legibly;
 * only the hue is taken from their input.
 */
export function deriveTenantPalette(hex: string): TenantPalette {
    const { c, h } = hexToOklch(hex);
    const clampedChroma = Math.min(c, 0.15).toFixed(3);
    const hue = h.toFixed(1);

    return {
        accent: `oklch(48% ${clampedChroma} ${hue})`,
        accentHover: `oklch(40% ${clampedChroma} ${hue})`,
        tint: `oklch(94% 0.03 ${hue})`,
    };
}
