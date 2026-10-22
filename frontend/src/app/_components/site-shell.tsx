import { SiteHeader } from "@/features/auth/components/site-header";
import { AuthErrorNotice } from "@/features/auth/components/auth-error-notice";

export function SiteShell({
    children,
}: Readonly<{ children: React.ReactNode }>) {
    return (
        <>
            <SiteHeader />
            <AuthErrorNotice />
            <main>{children}</main>
        </>
    );
}
