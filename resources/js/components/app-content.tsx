import * as React from "react";
import { SidebarInset } from "@/components/ui/sidebar";
import type { AppVariant } from "@/types";

type Props = React.ComponentProps<"main"> & {
    variant?: AppVariant;
};

export function AppContent({ variant = "sidebar", children, ...props }: Props) {
    const skipLink = (
        <a
            href="#main-content"
            className="sr-only fixed top-2 left-2 z-[100] rounded-full bg-background px-4 py-2 text-sm font-bold text-foreground shadow focus:not-sr-only focus:outline-none focus:ring-2 focus:ring-ring"
        >
            Lewati ke konten utama
        </a>
    );

    if (variant === "sidebar") {
        return (
            <SidebarInset
                {...props}
                id={props.id ?? "main-content"}
                tabIndex={props.tabIndex ?? -1}
            >
                {skipLink}
                {children}
            </SidebarInset>
        );
    }

    return (
        <main
            className="mx-auto flex h-full w-full max-w-7xl flex-1 flex-col gap-4 rounded-xl"
            {...props}
            id={props.id ?? "main-content"}
            tabIndex={props.tabIndex ?? -1}
        >
            {skipLink}
            {children}
        </main>
    );
}
