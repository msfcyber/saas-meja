import { BrandMark, BrandName } from "@/components/brand-mark";

export default function AppLogo() {
    return (
        <>
            <BrandMark className="size-8 rounded-lg shadow-none" />
            <div className="ml-1 grid flex-1 text-left text-sm">
                <BrandName />
            </div>
        </>
    );
}
