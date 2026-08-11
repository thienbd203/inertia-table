<script setup lang="ts">
import { computed } from "vue";
import { UiDropdownMenuItem } from "@/components/ui/dropdown-menu";
import { useTableContext } from "@/context/tableContext";
import { resolveIcon } from "@/icons";
import type { TableAction } from "@/types";

const props = defineProps<{ action: TableAction }>();
const { actions, iconResolver } = useTableContext();
const icon = computed(() =>
    props.action.icon
        ? resolveIcon(props.action.icon, props.action, iconResolver)
        : null,
);
</script>

<template>
    <UiDropdownMenuItem
        :disabled="
            actions.selectedItems.value.length === 0 ||
            action.disabled ||
            actions.isPerformingAction.value
        "
        :title="
            (action.disabled ? action.disabledTooltip : action.tooltip) ??
            undefined
        "
        :class="[
            action.buttonClass,
            action.variant === 'destructive'
                ? 'text-destructive focus:text-destructive'
                : undefined,
        ]"
        @select="actions.performAction(action)"
    >
        <component
            :is="icon"
            v-if="action.icon"
            class="tb-action-icon"
            aria-hidden="true"
        />
        <span>{{ action.label }}</span>
    </UiDropdownMenuItem>
</template>
