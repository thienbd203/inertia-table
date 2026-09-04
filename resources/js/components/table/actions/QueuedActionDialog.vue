<script setup lang="ts">
import { computed, ref, watch } from "vue";
import { CheckCircle2, CircleAlert, Clock3, LoaderCircle } from "@lucide/vue";
import { UiButton } from "@/components/ui/button";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog";
import { useTableContext } from "@/context/tableContext";

const { actions, i18n } = useTableContext();
const open = ref(false);

const status = computed(() => actions.queuedAction.value);
const isTerminal = computed(() =>
    ["completed", "failed", "expired"].includes(status.value?.status ?? ""),
);
const title = computed(() => {
    if (status.value?.status === "completed") return i18n.t("actionCompleted");
    if (status.value?.status === "failed") return i18n.t("actionFailed");
    if (status.value?.status === "expired") return i18n.t("actionExpired");

    return i18n.t("actionQueued");
});
const description = computed(() => {
    const current = status.value;

    if (!current) return "";
    if (current.message) return current.message;
    if (current.status === "completed") {
        return i18n.t("actionCompletedMessage", {
            action: current.label ?? current.action,
            count: current.total ?? current.succeeded ?? 0,
        });
    }
    if (current.status === "failed") return i18n.t("actionFailedMessage");
    if (current.status === "expired") return i18n.t("actionExpiredMessage");

    return i18n.t("actionQueuedMessage", {
        action: current.label ?? current.action,
        count: current.total ?? 0,
    });
});

watch(
    status,
    (current, previous) => {
        if (!current) {
            open.value = false;

            return;
        }

        const currentTerminal = ["completed", "failed", "expired"].includes(
            current.status,
        );
        const previousTerminal = ["completed", "failed", "expired"].includes(
            previous?.status ?? "",
        );

        if (
            !previous ||
            previous.id !== current.id ||
            (!previousTerminal && currentTerminal)
        ) {
            open.value = true;
        }
    },
    { immediate: true },
);

function updateOpen(value: boolean) {
    open.value = value;

    if (!value && isTerminal.value) actions.clearQueuedAction();
}
</script>

<template>
    <Dialog :open="open" @update:open="updateOpen">
        <DialogContent>
            <DialogHeader class="items-center text-center sm:text-center">
                <div
                    class="flex size-12 items-center justify-center rounded-full bg-muted"
                    aria-hidden="true"
                >
                    <CheckCircle2
                        v-if="status?.status === 'completed'"
                        class="size-6 text-emerald-600"
                    />
                    <CircleAlert
                        v-else-if="status?.status === 'failed'"
                        class="size-6 text-destructive"
                    />
                    <Clock3
                        v-else-if="status?.status === 'expired'"
                        class="size-6 text-muted-foreground"
                    />
                    <LoaderCircle
                        v-else
                        class="size-6 animate-spin text-muted-foreground"
                    />
                </div>
                <DialogTitle>{{ title }}</DialogTitle>
                <DialogDescription>{{ description }}</DialogDescription>
                <p
                    v-if="
                        status?.status === 'processing' &&
                        status.processed !== null &&
                        status.processed !== undefined &&
                        status.total !== undefined
                    "
                    class="text-sm text-muted-foreground"
                >
                    {{
                        i18n.t("actionProgress", {
                            processed: status.processed,
                            count: status.total,
                        })
                    }}
                </p>
            </DialogHeader>
            <DialogFooter>
                <UiButton variant="outline" @click="updateOpen(false)">
                    {{ i18n.t("close") }}
                </UiButton>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <Dialog
        :open="actions.actionError.value !== null && !status"
        @update:open="(value) => !value && actions.clearActionError()"
    >
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{ i18n.t("actionFailed") }}</DialogTitle>
                <DialogDescription>
                    {{ actions.actionError.value }}
                </DialogDescription>
            </DialogHeader>
            <DialogFooter>
                <UiButton @click="actions.clearActionError()">
                    {{ i18n.t("close") }}
                </UiButton>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
