import { Utensils } from "lucide-react";
import { cn } from "@/lib/utils";

export function BrandMark({ className }: { className?: string }) {
    return (
        <span
            className={cn(
                "inline-flex size-9 items-center justify-center rounded-[0.85rem] bg-primary text-primary-foreground shadow-[0_8px_24px_-12px_var(--primary)]",
                className,
            )}
        >
            <Utensils className="size-4.5" aria-hidden="true" />
        </span>
    );
}

export function BrandName({ compact = false }: { compact?: boolean }) {
    return (
        <span className="flex items-baseline gap-1.5">
            <span className="font-display text-xl font-bold tracking-[-0.04em]">meja</span>
            {!compact && (
                <span className="text-[10px] font-bold tracking-[0.2em] text-primary uppercase">
                    order
                </span>
            )}
        </span>
    );
}
