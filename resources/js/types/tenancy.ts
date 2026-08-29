export type TenantOption = {
    id: number;
    name: string;
    slug: string;
};

export type OutletOption = {
    id: number;
    name: string;
    code: string;
};

export type TenancyContext = {
    tenant: TenantOption | null;
    outlet: OutletOption | null;
    tenants: TenantOption[];
    outlets: OutletOption[];
    roles: string[];
    permissions: string[];
};
