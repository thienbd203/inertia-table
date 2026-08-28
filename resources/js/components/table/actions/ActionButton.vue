<script setup lang="ts">
import { computed } from "vue";
import { UiButton } from "@/components/ui/button";
import {
    UiTooltip,
    UiTooltipContent,
    UiTooltipProvider,
    UiTooltipTrigger,
} from "@/components/ui/tooltip";
import { useTableContext } from "@/context/tableContext";
import { resolveIcon } from "@/icons";
import type { TableAction, TableItem } from "@/types";

const props = withDefaults(
    defineProps<{
        action: TableAction;
        item?: TableItem;
        bulk?: boolean;
    }>(),
    { bulk: false },
);

const { actions, iconResolver } = useTableContext();
const icon = computed(() =>
    props.action.icon
        ? resolveIcon(props.action.icon, props.action, iconResolver)
        : null,
);
const disabled = computed(
    () =>
        actions.isPerformingAction.value ||
        props.action.disabled ||
        (props.bulk && actions.selectedCount.value === 0),
);
const tooltip = computed(
    () =>
        (props.action.disabled
            ? props.action.disabledTooltip
            : props.action.tooltip) ?? null,
);
</script>

<template>
    <UiTooltipProvider>
        <UiTooltip>
            <UiTooltipTrigger as-child>
                <span class="inline-flex">
                    <UiButton
                        :variant="
                            action.variant === 'destructive'
                                ? action.labelHidden
                                    ? 'ghost'
                                    : 'destructive'
                                : bulk
                                  ? 'default'
                                  : 'ghost'
                        "
                        :size="bulk ? 'default' : 'sm'"
                        :class="[
                            action.buttonClass,
                            disabled ? 'pointer-events-none' : undefined,
                            action.variant === 'destructive' &&
                            action.labelHidden
                                ? 'text-destructive hover:bg-destructive/10 hover:text-destructive'
                                : undefined,
                        ]"
                        :disabled="disabled"
                        :aria-label="
                            action.labelHidden ? action.label : undefined
                        "
                        @click="actions.performAction(action, item)"
                    >
                        <component
                            :is="icon"
                            v-if="action.icon"
                            class="tb-action-icon"
                            aria-hidden="true"
                        />

                        <span v-if="!action.labelHidden">
                            {{ action.label }}
                        </span>
                    </UiButton>
                </span>
            </UiTooltipTrigger>

            <UiTooltipContent v-if="tooltip" class="pointer-events-none">
                {{ tooltip }}
            </UiTooltipContent>
        </UiTooltip>
    </UiTooltipProvider>
</template>
