<script setup lang="ts">
import { DropdownMenuContent, DropdownMenuPortal } from "reka-ui";
</script>

<template>
    <DropdownMenuPortal>
        <DropdownMenuContent
            data-slot="dropdown-menu-content"
            align="end"
            :side-offset="4"
        >
            <slot />
        </DropdownMenuContent>
    </DropdownMenuPortal>
</template>
