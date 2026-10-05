import axios from "axios";

export type ApiFailure = {
    status: number;
    message: string;
    errors: Record<string, string[]>;
};

export function normalizeApiError(error: unknown): ApiFailure {
    if (axios.isAxiosError(error)) {
        const status = error.response?.status ?? 0;
        const data = error.response?.data as
            { message?: string; errors?: Record<string, string[]> } | undefined;
        const message = normalizedMessage(status, data?.message);
        return {
            status,
            message,
            errors: data?.errors ?? {},
        };
    }
    return {
        status: 0,
        message:
            error instanceof Error ? error.message : "処理に失敗しました。",
        errors: {},
    };
}

function normalizedMessage(status: number, serverMessage?: string) {
    if (status === 401)
        return "ログイン状態が切れました。ログインし直してください。";
    if (status === 419)
        return "セッションの有効期限が切れました。ページを再読み込みしてお試しください。";
    if (status === 0)
        return "通信に失敗しました。接続を確認してもう一度お試しください。";
    return serverMessage ?? "処理に失敗しました。";
}
