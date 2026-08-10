<script setup lang="ts">
import { PopoverContent, PopoverPortal } from "reka-ui";
</script>

<template>
    <PopoverPortal>
        <PopoverContent
            data-slot="popover-content"
            align="start"
            :side-offset="4"
        >
            <slot />
        </PopoverContent>
    </PopoverPortal>
</template>
