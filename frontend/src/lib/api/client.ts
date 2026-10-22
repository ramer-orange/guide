import axios from "axios";
import { normalizeApiError } from "./errors";

const baseURL = process.env.NEXT_PUBLIC_API_BASE_URL || "/api/v1";
export const api = axios.create({
    baseURL,
    withCredentials: true,
    withXSRFToken: true,
    headers: { Accept: "application/json" },
});

let csrfRequest: Promise<void> | null = null;
export async function ensureCsrfCookie() {
    if (!csrfRequest) {
        const origin =
            process.env.NEXT_PUBLIC_APP_ORIGIN ?? window.location.origin;
        csrfRequest = axios
            .get(`${origin}/sanctum/csrf-cookie`, { withCredentials: true })
            .then(() => undefined)
            .finally(() => {
                csrfRequest = null;
            });
    }
    return csrfRequest;
}

api.interceptors.response.use(
    (response) => response,
    (error: unknown) => {
        const normalized = normalizeApiError(error);
        if (normalized.status === 401 || normalized.status === 419) {
            window.dispatchEvent(
                new CustomEvent("plagine:session-expired", {
                    detail: normalized.status,
                }),
            );
        }
        return Promise.reject(error);
    },
);
