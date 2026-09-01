import { Link } from '@inertiajs/react';
import { BrandMark, BrandName } from '@/components/brand-mark';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

export default function AuthSimpleLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    return (
        <div className="paper-grid bg-background relative flex min-h-svh flex-col items-center justify-center overflow-hidden p-5 md:p-10">
            <div className="absolute -top-24 -left-24 size-80 rounded-full bg-[#dba07d]/20 blur-3xl" />
            <div className="absolute -right-32 -bottom-32 size-96 rounded-full bg-[#9cad78]/20 blur-3xl" />
            <div className="bg-card/95 relative w-full max-w-md rounded-[2rem] border p-6 shadow-[0_35px_100px_-55px_rgba(55,42,29,0.7)] backdrop-blur sm:p-9">
                <div className="flex flex-col gap-7">
                    <div className="flex flex-col items-center gap-4">
                        <Link
                            href={home()}
                            className="flex items-center gap-3 font-medium"
                        >
                            <BrandMark />
                            <BrandName />
                            <span className="sr-only">{title}</span>
                        </Link>

                        <div className="space-y-2 text-center">
                            <h1 className="font-display text-2xl font-bold">
                                {title}
                            </h1>
                            <p className="text-muted-foreground text-center text-sm">
                                {description}
                            </p>
                        </div>
                    </div>
                    {children}
                </div>
            </div>
        </div>
    );
}
