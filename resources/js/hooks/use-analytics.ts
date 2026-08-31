export type CustomerAnalyticsEvent =
    | 'product_viewed'
    | 'add_to_cart'
    | 'checkout_started';

type TrackOptions = {
    qrToken: string;
    productId?: number;
};

const SESSION_STORAGE_KEY = 'meja.analytics.session';

function analyticsSessionId(): string {
    try {
        const existing = window.localStorage.getItem(SESSION_STORAGE_KEY);

        if (existing) {
            return existing;
        }

        const generated =
            typeof crypto.randomUUID === 'function'
                ? crypto.randomUUID()
                : `${Date.now()}-${Math.random().toString(36).slice(2)}`;
        window.localStorage.setItem(SESSION_STORAGE_KEY, generated);

        return generated;
    } catch {
        return `${Date.now()}-${Math.random().toString(36).slice(2)}`;
    }
}

export function trackAnalytics(
    event: CustomerAnalyticsEvent,
    { qrToken, productId }: TrackOptions,
): void {
    if (typeof window === 'undefined' || !qrToken) {
        return;
    }

    void fetch('/api/analytics/events', {
        method: 'POST',
        credentials: 'same-origin',
        keepalive: true,
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            event,
            qr_token: qrToken,
            session_id: analyticsSessionId(),
            product_id: productId,
        }),
    }).catch(() => undefined);
}
