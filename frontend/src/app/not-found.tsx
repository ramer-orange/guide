import Link from "next/link";
export default function NotFound() {
    return (
        <section className="mx-auto flex min-h-[65vh] max-w-3xl flex-col items-center justify-center px-6 text-center">
            <p className="text-xs font-bold tracking-[.22em] text-[#5da59b]">
                PAGE NOT FOUND
            </p>
            <h1 className="mt-3 text-3xl font-extrabold text-[#27494d]">
                ページが見つかりません
            </h1>
            <p className="mt-3 text-sm text-[#73817c]">
                URLをご確認いただくか、トップページからお進みください。
            </p>
            <Link
                href="/"
                className="mt-6 rounded-full bg-[#208b82] px-5 py-3 text-sm font-bold text-white"
            >
                トップページへ
            </Link>
        </section>
    );
}
