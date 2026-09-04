export type CustomerAnalyticsEvent =
    | 'product_viewed'
    | 'add_to_cart'
    | 'checkout_started';

type TrackOptions = {
    qrToken: string;
    analyticsToken: string;
    productId?: number;
};

export function trackAnalytics(
    event: CustomerAnalyticsEvent,
    { qrToken, analyticsToken, productId }: TrackOptions,
): void {
    if (typeof window === 'undefined' || !qrToken || !analyticsToken) {
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
            analytics_token: analyticsToken,
            product_id: productId,
        }),
    }).catch(() => undefined);
}
