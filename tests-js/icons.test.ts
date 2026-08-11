import { h } from "vue";
import { afterEach, describe, expect, it } from "vitest";
import { resolveIcon, setIconResolver } from "../resources/js/icons";
import { topicResource } from "./fixtures";

describe("icon resolver", () => {
    afterEach(() => setIconResolver(null));

    it("uses a local resolver before the global resolver", () => {
        const action = topicResource().actions[0];
        const GlobalIcon = () => h("svg");
        const LocalIcon = () => h("svg");

        setIconResolver(() => GlobalIcon);

        expect(resolveIcon("Trash", action)).toBe(GlobalIcon);
        expect(resolveIcon("Trash", action, () => LocalIcon)).toBe(LocalIcon);
    });

    it("returns null when no resolver knows the icon", () => {
        expect(resolveIcon("Unknown", topicResource().actions[0])).toBeNull();
    });
});
