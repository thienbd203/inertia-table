<script setup lang="ts">
defineProps<{ modelValue?: string | number }>();
defineEmits<{ "update:modelValue": [value: string] }>();
</script>

<template>
    <input
        data-slot="input"
        :value="modelValue"
        @input="
            $emit(
                'update:modelValue',
                ($event.target as HTMLInputElement).value,
            )
        "
    />
</template>
