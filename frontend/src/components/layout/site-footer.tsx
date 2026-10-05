import Image from "next/image";
import Link from "next/link";
export function SiteFooter() {
    return (
        <footer className="border-t border-[#e0e7e2] bg-white">
            <div className="mx-auto grid max-w-7xl gap-10 px-6 py-12 sm:grid-cols-2 lg:grid-cols-4">
                <div className="sm:col-span-2">
                    <Link href="/">
                        <Image
                            src="/images/logo.png"
                            alt="PLAGINE"
                            width={160}
                            height={56}
                            className="h-12 w-auto object-contain"
                        />
                    </Link>
                    <p className="mt-3 max-w-sm text-sm leading-7 text-[#66787a]">
                        旅の計画をもっと簡単に、もっと楽しく。
                        <br />
                        あなたの思い出作りをサポートします。
                    </p>
                </div>
                <div>
                    <h2 className="mb-4 text-xs font-bold tracking-[.18em] text-[#81908e]">
                        SERVICE
                    </h2>
                    <div className="flex flex-col gap-3 text-sm text-[#52696b]">
                        <Link href="/itineraries/create">プラン作成</Link>
                        <a href="/login">Googleでログイン</a>
                        <a href="/login">ログイン</a>
                    </div>
                </div>
                <div>
                    <h2 className="mb-4 text-xs font-bold tracking-[.18em] text-[#81908e]">
                        INFORMATION
                    </h2>
                    <div className="flex flex-col gap-3 text-sm text-[#52696b]">
                        <Link href="/#question">よくある質問</Link>
                        <Link href="/terms">利用規約</Link>
                        <Link href="/policy">プライバシーポリシー</Link>
                    </div>
                </div>
                <p className="border-t border-[#e6ebe7] pt-6 text-center text-xs text-[#9aa6a2] sm:col-span-2 lg:col-span-4">
                    © {new Date().getFullYear()} PLAGINE. All rights reserved.
                </p>
            </div>
        </footer>
    );
}
