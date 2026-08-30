import { Link } from '@inertiajs/react';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn } from '@/lib/utils';
import type { NavItem } from '@/types';

export function NavMain({
    items,
    label = 'Operasional',
}: {
    items: NavItem[];
    label?: string;
}) {
    const { isCurrentUrl } = useCurrentUrl();
    const { isMobile, setOpenMobile } = useSidebar();

    return (
        <nav aria-label={label}>
            <SidebarGroup className="px-3 py-2">
                <SidebarGroupLabel className="text-sidebar-foreground/55 h-7 px-2 text-[10px] font-bold tracking-[0.16em] uppercase">
                    {label}
                </SidebarGroupLabel>
                <SidebarMenu className="gap-1.5">
                    {items.map((item) => {
                        const active = isCurrentUrl(item.href);

                        return (
                            <SidebarMenuItem key={item.title}>
                                <SidebarMenuButton
                                    asChild
                                    isActive={active}
                                    tooltip={{ children: item.title }}
                                    className={cn(
                                        'h-10 rounded-xl px-3 transition-[background-color,color,box-shadow,transform] duration-150',
                                        'hover:bg-sidebar-accent/80 hover:translate-x-0.5',
                                        'data-[active=true]:bg-sidebar-accent data-[active=true]:text-sidebar-accent-foreground data-[active=true]:font-bold data-[active=true]:shadow-[inset_3px_0_0_var(--sidebar-primary)]',
                                        'group-data-[collapsible=icon]:hover:translate-x-0',
                                    )}
                                >
                                    <Link
                                        href={item.href}
                                        prefetch
                                        onClick={() => {
                                            if (isMobile) {
                                                setOpenMobile(false);
                                            }
                                        }}
                                        aria-current={
                                            active ? 'page' : undefined
                                        }
                                    >
                                        {item.icon && (
                                            <item.icon aria-hidden="true" />
                                        )}
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
