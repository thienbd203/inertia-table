<script setup lang="ts">
import { SelectContent, SelectPortal, SelectViewport } from "reka-ui";
</script>

<template>
    <SelectPortal>
        <SelectContent data-slot="select-content" position="popper">
            <SelectViewport><slot /></SelectViewport>
        </SelectContent>
    </SelectPortal>
</template>
