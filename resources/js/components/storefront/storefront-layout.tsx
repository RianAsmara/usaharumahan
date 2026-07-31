import type { CSSProperties, ReactNode } from 'react';
import { DEFAULT_TENANT_COLOR, deriveTenantPalette } from '@/lib/tenant-theme';

type Props = {
    primaryColor: string | null;
    children: ReactNode;
};

export function StorefrontLayout({ primaryColor, children }: Props) {
    const palette = deriveTenantPalette(primaryColor ?? DEFAULT_TENANT_COLOR);

    const style = {
        '--tenant-accent': palette.accent,
        '--tenant-accent-hover': palette.accentHover,
        '--tenant-tint': palette.tint,
    } as CSSProperties;

    return (
        <div
            className="storefront min-h-screen bg-[var(--storefront-paper)] text-[var(--storefront-ink)]"
            style={style}
        >
            {children}
        </div>
    );
}
