import axios from "axios";
import { ensureCsrfCookie } from "@/lib/api/client";
export async function logout() {
    await ensureCsrfCookie();
    await axios.post(
        `${process.env.NEXT_PUBLIC_APP_ORIGIN ?? ""}/logout`,
        {},
        { withCredentials: true, withXSRFToken: true },
    );
}
