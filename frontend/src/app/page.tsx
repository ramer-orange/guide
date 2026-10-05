import Image from "next/image";
import Link from "next/link";
import { ArrowRight, CheckSquare2, Map, Share2, Sparkles } from "lucide-react";
const features = [
    {
        icon: Map,
        title: "旅の予定を一冊に",
        text: "日付や時間、場所、写真を並べて、旅の流れをみんなで見渡せます。",
    },
    {
        icon: CheckSquare2,
        title: "準備も買い物も",
        text: "持ち物とお土産をチェックリストに。自分の準備を気軽に進められます。",
    },
    {
        icon: Share2,
        title: "仲間と一緒に",
        text: "メンバーと共同編集。閲覧用リンクならパスワード付きで共有できます。",
    },
];
export default function HomePage() {
    return (
        <>
            <section className="relative isolate min-h-[680px] overflow-hidden bg-[#31535b] pt-[72px] text-white sm:min-h-[760px]">
                <Image
                    src="/images/mv/MV.webp"
                    alt="海と緑に囲まれた旅先"
                    fill
                    priority
                    sizes="100vw"
                    className="-z-20 object-cover object-center"
                />
                <div className="absolute inset-0 -z-10 bg-gradient-to-r from-[#183d48]/85 via-[#1c4650]/55 to-[#31535b]/10" />
                <div className="mx-auto flex min-h-[610px] max-w-7xl items-center px-6 py-20 sm:px-10">
                    <div className="max-w-xl">
                        <p className="mb-5 inline-flex items-center gap-2 rounded-full border border-white/30 bg-white/10 px-4 py-2 text-xs font-semibold tracking-[.16em] backdrop-blur">
                            <Sparkles size={14} /> YOUR TRAVEL NOTE
                        </p>
                        <h1 className="text-4xl leading-[1.28] font-extrabold tracking-wide sm:text-6xl">
                            旅の計画を
                            <br />
                            もっと簡単に、
                            <br />
                            <span className="text-[#b6e8db]">
                                もっと楽しく。
                            </span>
                        </h1>
                        <p className="mt-6 max-w-md text-base leading-8 text-white/85 sm:text-lg">
                            思いのままに、あなただけの旅をデザイン。
                            <br />
                            思い出作りが、これまで以上に楽しくなる。
                        </p>
                        <Link
                            href="/login"
                            className="mt-8 inline-flex items-center gap-3 rounded-full bg-gradient-to-r from-[#55acd0] to-[#21a796] px-7 py-4 font-bold text-white shadow-xl shadow-[#183f4a]/25 transition hover:-translate-y-0.5"
                        >
                            無料で始める
                            <ArrowRight size={18} />
                        </Link>
                    </div>
                </div>
                <div className="absolute bottom-0 left-0 h-16 w-full bg-gradient-to-t from-[#fffdfa] to-transparent" />
            </section>
            <section className="mx-auto max-w-7xl px-6 py-20 sm:px-10 sm:py-28">
                <div className="mx-auto mb-12 max-w-2xl text-center">
                    <p className="text-xs font-extrabold tracking-[.22em] text-[#64aaa0]">
                        TRAVEL, YOUR WAY
                    </p>
                    <h2 className="mt-3 text-3xl font-extrabold text-[#26484d]">
                        旅の準備を、ひとつの場所に。
                    </h2>
                    <p className="mt-4 leading-7 text-[#748581]">
                        計画から持ち物まで、みんなのアイデアをひとつのしおりにまとめましょう。
                    </p>
                </div>
                <div className="grid gap-5 md:grid-cols-3">
                    {features.map(({ icon: Icon, title, text }, index) => (
                        <article
                            key={title}
                            className="relative overflow-hidden rounded-[1.5rem] border border-[#e3ebe4] bg-white p-7 shadow-[0_18px_48px_-38px_rgba(35,80,70,.5)]"
                        >
                            <span className="absolute top-3 right-5 text-5xl font-black text-[#eef5ef]">
                                0{index + 1}
                            </span>
                            <div className="relative grid size-12 place-items-center rounded-2xl bg-[#e8f4ee] text-[#4d998e]">
                                <Icon size={22} />
                            </div>
                            <h3 className="relative mt-5 text-lg font-bold text-[#385756]">
                                {title}
                            </h3>
                            <p className="relative mt-2 text-sm leading-7 text-[#798782]">
                                {text}
                            </p>
                        </article>
                    ))}
                </div>
            </section>
            <section className="paper-grid border-y border-[#e6ede7] py-20 sm:py-24">
                <div className="mx-auto grid max-w-7xl items-center gap-10 px-6 sm:px-10 lg:grid-cols-[1.1fr_.9fr]">
                    <div>
                        <p className="text-xs font-extrabold tracking-[.22em] text-[#5ba8a0]">
                            START WITH THREE STEPS
                        </p>
                        <h2 className="mt-3 text-3xl font-extrabold text-[#26484d]">
                            しおり作りは、かんたん。
                        </h2>
                        <ol className="mt-8 grid gap-5">
                            {[
                                [
                                    "01",
                                    "しおりを作成",
                                    "旅行のタイトルと概要を決めましょう。",
                                ],
                                [
                                    "02",
                                    "予定を追加",
                                    "日付、時間、場所や写真を自由に並べます。",
                                ],
                                [
                                    "03",
                                    "仲間と共有",
                                    "共同編集や閲覧用リンクで計画を共有できます。",
                                ],
                            ].map(([number, title, text]) => (
                                <li key={number} className="flex gap-4">
                                    <span className="grid size-11 shrink-0 place-items-center rounded-full bg-[#258e84] text-sm font-bold text-white">
                                        {number}
                                    </span>
                                    <div>
                                        <h3 className="font-bold text-[#43615d]">
                                            {title}
                                        </h3>
                                        <p className="mt-1 text-sm text-[#7a8883]">
                                            {text}
                                        </p>
                                    </div>
                                </li>
                            ))}
                        </ol>
                    </div>
                    <div className="relative mx-auto w-full max-w-md">
                        <Image
                            src="/images/mockup/mockup.webp"
                            alt="旅のしおりの画面イメージ"
                            width={720}
                            height={560}
                            className="h-auto w-full rounded-3xl shadow-2xl"
                        />
                    </div>
                </div>
            </section>
            <section
                id="question"
                className="mx-auto max-w-4xl px-6 py-20 sm:py-24"
            >
                <p className="text-center text-xs font-extrabold tracking-[.22em] text-[#5ba8a0]">
                    GOOD TO KNOW
                </p>
                <h2 className="mt-3 text-center text-3xl font-extrabold text-[#26484d]">
                    よくある質問
                </h2>
                <div className="mt-9 divide-y divide-[#e5ebe6]">
                    {[
                        [
                            "しおりは誰と共有できますか？",
                            "登録済みのメンバーを編集者として招待できます。閲覧用リンクはパスワードを設定して共有できます。",
                        ],
                        [
                            "持ち物リストはメンバー全員に見えますか？",
                            "持ち物リストは利用者ごとに管理されます。旅程、お土産、メモはしおりのメンバーで共有されます。",
                        ],
                        [
                            "添付できるファイルは？",
                            "JPEG、PNG、PDF、Wordファイルを添付できます。1ファイルあたり10MBまでです。",
                        ],
                    ].map(([question, answer]) => (
                        <details key={question} className="group py-5">
                            <summary className="cursor-pointer list-none font-bold text-[#496461] marker:hidden">
                                {question}
                                <span className="float-right text-[#64a79e] transition group-open:rotate-45">
                                    ＋
                                </span>
                            </summary>
                            <p className="mt-3 text-sm leading-7 text-[#7a8883]">
                                {answer}
                            </p>
                        </details>
                    ))}
                </div>
                <div className="mt-12 rounded-[1.7rem] bg-gradient-to-r from-[#4ba8c8] to-[#23a294] px-7 py-9 text-center text-white sm:px-10">
                    <h2 className="text-2xl font-extrabold">
                        次の旅を、ここから。
                    </h2>
                    <p className="mt-2 text-sm text-white/85">
                        あなたらしい旅のしおりを作ってみませんか？
                    </p>
                    <Link
                        href="/login"
                        className="mt-5 inline-flex items-center gap-2 rounded-full bg-white px-6 py-3 text-sm font-bold text-[#278d87]"
                    >
                        無料で始める
                        <ArrowRight size={16} />
                    </Link>
                </div>
            </section>
        </>
    );
}
