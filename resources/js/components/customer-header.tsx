import { Link } from "@inertiajs/react";
import { ChevronDown, MapPin } from "lucide-react";
import { BrandMark, BrandName } from "@/components/brand-mark";

export function CustomerHeader({ minimal = false }: { minimal?: boolean }) {
    return (
        <header className="sticky top-0 z-40 border-b border-border/70 bg-background/92 backdrop-blur-xl">
            <div className="mx-auto flex h-16 max-w-6xl items-center justify-between px-4 sm:px-6">
                <Link href={minimal ? "/" : "/demo/menu"} className="flex items-center gap-2.5">
                    <BrandMark className="size-8 rounded-lg shadow-none" />
                    <BrandName compact />
                </Link>
                {!minimal && (
                    <button
                        type="button"
                        className="flex min-h-11 items-center gap-2 rounded-full border bg-card px-3.5 text-left transition-colors hover:bg-secondary"
                    >
                        <MapPin className="size-4 text-primary" aria-hidden="true" />
                        <span>
                            <span className="block text-[10px] leading-none font-bold tracking-wider text-muted-foreground uppercase">
                                Kedai Sore
                            </span>
                            <span className="mt-1 block text-xs leading-none font-bold">
                                Meja 08
                            </span>
                        </span>
                        <ChevronDown
                            className="size-3.5 text-muted-foreground"
                            aria-hidden="true"
                        />
                    </button>
                )}
            </div>
        </header>
    );
}
