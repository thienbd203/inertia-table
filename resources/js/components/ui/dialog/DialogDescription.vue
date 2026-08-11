<script setup lang="ts">
import type { DialogDescriptionProps } from "reka-ui";
import type { HTMLAttributes } from "vue";
import { reactiveOmit } from "@vueuse/core";
import { DialogDescription, useForwardProps } from "reka-ui";
import { cn } from "@/lib/utils";

const props = defineProps<
    DialogDescriptionProps & { class?: HTMLAttributes["class"] }
>();
const forwardedProps = useForwardProps(reactiveOmit(props, "class"));
</script>

<template>
    <DialogDescription
        data-slot="dialog-description"
        v-bind="forwardedProps"
        :class="cn('text-muted-foreground text-sm', props.class)"
    >
        <slot />
    </DialogDescription>
</template>
