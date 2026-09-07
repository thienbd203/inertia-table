import { describe, expect, it } from "vitest";
import {
    clauseSymbol,
    filterClauseValueKind,
    filterDisplayValue,
    isRangeClause,
    isValuelessClause,
} from "../resources/js/filters";
import type { TableFilter } from "../resources/js/types";

const statusFilter: TableFilter = {
    attribute: "status",
    label: "Status",
    type: "set",
    clauses: ["equals"],
    options: [
        { label: "Active", value: "active" },
        { label: "Inactive", value: "inactive" },
    ],
    meta: {},
};

const customClauseFilter: TableFilter = {
    ...statusFilter,
    clauses: ["equals", "matches_range", "is_blank"],
    clauseValueKinds: {
        matches_range: "range",
        is_blank: "none",
    },
};

describe("filter display", () => {
    it("renders clause symbols and declared option labels", () => {
        expect(clauseSymbol("contains")).toBe("*");
        expect(clauseSymbol("equals")).toBe("=");
        expect(
            filterDisplayValue(statusFilter, {
                enabled: true,
                clause: "equals",
                value: "active",
            }),
        ).toBe("Active");
    });

    it("does not repeat the value for valueless clauses", () => {
        expect(
            filterDisplayValue(statusFilter, {
                enabled: true,
                clause: "is_true",
                value: true,
            }),
        ).toBe("");
    });

    it("shares range and valueless clause semantics", () => {
        expect(isRangeClause("between")).toBe(true);
        expect(isRangeClause("not_between")).toBe(true);
        expect(isRangeClause("equals")).toBe(false);
        expect(isValuelessClause("is_true")).toBe(true);
        expect(isValuelessClause("is_not_set")).toBe(true);
        expect(isValuelessClause("equals")).toBe(false);
    });

    it("uses declared value kinds for custom clauses and falls back for older resources", () => {
        expect(filterClauseValueKind(customClauseFilter, "matches_range")).toBe(
            "range",
        );
        expect(filterClauseValueKind(customClauseFilter, "is_blank")).toBe(
            "none",
        );
        expect(filterClauseValueKind(statusFilter, "is_true")).toBe("none");
    });
});
