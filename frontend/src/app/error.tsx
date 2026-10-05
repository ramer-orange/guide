"use client";
import { useEffect } from "react";
export default function ErrorPage({
    error,
    reset,
}: {
    error: Error & { digest?: string };
    reset: () => void;
}) {
    useEffect(() => {
        console.error(error);
    }, [error]);
    return (
        <section className="mx-auto flex min-h-[65vh] max-w-3xl flex-col items-center justify-center px-6 text-center">
            <p className="text-xs font-bold tracking-[.22em] text-[#cf8874]">
                SOMETHING WENT WRONG
            </p>
            <h1 className="mt-3 text-3xl font-extrabold text-[#27494d]">
                ページを表示できませんでした
            </h1>
            <p className="mt-3 text-sm text-[#73817c]">
                通信状態を確認して、もう一度お試しください。
            </p>
            <button
                type="button"
                onClick={() => reset()}
                className="mt-6 rounded-full bg-[#208b82] px-5 py-3 text-sm font-bold text-white"
            >
                再読み込み
            </button>
        </section>
    );
}
