<script setup lang="ts">
withDefaults(
    defineProps<{
        variant?: "default" | "outline" | "ghost";
        size?: "default" | "sm";
    }>(),
    { variant: "default", size: "default" },
);
</script>

<template>
    <button data-slot="button" :data-variant="variant" :data-size="size">
        <slot />
    </button>
</template>
