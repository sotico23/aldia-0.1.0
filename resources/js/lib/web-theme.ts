export interface WebThemeColors {
    primary?: string | null;
    secondary?: string | null;
    accent?: string | null;
}

const HEX_COLOR = /^#([0-9a-f]{3}|[0-9a-f]{6})$/i;

function normalizeHexColor(color: string | null | undefined): string | null {
    if (typeof color !== 'string') {
        return null;
    }

    const value = color.trim();

    return HEX_COLOR.test(value) ? value : null;
}

/**
 * Apply the institutional palette to the global CSS custom properties that
 * back the emerald theme tokens defined in app.css.
 */
export function applyWebTheme(colors?: WebThemeColors | null): void {
    if (typeof document === 'undefined' || !colors) {
        return;
    }

    const root = document.documentElement;
    const primary = normalizeHexColor(colors.primary);
    const secondary = normalizeHexColor(colors.secondary);
    const accent = normalizeHexColor(colors.accent);

    if (primary) {
        root.style.setProperty('--brand-primary', primary);
    }

    if (secondary) {
        root.style.setProperty('--brand-secondary', secondary);
    }

    if (accent) {
        root.style.setProperty('--brand-accent', accent);
    }
}

/**
 * Read the palette from the shared Inertia page props and apply it globally.
 */
export function applyWebThemeFromPageProps(pageProps: unknown): void {
    const webSettings = (
        pageProps as { web_settings?: WebThemeColors } | null | undefined
    )?.web_settings;

    applyWebTheme(webSettings);
}
