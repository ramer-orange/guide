import type { ReactNode } from "react";
export function EmptyState({
    title,
    description,
    action,
}: {
    title: string;
    description?: string;
    action?: ReactNode;
}) {
    return (
        <div className="rounded-2xl border border-dashed border-[#d5e2da] bg-[#fcfdfb] px-6 py-8 text-center">
            <p className="font-semibold text-[#536b66]">{title}</p>
            {description && (
                <p className="mt-1 text-sm text-[#83908b]">{description}</p>
            )}
            {action && <div className="mt-4">{action}</div>}
        </div>
    );
}
