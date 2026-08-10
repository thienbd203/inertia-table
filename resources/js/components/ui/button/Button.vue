<script setup lang="ts">
import { Primitive, type PrimitiveProps } from "reka-ui";

withDefaults(
    defineProps<
        PrimitiveProps & {
            variant?: "default" | "outline" | "ghost";
            size?: "default" | "sm";
        }
    >(),
    { as: "button", variant: "default", size: "default" },
);
</script>

<template>
    <Primitive
        data-slot="button"
        :as="as"
        :as-child="asChild"
        :data-variant="variant"
        :data-size="size"
    >
        <slot />
    </Primitive>
</template>
