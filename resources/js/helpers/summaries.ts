export function formatSummaryValue(
    value: unknown,
    format: string | null | undefined,
    locale: string,
): string {
    if (value === null || value === undefined) return "—";
    if (!format) return String(value);

    const number = typeof value === "number" ? value : Number(value);
    if (!Number.isFinite(number)) return String(value);

    const match = format.match(/^(.*?)([#0][#0,]*)(?:\.([#0]+))?(.*?)$/);
    if (!match) return String(value);

    const [, prefix, integer, decimals = "", suffix] = match;
    const minimumFractionDigits = [...decimals].filter(
        (character) => character === "0",
    ).length;
    const formatted = new Intl.NumberFormat(locale, {
        useGrouping: integer.includes(","),
        minimumFractionDigits,
        maximumFractionDigits: decimals.length,
    }).format(number);

    return `${prefix}${formatted}${suffix}`;
}
