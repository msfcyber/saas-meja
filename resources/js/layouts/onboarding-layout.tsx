import { Link } from "@inertiajs/react";
import { BrandMark, BrandName } from "@/components/brand-mark";
import { home } from "@/routes";

export default function OnboardingLayout({ children }: { children: React.ReactNode }) {
    return (
        <div className="paper-grid relative min-h-svh overflow-hidden bg-background px-4 py-6 sm:px-6 lg:px-10 lg:py-10">
            <div className="absolute top-0 left-1/2 h-72 w-[42rem] -translate-x-1/2 rounded-full bg-primary/10 blur-3xl" />
            <div className="absolute right-0 bottom-0 size-96 translate-x-1/3 translate-y-1/3 rounded-full bg-[#9cad78]/20 blur-3xl" />
            <main className="relative mx-auto flex min-h-[calc(100svh-3rem)] w-full max-w-5xl flex-col">
                <header className="flex items-center justify-between py-2 sm:py-4">
                    <Link href={home()} className="flex items-center gap-3 font-medium">
                        <BrandMark />
                        <BrandName />
                    </Link>
                    <span className="rounded-full border bg-card/70 px-3 py-1.5 text-xs font-medium text-muted-foreground backdrop-blur">
                        Setup awal
                    </span>
                </header>
                <div className="flex flex-1 items-center justify-center py-8 sm:py-12">
                    {children}
                </div>
            </main>
        </div>
    );
}
