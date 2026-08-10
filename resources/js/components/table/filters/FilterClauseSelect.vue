<script setup lang="ts">
import { Filter } from "@lucide/vue";
import { UiSelect } from "@/components/ui/select";

defineProps<{
    modelValue: string;
    options: Array<{ label: string; value: string }>;
}>();
const emit = defineEmits<{ "update:modelValue": [value: string] }>();
</script>

<template>
    <div class="flex items-center gap-2">
        <div>
            <Filter class="size-5" />
        </div>
        <UiSelect
            :model-value="modelValue"
            :options="options"
            class="w-full"
            @update:model-value="emit('update:modelValue', $event)"
        />
    </div>
</template>
