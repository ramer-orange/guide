import type { ReactNode } from "react";
export function ItinerarySection({
    title,
    eyebrow,
    description,
    action,
    children,
}: {
    title: string;
    eyebrow: string;
    description?: string;
    action?: ReactNode;
    children: ReactNode;
}) {
    return (
        <section className="rounded-[1.6rem] border border-[#e0e8e1] bg-white p-5 shadow-[0_12px_40px_-30px_rgba(34,72,67,.35)] sm:p-7">
            <div className="mb-5 flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p className="mb-1 text-[10px] font-extrabold tracking-[.22em] text-[#54a7a0]">
                        {eyebrow}
                    </p>
                    <h2 className="text-xl font-bold tracking-tight text-[#224349]">
                        {title}
                    </h2>
                    {description && (
                        <p className="mt-1.5 text-sm leading-6 text-[#748482]">
                            {description}
                        </p>
                    )}
                </div>
                {action}
            </div>
            {children}
        </section>
    );
}
