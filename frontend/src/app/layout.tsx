import type { Metadata } from "next";
import "./globals.css";
import { Providers } from "./providers";
import { SiteHeader } from "@/features/auth/components/site-header";
import { SiteFooter } from "@/components/layout/site-footer";
import { AuthErrorNotice } from "@/features/auth/components/auth-error-notice";

const configuredSiteUrl =
    process.env.NEXT_PUBLIC_SITE_URL ?? "http://localhost:8081";
const siteUrl = new URL(configuredSiteUrl);
if (
    !["http:", "https:"].includes(siteUrl.protocol) ||
    siteUrl.username ||
    siteUrl.password ||
    siteUrl.pathname !== "/" ||
    siteUrl.search ||
    siteUrl.hash
) {
    throw new Error(
        "NEXT_PUBLIC_SITE_URL must be an HTTP(S) origin without a path.",
    );
}

export const metadata: Metadata = {
    metadataBase: siteUrl,
    title: { default: "PLAGINE｜旅のしおり", template: "%s - PLAGINE" },
    description:
        "旅の計画をもっと簡単に、もっと楽しく。思いのままに、あなただけの旅をデザイン。",
    icons: {
        icon: "/images/favicon.ico",
        apple: "/images/apple-touch-icon.png",
    },
    openGraph: { type: "website", images: ["/images/ogp.webp"] },
};

export default function RootLayout({
    children,
}: Readonly<{ children: React.ReactNode }>) {
    return (
        <html lang="ja">
            <body>
                <Providers>
                    <SiteHeader />
                    <AuthErrorNotice />
                    <main>{children}</main>
                    <SiteFooter />
                </Providers>
            </body>
        </html>
    );
}
