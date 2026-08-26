<script setup lang="ts">
import type { DateValue } from "@internationalized/date";
import { getLocalTimeZone, parseDate, today } from "@internationalized/date";
import {
    CalendarCell,
    CalendarCellTrigger,
    CalendarGrid,
    CalendarGridBody,
    CalendarGridHead,
    CalendarGridRow,
    CalendarHeadCell,
    CalendarHeader,
    CalendarHeading,
    CalendarNext,
    CalendarPrev,
    CalendarRoot,
} from "reka-ui";
import { ChevronLeft, ChevronRight } from "@lucide/vue";
import { computed } from "vue";
import { UiButton } from "@/components/ui/button";
import { useTableContext } from "@/context/tableContext";

const props = defineProps<{
    modelValue: string;
}>();
const emit = defineEmits<{ "update:modelValue": [value: string] }>();
const { i18n } = useTableContext();

const date = computed<DateValue | undefined>({
    get: () => {
        if (!/^\d{4}-\d{2}-\d{2}$/.test(props.modelValue)) return undefined;

        try {
            return parseDate(props.modelValue);
        } catch {
            return undefined;
        }
    },
    set: (value) => emit("update:modelValue", value?.toString() ?? ""),
});

function selectToday() {
    date.value = today(getLocalTimeZone());
}

defineExpose({
    focus: () => undefined,
});
</script>

<template>
    <CalendarRoot
        v-slot="{ weekDays, grid }"
        v-model="date"
        :default-placeholder="date ?? today(getLocalTimeZone())"
        :locale="i18n.locale.value"
        initial-focus
        class="rounded-md"
    >
        <CalendarHeader
            class="flex items-center justify-between gap-1 px-1 pb-2"
        >
            <CalendarPrev as-child>
                <UiButton variant="ghost" size="icon-sm">
                    <ChevronLeft class="size-4" />
                </UiButton>
            </CalendarPrev>
            <CalendarHeading class="text-sm font-medium" />
            <CalendarNext as-child>
                <UiButton variant="ghost" size="icon-sm">
                    <ChevronRight class="size-4" />
                </UiButton>
            </CalendarNext>
        </CalendarHeader>

        <CalendarGrid
            v-for="month in grid"
            :key="month.value.toString()"
            class="w-full border-collapse"
        >
            <CalendarGridHead>
                <CalendarGridRow>
                    <CalendarHeadCell
                        v-for="day in weekDays"
                        :key="day"
                        class="h-8 w-8 text-center text-xs font-normal text-muted-foreground"
                    >
                        {{ day }}
                    </CalendarHeadCell>
                </CalendarGridRow>
            </CalendarGridHead>
            <CalendarGridBody>
                <CalendarGridRow
                    v-for="week in month.rows"
                    :key="week[0]?.toString()"
                >
                    <CalendarCell
                        v-for="day in week"
                        :key="day.toString()"
                        :date="day"
                        class="p-0 text-center"
                    >
                        <CalendarCellTrigger
                            :day="day"
                            :month="month.value"
                            class="inline-flex size-8 items-center justify-center rounded-full text-sm outline-none hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring data-[selected]:bg-primary data-[selected]:text-primary-foreground data-[today]:font-semibold data-[outside-view]:text-muted-foreground data-[outside-view]:opacity-50 data-[disabled]:pointer-events-none data-[disabled]:opacity-50"
                        />
                    </CalendarCell>
                </CalendarGridRow>
            </CalendarGridBody>
        </CalendarGrid>

        <div class="pt-2">
            <UiButton variant="outline" size="sm" @click="selectToday">
                {{ i18n.t("today") }}
            </UiButton>
        </div>
    </CalendarRoot>
</template>
