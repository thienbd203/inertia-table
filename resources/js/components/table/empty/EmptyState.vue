<script setup lang="ts">
import { Link } from "@inertiajs/vue3";
import { computed, type Component } from "vue";
import { Inbox } from "@lucide/vue";
import { UiButton } from "@/components/ui/button";
import { useTableContext } from "@/context/tableContext";
import { resolveIcon } from "@/icons";
import type { TableEmptyState, TableEmptyStateAction } from "@/types";

const { resource, iconResolver } = useTableContext();
const emptyState = computed(() => resource.value.emptyState as TableEmptyState);
const icon = computed(() => {
    if (!emptyState.value.icon) return null;

    return (
        resolveIcon(emptyState.value.icon, emptyState.value, iconResolver) ??
        (emptyState.value.icon === "Inbox" ? Inbox : null)
    );
});

function actionIcon(action: TableEmptyStateAction): Component | null {
    return action.icon ? resolveIcon(action.icon, action, iconResolver) : null;
}

function buttonVariant(action: TableEmptyStateAction) {
    return action.variant === "danger" ? "destructive" : "default";
}
</script>

<template>
    <div
        class="tb-empty-state flex flex-col items-center gap-3 p-8 text-center"
        v-bind="emptyState.dataAttributes"
    >
        <div
            v-if="icon"
            class="tb-empty-state-icon-wrapper rounded-full bg-muted p-3 text-muted-foreground"
        >
            <component :is="icon" class="size-6" aria-hidden="true" />
        </div>
        <div class="space-y-1">
            <h3 class="font-semibold text-foreground">
                {{ emptyState.title }}
            </h3>
            <p v-if="emptyState.message" class="text-sm text-muted-foreground">
                {{ emptyState.message }}
            </p>
        </div>
        <div
            v-if="emptyState.actions.length > 0"
            class="tb-empty-state-actions flex flex-wrap justify-center gap-2"
        >
            <UiButton
                v-for="action in emptyState.actions"
                :key="`${action.label}:${action.url.url}`"
                :variant="buttonVariant(action)"
                :class="action.buttonClass"
                :data-empty-state-variant="action.variant"
                as-child
            >
                <a
                    v-if="action.url.newTab || action.url.download"
                    :href="action.url.url"
                    :target="action.url.newTab ? '_blank' : undefined"
                    :rel="action.url.newTab ? 'noopener' : undefined"
                    :download="action.url.download || undefined"
                    v-bind="action.dataAttributes"
                >
                    <component
                        :is="actionIcon(action)"
                        v-if="actionIcon(action)"
                        aria-hidden="true"
                    />
                    {{ action.label }}
                </a>
                <button
                    v-else-if="action.url.disabled"
                    type="button"
                    disabled
                    v-bind="action.dataAttributes"
                >
                    <component
                        :is="actionIcon(action)"
                        v-if="actionIcon(action)"
                        aria-hidden="true"
                    />
                    {{ action.label }}
                </button>
                <Link
                    v-else
                    :href="action.url.url"
                    :preserve-scroll="action.url.preserveScroll"
                    :preserve-state="action.url.preserveState"
                    v-bind="action.dataAttributes"
                >
                    <component
                        :is="actionIcon(action)"
                        v-if="actionIcon(action)"
                        aria-hidden="true"
                    />
                    {{ action.label }}
                </Link>
            </UiButton>
        </div>
    </div>
</template>
