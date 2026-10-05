import type { ButtonHTMLAttributes, ReactNode } from "react";
import { cn } from "@/lib/utils";
type Props = ButtonHTMLAttributes<HTMLButtonElement> & { children: ReactNode };
export function Button({
    className,
    children,
    type = "button",
    ...props
}: Props) {
    return (
        <button
            type={type}
            className={cn(
                "inline-flex min-h-11 items-center justify-center gap-2 rounded-full bg-[#208b82] px-5 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-[#16786f] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#168c88] disabled:cursor-wait disabled:opacity-55",
                className,
            )}
            {...props}
        >
            {children}
        </button>
    );
}
