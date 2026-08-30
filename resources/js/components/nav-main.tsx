import { Link } from "@inertiajs/react";
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from "@/components/ui/sidebar";
import { useCurrentUrl } from "@/hooks/use-current-url";
import type { NavItem } from "@/types";

export function NavMain({ items, label = "Operasional" }: { items: NavItem[]; label?: string }) {
    const { isCurrentUrl } = useCurrentUrl();

    return (
        <nav aria-label={label}>
            <SidebarGroup className="px-2 py-0">
                <SidebarGroupLabel>{label}</SidebarGroupLabel>
                <SidebarMenu>
                    {items.map((item) => {
                        const active = isCurrentUrl(item.href);

                        return (
                            <SidebarMenuItem key={item.title}>
                                <SidebarMenuButton
                                    asChild
                                    isActive={active}
                                    tooltip={{ children: item.title }}
                                >
                                    <Link
                                        href={item.href}
                                        prefetch
                                        aria-current={active ? "page" : undefined}
                                    >
                                        {item.icon && <item.icon aria-hidden="true" />}
                                        <span>{item.title}</span>
                                    </Link>
                                </SidebarMenuButton>
                            </SidebarMenuItem>
                        );
                    })}
                </SidebarMenu>
            </SidebarGroup>
        </nav>
    );
}
