/// <reference types="node" />

import { mkdirSync, writeFileSync } from "node:fs";
import { dirname } from "node:path";
import { describe, expect, it } from "vitest";
import casesFixture from "../tests/Fixtures/url-contract-cases.json";
import {
    contractCursorResource,
    contractResource,
} from "./contracts/resource.generated";
import { tableUrl } from "../resources/js/url";
import type { TableResource, TableState } from "../resources/js/types";

type ContractCase = {
    id: string;
    resource: "full" | "cursor";
    currentUrl: string;
    state: TableState;
    expectedTableParams: [string, string][];
};

const cases = casesFixture.cases as unknown as ContractCase[];
const resources: Record<ContractCase["resource"], TableResource> = {
    full: contractResource,
    cursor: contractCursorResource,
};

describe("frontend URL contract", () => {
    it("serializes the shared cases and writes an optional PHP bridge artifact", () => {
        const urls = cases.map((testCase) => {
            const url = tableUrl(
                testCase.currentUrl,
                resources[testCase.resource],
                testCase.state,
            );
            const parsed = new URL(url, "http://inertia-table.local");
            const prefix = `table[${resources[testCase.resource].name}]`;
            const tableParams = [...parsed.searchParams.entries()].filter(
                ([key]) => key.startsWith(`${prefix}[`),
            );

            expect(tableParams, testCase.id).toEqual(
                testCase.expectedTableParams,
            );

            if (testCase.id === "full-layout-and-filters") {
                expect(parsed.searchParams.get("host")).toBe("kept");
                expect(parsed.searchParams.get("table[authors][page]")).toBe(
                    "3",
                );
                expect(parsed.hash).toBe("#results");
            }

            if (testCase.id === "cursor-replaces-page") {
                expect(parsed.searchParams.get("table[authors][cursor]")).toBe(
                    "other",
                );
                expect(
                    parsed.searchParams.has("table[contract_topics][page]"),
                ).toBe(false);
            }

            return { id: testCase.id, url };
        });

        const output = process.env.INERTIA_TABLE_URL_CONTRACT_OUTPUT;

        if (output) {
            mkdirSync(dirname(output), { recursive: true });
            writeFileSync(
                output,
                `${JSON.stringify({ schemaVersion: 1, cases: urls }, null, 2)}\n`,
            );
        }
    });
});
