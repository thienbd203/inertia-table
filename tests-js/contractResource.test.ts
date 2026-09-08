import { mount } from "@vue/test-utils";
import { beforeEach, describe, expect, it } from "vitest";
import {
    contractCursorResource,
    contractResource,
    contractSimpleResource,
    contractUnpaginatedResource,
} from "./contracts/resource.generated";
import { resetInertiaMock } from "./inertiaMock";

import DataTable from "../resources/js/DataTable.vue";

describe("generated PHP resource contract", () => {
    beforeEach(() => resetInertiaMock("/contract-topics"));

    it("type-checks and renders the PHP resource across pagination modes", () => {
        const wrapper = mount(DataTable, {
            props: { resource: contractResource },
            attachTo: document.body,
        });

        expect(wrapper.get('td[data-column="name"]').text()).toBe("Alpha");
        expect(
            wrapper
                .get('td[data-column="status"] .tb-badge')
                .attributes("data-style"),
        ).toBe("success");
        expect(wrapper.get('tfoot td[data-column="amount"]').text()).toBe("30");
        expect(contractResource.capabilities.hasExports).toBe(true);
        expect(contractResource.views?.selected).toBe(1);
        expect(contractSimpleResource.results.total).toBeNull();
        expect(contractCursorResource.results.currentPage).toBeNull();
        expect(contractCursorResource.results.nextCursor).not.toBeNull();
        expect(contractUnpaginatedResource.capabilities.paginated).toBe(false);
        expect(contractUnpaginatedResource.results.lastPage).toBe(1);
    });
});
