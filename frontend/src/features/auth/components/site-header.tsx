"use client";
import Image from "next/image";
import Link from "next/link";
import { Menu, X } from "lucide-react";
import { useState } from "react";
import { useSession } from "@/features/auth/hooks/use-session";
import { useLogout } from "@/features/auth/hooks/use-logout";
export function SiteHeader() {
    const [open, setOpen] = useState(false);
    const { data, isPending, isError, refetch } = useSession();
    const logout = useLogout();
    const accountControl = isError ? (
        <div className="flex items-center gap-2 text-xs text-rose-800">
            <span role="alert">ログイン状態を確認できません</span>
            <button
                type="button"
                onClick={() => void refetch()}
                className="underline underline-offset-2"
            >
                再試行
            </button>
        </div>
    ) : data?.authenticated ? (
        <button
            disabled={logout.isPending}
            onClick={() => logout.mutate()}
            className="rounded-full border border-[#dc9987] px-5 py-2 text-[#c87562] hover:bg-[#fff0eb] disabled:opacity-50"
        >
            ログアウト
        </button>
    ) : isPending || !data ? (
        <span role="status" className="text-xs text-[#73827e]">
            ログイン状態を確認中…
        </span>
    ) : (
        <a
            href="/login"
            className="rounded-full bg-gradient-to-r from-[#53a9ce] to-[#19a598] px-5 py-2.5 text-white shadow-sm hover:brightness-105"
        >
            ログイン
        </a>
    );
    const links = (
        <>
            <Link href="/itineraries/create" onClick={() => setOpen(false)}>
                しおりを作る
            </Link>
            {data?.authenticated && (
                <Link href="/itineraries/index" onClick={() => setOpen(false)}>
                    マイページ
                </Link>
            )}
            <Link href="/terms" onClick={() => setOpen(false)}>
                利用規約
            </Link>
        </>
    );
    return (
        <header className="fixed inset-x-0 top-0 z-40 border-b border-[#d9e5df]/80 bg-[#fffdfa]/90 shadow-sm backdrop-blur-lg">
            <div className="mx-auto flex h-[72px] max-w-7xl items-center justify-between px-5 sm:px-8">
                <Link href="/" aria-label="PLAGINE トップページ">
                    <Image
                        src="/images/logo.png"
                        alt="PLAGINE"
                        width={160}
                        height={56}
                        className="h-10 w-auto object-contain"
                        priority
                    />
                </Link>
                <nav className="hidden items-center gap-8 text-sm font-semibold text-[#40585b] md:flex">
                    {links}
                    {accountControl}
                </nav>
                <button
                    className="rounded-xl p-2 text-[#28545b] hover:bg-[#e8f4ef] md:hidden"
                    aria-label={open ? "メニューを閉じる" : "メニューを開く"}
                    aria-expanded={open}
                    onClick={() => setOpen(!open)}
                >
                    {open ? <X /> : <Menu />}
                </button>
            </div>
            {open && (
                <nav className="flex flex-col gap-5 border-t border-[#d9e5df] bg-[#fffdfa] px-6 py-5 text-sm font-semibold md:hidden">
                    {links}
                    {accountControl}
                </nav>
            )}
            {logout.isError && (
                <p
                    role="alert"
                    className="absolute top-[72px] right-5 rounded-xl border border-rose-200 bg-white px-4 py-3 text-sm text-rose-800 shadow-lg"
                >
                    ログアウトできませんでした。通信状態を確認して、もう一度お試しください。
                </p>
            )}
        </header>
    );
}
