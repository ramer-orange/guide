"use client";

import { useNavigationGuard } from "nextjs-nav-guard";

export function useUnsavedChangesGuard(enabled: boolean) {
    useNavigationGuard({
        enabled,
        confirm: () =>
            window.confirm(
                "保存していない変更があります。このページを離れますか？",
            ),
    });
}
