import { api } from "@/lib/api/client";
import type { Session } from "@/types/api";
export async function getSession() {
    return (await api.get<Session>("/session")).data;
}
