import { Link } from "@inertiajs/react";
import { MapPin } from "lucide-react";
import { BrandMark, BrandName } from "@/components/brand-mark";

export function CustomerHeader({
    minimal = false,
    outletName = "Kedai Sore",
    tableName = "Meja 08",
    homeHref = "/demo/menu",
}: {
    minimal?: boolean;
    outletName?: string;
    tableName?: string;
    homeHref?: string;
}) {
    return (
        <header className="sticky top-0 z-40 border-b border-border/70 bg-background/92 backdrop-blur-xl">
            <div className="mx-auto flex h-16 max-w-6xl items-center justify-between px-4 sm:px-6">
                <Link href={minimal ? "/" : homeHref} className="flex items-center gap-2.5">
                    <BrandMark className="size-8 rounded-lg shadow-none" />
                    <BrandName compact />
                </Link>
                {!minimal && (
                    <div className="flex min-h-11 items-center gap-2 rounded-full border bg-card px-3.5 text-left">
                        <MapPin className="size-4 text-primary" aria-hidden="true" />
                        <span>
                            <span className="block text-[10px] leading-none font-bold tracking-wider text-muted-foreground uppercase">
                                {outletName}
                            </span>
                            <span className="mt-1 block text-xs leading-none font-bold">
                                {tableName}
                            </span>
                        </span>
                    </div>
                )}
            </div>
        </header>
    );
}
