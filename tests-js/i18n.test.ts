import { computed } from "vue";
import { describe, expect, it } from "vitest";
import { createTableI18n, en, vi } from "../resources/js/i18n";

describe("table i18n", () => {
    it("uses English defaults and interpolates parameters", () => {
        const i18n = createTableI18n(
            computed(() => "en-US"),
            computed(() => ({})),
        );

        expect(i18n.t("searchPlaceholder")).toBe(en.searchPlaceholder);
        expect(i18n.t("pageOf", { page: 2, pages: 5 })).toBe("Page 2 of 5");
    });

    it("inherits missing messages and overrides selected messages", () => {
        const fallback = createTableI18n(
            computed(() => "vi-VN"),
            computed(() => vi),
        );
        const i18n = createTableI18n(
            computed(() => fallback.locale.value),
            computed(() => ({ noResults: "Chưa có dữ liệu." })),
            fallback,
        );

        expect(i18n.locale.value).toBe("vi-VN");
        expect(i18n.t("noResults")).toBe("Chưa có dữ liệu.");
        expect(i18n.t("filters")).toBe("Bộ lọc");
    });
});
