export type CustomerMenuVariant = {
    id: number;
    name: string;
    price_delta: number;
    is_default?: boolean;
};

export type CustomerMenuModifierOption = {
    id: number;
    name: string;
    price_delta: number;
};

export type CustomerMenuModifier = {
    id: number;
    name: string;
    selection_type: 'single' | 'multiple';
    minimum_selections: number;
    maximum_selections: number;
    is_required: boolean;
    options: CustomerMenuModifierOption[];
};

export type CustomerMenuProduct = {
    id: number;
    name: string;
    category: string;
    description: string | null;
    price: number;
    image: string | null;
    popular?: boolean;
    spicy?: boolean;
    is_available?: boolean;
    variants?: CustomerMenuVariant[];
    modifiers?: CustomerMenuModifier[];
};

export type CustomerCartItem = {
    key: string;
    product_id: number;
    variant_id: number | null;
    modifier_option_ids: number[];
    quantity: number;
    note: string | null;
    product: CustomerMenuProduct;
};

export type CustomerOrderItem = {
    id: number;
    product_name: string;
    variant_name: string | null;
    quantity: number;
    unit_price: number;
    line_total: number;
    note: string | null;
    modifiers: Array<{
        modifier_name: string;
        option_name: string;
        price_delta: number;
    }>;
};

export type CustomerOrder = {
    id: number;
    number: string;
    status: string;
    status_label: string;
    payment_status: string | null;
    payment_method: string | null;
    customer_name: string | null;
    outlet: { name: string; currency: string } | null;
    table: { name: string; code: string } | null;
    subtotal: number;
    discount_amount: number;
    tax_name: string | null;
    tax_rate_basis_points: number;
    tax_inclusive: boolean;
    tax_amount: number;
    fee_amount: number;
    grand_total: number;
    currency: string;
    paid_at: string | null;
    completed_at: string | null;
    created_at: string;
    items: CustomerOrderItem[];
    status_history: Array<{
        from_status: string | null;
        to_status: string;
        to_status_label: string;
        actor_type: string;
        created_at: string;
    }>;
};
