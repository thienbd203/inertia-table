<script setup lang="ts">
import { getLocalTimeZone, parseDate, today } from "@internationalized/date";
import {
    RangeCalendarCell,
    RangeCalendarCellTrigger,
    RangeCalendarGrid,
    RangeCalendarGridBody,
    RangeCalendarGridHead,
    RangeCalendarGridRow,
    RangeCalendarHeadCell,
    RangeCalendarHeader,
    RangeCalendarHeading,
    RangeCalendarNext,
    RangeCalendarPrev,
    RangeCalendarRoot,
} from "reka-ui";
import { ChevronLeft, ChevronRight } from "@lucide/vue";
import { computed, ref, watch } from "vue";
import { UiButton } from "@/components/ui/button";

const props = defineProps<{
    modelValue: [string, string];
}>();
const emit = defineEmits<{
    "update:modelValue": [value: [string, string]];
}>();

function parse(value: string) {
    if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) return undefined;

    try {
        return parseDate(value);
    } catch {
        return undefined;
    }
}

// Reka UI's DateRange type comes from its nested date dependency. Keep the
// local model structural so it remains compatible when consumers dedupe that
// dependency differently.
const selectedRange = ref<{
    start?: ReturnType<typeof parseDate>;
    end?: ReturnType<typeof parseDate>;
}>();

watch(
    () => props.modelValue,
    ([start, end]) => {
        selectedRange.value = {
            start: parse(start),
            end: parse(end),
        };
    },
    { immediate: true },
);

const placeholder = computed(
    () => selectedRange.value?.start ?? today(getLocalTimeZone()),
);

function updateRange(value: unknown) {
    const range = value as
        | {
              start?: { toString(): string };
              end?: { toString(): string };
          }
        | undefined;

    selectedRange.value = range as typeof selectedRange.value;

    const start = range?.start?.toString() ?? "";
    const end = range?.end?.toString() ?? "";
    emit("update:modelValue", [start, end]);
}

function selectToday() {
    const value = today(getLocalTimeZone());
    updateRange({ start: value, end: value });
}

defineExpose({
    focus: () => undefined,
});
</script>

<template>
    <RangeCalendarRoot
        v-slot="{ weekDays, grid }"
        :model-value="selectedRange as never"
        :default-placeholder="placeholder"
        :number-of-months="2"
        locale="vi-VN"
        initial-focus
        @update:model-value="updateRange"
    >
        <RangeCalendarHeader
            class="flex items-center justify-between gap-1 px-1 pb-2"
        >
            <RangeCalendarPrev as-child>
                <UiButton variant="ghost" size="icon-sm">
                    <ChevronLeft class="size-4" />
                </UiButton>
            </RangeCalendarPrev>
            <RangeCalendarHeading class="text-sm font-medium" />
            <RangeCalendarNext as-child>
                <UiButton variant="ghost" size="icon-sm">
                    <ChevronRight class="size-4" />
                </UiButton>
            </RangeCalendarNext>
        </RangeCalendarHeader>

        <div class="flex gap-4">
            <RangeCalendarGrid
                v-for="month in grid"
                :key="month.value.toString()"
                class="w-full border-collapse"
            >
                <RangeCalendarGridHead>
                    <RangeCalendarGridRow>
                        <RangeCalendarHeadCell
                            v-for="day in weekDays"
                            :key="day"
                            class="h-8 w-8 text-center text-xs font-normal text-muted-foreground"
                        >
                            {{ day }}
                        </RangeCalendarHeadCell>
                    </RangeCalendarGridRow>
                </RangeCalendarGridHead>
                <RangeCalendarGridBody>
                    <RangeCalendarGridRow
                        v-for="week in month.rows"
                        :key="week[0]?.toString()"
                    >
                        <RangeCalendarCell
                            v-for="day in week"
                            :key="day.toString()"
                            :date="day"
                            class="p-0 text-center"
                        >
                            <RangeCalendarCellTrigger
                                :day="day"
                                :month="month.value"
                                class="inline-flex size-8 items-center justify-center rounded-full text-sm outline-none hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring data-[selected]:bg-primary data-[selected]:text-primary-foreground data-[highlighted]:bg-accent data-[today]:font-semibold data-[outside-view]:text-muted-foreground data-[outside-view]:opacity-50 data-[disabled]:pointer-events-none data-[disabled]:opacity-50"
                            />
                        </RangeCalendarCell>
                    </RangeCalendarGridRow>
                </RangeCalendarGridBody>
            </RangeCalendarGrid>
        </div>

        <div class="pt-2">
            <UiButton variant="outline" size="sm" @click="selectToday">
                Today
            </UiButton>
        </div>
    </RangeCalendarRoot>
</template>
