import { Link, router, usePage } from '@inertiajs/react';
import {
    BarChart3,
    Building2,
    ClipboardList,
    CreditCard,
    ChevronDown,
    LayoutDashboard,
    MapPin,
    QrCode,
    Settings2,
    ShieldCheck,
    Soup,
    UsersRound,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
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
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

type PermissionNavItem = NavItem & {
    permission?: string;
};

const mainNavItems: PermissionNavItem[] = [
    {
        title: 'Ringkasan',
        href: dashboard(),
        icon: LayoutDashboard,
        permission: 'order.view',
    },
    {
        title: 'Live order',
        href: '/orders',
        icon: ClipboardList,
        permission: 'order.view',
    },
    {
        title: 'Produk & menu',
        href: '/products',
        icon: Soup,
        permission: 'menu.manage',
    },
    {
        title: 'Meja & QR',
        href: '/tables',
        icon: QrCode,
        permission: 'table.manage',
    },
    {
        title: 'Outlet',
        href: '/outlets',
        icon: Building2,
        permission: 'outlet.manage',
    },
    {
        title: 'Staf & akses',
        href: '/staff',
        icon: UsersRound,
        permission: 'staff.manage',
    },
    {
        title: 'Subscription',
        href: '/subscription',
        icon: CreditCard,
        permission: 'subscription.manage',
    },
    {
        title: 'Laporan penjualan',
        href: '/reports/sales',
        icon: BarChart3,
        permission: 'report.view',
    },
    {
        title: 'Pengaturan',
        href: '/settings/profile',
        icon: Settings2,
    },
];

const platformNavItems: NavItem[] = [
    {
        title: 'Platform',
        href: '/platform',
        icon: ShieldCheck,
    },
];

const operationalNavItems = mainNavItems.slice(0, 2);
const managementNavItems = mainNavItems.slice(2);

const contextSelectClassName =
    'h-10 w-full appearance-none rounded-xl border border-sidebar-border/80 bg-background/70 px-3 pr-9 text-xs font-semibold text-foreground shadow-sm outline-hidden transition-colors hover:bg-background focus:border-primary focus-visible:ring-2 focus-visible:ring-ring';

export function AppSidebar() {
    const { tenancy } = usePage().props;
    const canView = (item: PermissionNavItem) =>
        item.permission === undefined ||
        tenancy.permissions.includes(item.permission);
    const visibleOperationalItems = operationalNavItems.filter(canView);
    const visibleManagementItems = managementNavItems.filter(canView);

    const contextLink =
        tenancy.platform_admin && !tenancy.tenant ? '/platform' : dashboard();

    return (
        <Sidebar
            collapsible="icon"
            variant="inset"
            className="[&>[data-sidebar=sidebar]]:border-sidebar-border/70 [&>[data-sidebar=sidebar]]:border [&>[data-sidebar=sidebar]]:shadow-sm"
        >
            <SidebarHeader className="border-sidebar-border/70 border-b px-3 pt-4 pb-3 group-data-[collapsible=icon]:border-b-0">
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            size="lg"
                            asChild
                            className="hover:bg-sidebar-accent/70 h-12 rounded-2xl px-2.5"
                        >
                            <Link href={contextLink} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent className="gap-0">
                {tenancy.platform_admin && (
                    <NavMain items={platformNavItems} label="Platform" />
                )}

                {tenancy.tenant && (
                    <SidebarGroup className="px-3 py-2 group-data-[collapsible=icon]:hidden">
                        <div className="border-sidebar-border/70 bg-background/55 rounded-2xl border p-3 shadow-sm">
                            <div className="flex items-start gap-2.5">
                                <span className="bg-primary/10 text-primary mt-0.5 inline-flex size-8 shrink-0 items-center justify-center rounded-xl">
                                    <MapPin
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                </span>
                                <div className="min-w-0">
                                    <SidebarGroupLabel className="text-sidebar-foreground/55 h-auto px-0 text-[10px] font-bold tracking-[0.16em] uppercase">
                                        Konteks aktif
                                    </SidebarGroupLabel>
                                    <p className="text-sidebar-foreground mt-0.5 truncate text-sm font-bold">
                                        {tenancy.outlet?.name ?? 'Pilih outlet'}
                                    </p>
                                    <p className="text-muted-foreground truncate text-xs">
                                        {tenancy.tenant.name}
                                    </p>
                                </div>
                            </div>

                            <div className="mt-3 grid gap-2">
                                {tenancy.tenants.length > 1 && (
                                    <label className="grid gap-1.5">
                                        <span className="text-muted-foreground px-0.5 text-[10px] font-bold tracking-[0.12em] uppercase">
                                            Bisnis
                                        </span>
                                        <div className="relative">
                                            <select
                                                value={tenancy.tenant.id}
                                                onChange={(event) =>
                                                    router.post(
                                                        `/context/tenant/${event.target.value}`,
                                                    )
                                                }
                                                className={
                                                    contextSelectClassName
                                                }
                                            >
                                                {tenancy.tenants.map(
                                                    (tenant) => (
                                                        <option
                                                            key={tenant.id}
                                                            value={tenant.id}
                                                        >
                                                            {tenant.name}
                                                        </option>
                                                    ),
                                                )}
                                            </select>
                                            <ChevronDown
                                                className="text-muted-foreground pointer-events-none absolute top-1/2 right-3 size-3.5 -translate-y-1/2"
                                                aria-hidden="true"
                                            />
                                        </div>
                                    </label>
                                )}
                                <label className="grid gap-1.5">
                                    <span className="text-muted-foreground px-0.5 text-[10px] font-bold tracking-[0.12em] uppercase">
                                        Outlet
                                    </span>
                                    <div className="relative">
                                        <select
                                            value={tenancy.outlet?.id ?? ''}
                                            onChange={(event) =>
                                                router.post(
                                                    `/context/outlet/${event.target.value}`,
                                                )
                                            }
                                            className={contextSelectClassName}
                                        >
                                            {tenancy.outlets.map((outlet) => (
                                                <option
                                                    key={outlet.id}
                                                    value={outlet.id}
                                                >
                                                    {outlet.name}
                                                </option>
                                            ))}
                                        </select>
                                        <ChevronDown
                                            className="text-muted-foreground pointer-events-none absolute top-1/2 right-3 size-3.5 -translate-y-1/2"
                                            aria-hidden="true"
                                        />
                                    </div>
                                </label>
                            </div>
                        </div>
                    </SidebarGroup>
                )}

                {visibleOperationalItems.length > 0 && (
                    <NavMain
                        items={visibleOperationalItems}
                        label="Operasional"
                    />
                )}
                {visibleManagementItems.length > 0 && (
                    <NavMain items={visibleManagementItems} label="Kelola" />
                )}
            </SidebarContent>

            <SidebarFooter className="border-sidebar-border/70 border-t px-3 py-3">
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
