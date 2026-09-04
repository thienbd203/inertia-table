export function createIdempotencyKey(): string {
    if (
        typeof crypto !== "undefined" &&
        typeof crypto.randomUUID === "function"
    ) {
        return crypto.randomUUID();
    }

    return `${Date.now()}-${Math.random().toString(36).slice(2)}`;
}

export function csrfHeaders(): Record<string, string> | null {
    const meta = document
        .querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
        ?.getAttribute("content");

    if (meta) return { "X-CSRF-TOKEN": meta };

    const cookie = document.cookie
        .split("; ")
        .find((value) => value.startsWith("XSRF-TOKEN="))
        ?.slice("XSRF-TOKEN=".length);

    return cookie ? { "X-XSRF-TOKEN": decodeURIComponent(cookie) } : null;
}

export async function responseMessage(
    response: Response,
    fallback: string,
): Promise<string> {
    try {
        const payload = (await response.json()) as {
            message?: string;
            errors?: Record<string, string[]>;
        };
        const validation = Object.values(payload.errors ?? {}).flat()[0];

        return validation ?? payload.message ?? fallback;
    } catch {
        return `${fallback} (HTTP ${response.status}).`;
    }
}
