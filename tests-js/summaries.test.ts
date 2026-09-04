import { describe, expect, it } from "vitest";
import { formatSummaryValue } from "../resources/js/helpers/summaries";

describe("summary formatting", () => {
    it("formats grouping, precision, prefixes, and suffixes by locale", () => {
        expect(formatSummaryValue("1234.5", "#,##0.00", "en-US")).toBe(
            "1,234.50",
        );
        expect(formatSummaryValue(1234.5, "#,##0.00", "vi-VN")).toBe(
            "1.234,50",
        );
        expect(formatSummaryValue(12, "$#,##0.0 kg", "en-US")).toBe("$12.0 kg");
    });

    it("preserves raw values without a compatible format", () => {
        expect(formatSummaryValue("12.3400", null, "en-US")).toBe("12.3400");
        expect(formatSummaryValue("not-a-number", "#,##0.00", "en-US")).toBe(
            "not-a-number",
        );
        expect(formatSummaryValue(null, "#,##0.00", "en-US")).toBe("—");
    });
});
