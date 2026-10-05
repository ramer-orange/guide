export function FieldError({ children }: { children?: string }) {
    if (!children) return null;
    return (
        <span role="alert" className="text-xs font-medium text-rose-700">
            {children}
        </span>
    );
}
