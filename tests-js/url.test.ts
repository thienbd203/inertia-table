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
                columns: { name: true, is_featured: false },
                page: 2,
                perPage: 50,
            },
        );
        const parsed = new URL(url, "http://toolbelt.local");

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
    });

    it("omits empty and first-page values", () => {
        const url = tableUrl("/admin", topicResource(), {
            search: "",
            sort: null,
            filters: {},
            columns: { name: true, is_featured: true },
            page: 1,
            perPage: 25,
        });
        const parsed = new URL(url, "http://toolbelt.local");

        expect(parsed.searchParams.has("table[topics][search]")).toBe(false);
        expect(parsed.searchParams.has("table[topics][sort]")).toBe(false);
        expect(parsed.searchParams.has("table[topics][page]")).toBe(false);
        expect(parsed.searchParams.get("table[topics][perPage]")).toBe("25");
    });
});
