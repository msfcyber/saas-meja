import type { CustomerCartItem, CustomerMenuProduct } from "@/types/customer";

const storagePrefix = "meja:customer-cart:";

export const cartStorageKey = (qrToken: string) => `${storagePrefix}${qrToken}`;

export const cartItemKey = (
    productId: number,
    variantId: number | null,
    modifierOptionIds: number[],
    note: string | null,
) =>
    [
        productId,
        variantId ?? "base",
        [...modifierOptionIds].sort((a, b) => a - b).join(","),
        note ?? "",
    ].join(":");

export function loadCustomerCart(qrToken: string): CustomerCartItem[] {
    if (typeof window === "undefined") {
        return [];
    }

    try {
        const stored = window.localStorage.getItem(cartStorageKey(qrToken));
        const parsed: unknown = stored ? JSON.parse(stored) : [];

        return Array.isArray(parsed) ? parsed.filter(isCartItem) : [];
    } catch {
        return [];
    }
}

export function saveCustomerCart(qrToken: string, cart: CustomerCartItem[]): void {
    if (typeof window === "undefined") {
        return;
    }

    window.localStorage.setItem(cartStorageKey(qrToken), JSON.stringify(cart));
}

export function clearCustomerCart(qrToken: string): void {
    if (typeof window === "undefined") {
        return;
    }

    window.localStorage.removeItem(cartStorageKey(qrToken));
}

export function itemUnitPrice(item: CustomerCartItem): number {
    const variant = item.product.variants?.find((entry) => entry.id === item.variant_id);
    const options = item.product.modifiers?.flatMap((modifier) => modifier.options) ?? [];
    const modifierAmount = item.modifier_option_ids.reduce(
        (total, optionId) =>
            total + (options.find((option) => option.id === optionId)?.price_delta ?? 0),
        0,
    );

    return item.product.price + (variant?.price_delta ?? 0) + modifierAmount;
}

function isCartItem(value: unknown): value is CustomerCartItem {
    if (typeof value !== "object" || value === null) {
        return false;
    }

    const item = value as Partial<CustomerCartItem>;

    return (
        typeof item.key === "string" &&
        typeof item.product_id === "number" &&
        (typeof item.variant_id === "number" || item.variant_id === null) &&
        Array.isArray(item.modifier_option_ids) &&
        typeof item.quantity === "number" &&
        item.quantity > 0 &&
        typeof item.product === "object" &&
        item.product !== null
    );
}

export function replaceCartProduct(
    cart: CustomerCartItem[],
    product: CustomerMenuProduct,
): CustomerCartItem[] {
    return cart.map((item) => (item.product_id === product.id ? { ...item, product } : item));
}
