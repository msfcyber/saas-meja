import { Link, router, usePage } from "@inertiajs/react";
import {
    BarChart3,
    ClipboardList,
    CreditCard,
    LayoutDashboard,
    QrCode,
    Settings2,
    ShieldCheck,
    Soup,
} from "lucide-react";
import AppLogo from "@/components/app-logo";
import { NavMain } from "@/components/nav-main";
import { NavUser } from "@/components/nav-user";
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from "@/components/ui/sidebar";
import { dashboard } from "@/routes";
import type { NavItem } from "@/types";

type PermissionNavItem = NavItem & {
    permission?: string;
};

const mainNavItems: PermissionNavItem[] = [
    {
        title: "Ringkasan",
        href: dashboard(),
        icon: LayoutDashboard,
        permission: "order.view",
    },
    {
        title: "Live order",
        href: "/orders",
        icon: ClipboardList,
        permission: "order.view",
    },
    {
        title: "Produk & menu",
        href: "/products",
        icon: Soup,
        permission: "menu.manage",
    },
    {
        title: "Meja & QR",
        href: "/tables",
        icon: QrCode,
        permission: "table.manage",
    },
    {
        title: "Subscription",
        href: "/subscription",
        icon: CreditCard,
        permission: "subscription.manage",
    },
    {
        title: "Laporan penjualan",
        href: "/reports/sales",
        icon: BarChart3,
        permission: "report.view",
    },
    {
        title: "Pengaturan",
        href: "/settings/profile",
        icon: Settings2,
    },
];

const platformNavItems: NavItem[] = [
    {
        title: "Platform",
        href: "/platform",
        icon: ShieldCheck,
    },
];

export function AppSidebar() {
    const { tenancy } = usePage().props;
    const visibleItems = mainNavItems.filter(
        (item) => item.permission === undefined || tenancy.permissions.includes(item.permission),
    );

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link
                                href={
                                    tenancy.platform_admin && !tenancy.tenant
                                        ? "/platform"
                                        : dashboard()
                                }
                                prefetch
                            >
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                {tenancy.platform_admin && <NavMain items={platformNavItems} label="Platform" />}
                {tenancy.tenant && (
                    <SidebarGroup className="group-data-[collapsible=icon]:hidden">
                        <SidebarGroupLabel>Context aktif</SidebarGroupLabel>
                        <div className="grid gap-2 px-2">
                            {tenancy.tenants.length > 1 && (
                                <label className="grid gap-1.5 text-xs font-medium text-muted-foreground">
                                    Bisnis
                                    <select
                                        value={tenancy.tenant.id}
                                        onChange={(event) =>
                                            router.post(`/context/tenant/${event.target.value}`)
                                        }
                                        className="min-h-9 rounded-lg border bg-background px-2 text-xs font-semibold text-foreground"
                                    >
                                        {tenancy.tenants.map((tenant) => (
                                            <option key={tenant.id} value={tenant.id}>
                                                {tenant.name}
                                            </option>
                                        ))}
                                    </select>
                                </label>
                            )}
                            <label className="grid gap-1.5 text-xs font-medium text-muted-foreground">
                                Outlet
                                <select
                                    value={tenancy.outlet?.id ?? ""}
                                    onChange={(event) =>
                                        router.post(`/context/outlet/${event.target.value}`)
                                    }
                                    className="min-h-9 rounded-lg border bg-background px-2 text-xs font-semibold text-foreground"
                                >
                                    {tenancy.outlets.map((outlet) => (
                                        <option key={outlet.id} value={outlet.id}>
                                            {outlet.name}
                                        </option>
                                    ))}
                                </select>
                            </label>
                        </div>
                    </SidebarGroup>
                )}
                <NavMain items={visibleItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
