import { router } from '@inertiajs/vue3';

type Gtag = (...args: unknown[]) => void;

declare global {
    interface Window {
        gtag?: Gtag;
    }
}

/**
 * Report a page view to Google Analytics. No-op when the gtag snippet was not
 * rendered (i.e. GOOGLE_ANALYTICS_MEASUREMENT_ID is unset).
 */
export function trackPageView(): void {
    window.gtag?.('event', 'page_view', {
        page_location: window.location.href,
        page_path: window.location.pathname + window.location.search,
        page_title: document.title,
    });
}

/**
 * Report a custom GA4 event. No-op when analytics is disabled.
 */
export function trackEvent(name: string, params: Record<string, unknown> = {}): void {
    window.gtag?.('event', name, params);
}

/**
 * Send a page view for the current page and for every subsequent Inertia
 * navigation. Inertia swaps pages without a full reload, so gtag would
 * otherwise only ever see the first page of a session.
 */
export function initAnalytics(): void {
    if (!window.gtag) {
        return;
    }

    // Inertia sets the new page title asynchronously (via the `title` callback
    // in createInertiaApp), so wait a tick before reading document.title.
    router.on('navigate', () => {
        setTimeout(trackPageView, 0);
    });
}
