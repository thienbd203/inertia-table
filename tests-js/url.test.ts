import { describe, expect, it } from "vitest";
import { tableUrl } from "../resources/js/url";
import { topicResource } from "./fixtures";

describe("tableUrl", () => {
    it("replaces only the current table state", () => {
        const resource = topicResource();
        const url = tableUrl(
            "/admin?foo=bar&table%5Bauthors%5D%5Bpage%5D=3&table%5Btopics%5D%5Bsearch%5D=old",
            resource,
            {
                search: "life",
                sort: "-name",
                filters: {
                    status: {
                        enabled: true,
                        clause: "equals",
                        value: "featured",
                    },
                },
                columns: { name: true, is_featured: false, __actions: true },
                pinnedColumns: {
                    left: ["name"],
                    right: ["__actions"],
                },
                columnOrder: ["is_featured", "name", "__actions"],
                columnWidths: { name: 320 },
                page: 2,
                perPage: 50,
            },
        );
        const parsed = new URL(url, "http://inertia-table.local");

        expect(parsed.searchParams.get("foo")).toBe("bar");
        expect(parsed.searchParams.get("table[authors][page]")).toBe("3");
        expect(parsed.searchParams.get("table[topics][search]")).toBe("life");
        expect(parsed.searchParams.get("table[topics][sort]")).toBe("-name");
        expect(
            parsed.searchParams.get("table[topics][filters][status][enabled]"),
        ).toBe("1");
        expect(
            parsed.searchParams.get("table[topics][filters][status][clause]"),
        ).toBe("equals");
        expect(
            parsed.searchParams.get("table[topics][filters][status][value]"),
        ).toBe("featured");
        expect(
            parsed.searchParams.get("table[topics][columns][is_featured]"),
        ).toBe("0");
        expect(parsed.searchParams.get("table[topics][page]")).toBe("2");
        expect(parsed.searchParams.get("table[topics][perPage]")).toBe("50");
        expect(
            parsed.searchParams.getAll("table[topics][pinnedColumns][left][]"),
        ).toEqual(["name"]);
        expect(
            parsed.searchParams.getAll("table[topics][pinnedColumns][right][]"),
        ).toEqual(["__actions"]);
        expect(
            parsed.searchParams.getAll("table[topics][columnOrder][]"),
        ).toEqual(["is_featured", "name", "__actions"]);
        expect(
            parsed.searchParams.get("table[topics][columnWidths][name]"),
        ).toBe("320");
    });

    it("omits empty and first-page values", () => {
        const url = tableUrl("/admin", topicResource(), {
            search: "",
            sort: null,
            filters: {},
            columns: { name: true, is_featured: true, __actions: true },
            page: 1,
            perPage: 25,
        });
        const parsed = new URL(url, "http://inertia-table.local");

        expect(parsed.searchParams.has("table[topics][search]")).toBe(false);
        expect(parsed.searchParams.has("table[topics][sort]")).toBe(false);
        expect(parsed.searchParams.has("table[topics][page]")).toBe(false);
        expect(parsed.searchParams.get("table[topics][perPage]")).toBe("25");
    });

    it("serializes multiple filter values as an array", () => {
        const url = tableUrl("/admin", topicResource(), {
            search: "",
            sort: null,
            filters: {
                status: {
                    enabled: true,
                    clause: "in",
                    value: ["featured", "pending"],
                },
            },
            columns: { name: true, is_featured: true, __actions: true },
            page: 1,
            perPage: 25,
        });
        const parsed = new URL(url, "http://inertia-table.local");

        expect(
            parsed.searchParams.getAll(
                "table[topics][filters][status][value][]",
            ),
        ).toEqual(["featured", "pending"]);
    });

    it("serializes cursor state instead of page state for cursor pagination", () => {
        const resource = topicResource();
        resource.options.paginationType = "cursor";
        const url = tableUrl("/admin?table%5Btopics%5D%5Bpage%5D=9", resource, {
            ...resource.state,
            page: 9,
            cursor: "opaque-token",
        });
        const parsed = new URL(url, "http://inertia-table.local");

        expect(parsed.searchParams.get("table[topics][cursor]")).toBe(
            "opaque-token",
        );
        expect(parsed.searchParams.has("table[topics][page]")).toBe(false);
    });

    it("keeps selected views isolated and serializes explicit disabled state", () => {
        const resource = topicResource();
        const url = tableUrl(
            "/admin?table%5Bauthors%5D%5Bview%5D=4",
            resource,
            {
                ...resource.state,
                view: 7,
                sort: null,
                filters: {
                    status: {
                        enabled: false,
                        clause: "equals",
                        value: null,
                    },
                },
            },
        );
        const parsed = new URL(url, "http://inertia-table.local");

        expect(parsed.searchParams.get("table[authors][view]")).toBe("4");
        expect(parsed.searchParams.get("table[topics][view]")).toBe("7");
        expect(parsed.searchParams.get("table[topics][sort]")).toBe("");
        expect(
            parsed.searchParams.get("table[topics][filters][status][enabled]"),
        ).toBe("0");
        expect(
            parsed.searchParams.getAll("table[topics][pinnedColumns][left][]"),
        ).toEqual([""]);
        expect(
            parsed.searchParams.getAll("table[topics][pinnedColumns][right][]"),
        ).toEqual([""]);
        expect(
            parsed.searchParams.get("table[topics][columnWidths][__reset]"),
        ).toBe("1");
    });
});
