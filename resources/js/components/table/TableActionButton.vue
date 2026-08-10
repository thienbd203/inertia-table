<script setup lang="ts">
import { computed } from "vue";
import { UiButton } from "../ui/button";
import { useTableContext } from "../../context/tableContext";
import { resolveIcon } from "../../icons";
import type { TableAction, TableItem } from "../../types";

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
        (props.bulk && actions.selectedItems.value.length === 0),
);
</script>

<template>
    <UiButton
        :variant="
            action.variant === 'destructive'
                ? 'outline'
                : bulk
                  ? 'default'
                  : 'ghost'
        "
        :size="bulk ? 'default' : 'sm'"
        :disabled="disabled"
        :title="action.tooltip ?? undefined"
        :aria-label="action.labelHidden ? action.label : undefined"
        @click="actions.performAction(action, item)"
    >
        <component
            :is="icon"
            v-if="action.icon"
            class="tb-action-icon"
            aria-hidden="true"
        />
        <span v-if="!action.labelHidden">{{ action.label }}</span>
    </UiButton>
</template>
