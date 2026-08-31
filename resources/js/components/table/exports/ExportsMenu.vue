<script setup lang="ts">
import { ref, watch } from "vue";
import { Download, LoaderCircle } from "@lucide/vue";
import { UiButton } from "@/components/ui/button";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog";
import {
    UiDropdownMenu,
    UiDropdownMenuContent,
    UiDropdownMenuItem,
    UiDropdownMenuLabel,
    UiDropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { useTableContext } from "@/context/tableContext";

const { resource, actions, exports: tableExports, i18n } = useTableContext();
const queuedDialogOpen = ref(false);

function isTerminal(status: string | undefined): boolean {
    return ["ready", "failed", "expired"].includes(status ?? "");
}

watch(
    () => tableExports.queuedExport.value,
    (status, previous) => {
        if (!status) {
            queuedDialogOpen.value = false;

            return;
        }

        if (
            !previous ||
            previous.id !== status.id ||
            (!isTerminal(previous.status) && isTerminal(status.status))
        ) {
            queuedDialogOpen.value = true;
        }
    },
    { immediate: true },
);

function updateQueuedDialog(open: boolean): void {
    queuedDialogOpen.value = open;

    if (!open && isTerminal(tableExports.queuedExport.value?.status)) {
        tableExports.clearQueuedExport();
    }
}
</script>

<template>
    <UiDropdownMenu v-if="resource.exports?.length">
        <UiDropdownMenuTrigger as-child>
            <UiButton
                variant="outline"
                :disabled="tableExports.isExporting.value"
                :aria-label="i18n.t('exports')"
            >
                <LoaderCircle
                    v-if="tableExports.isExporting.value"
                    class="animate-spin"
                    aria-hidden="true"
                />
                <Download v-else aria-hidden="true" />
                {{ i18n.t("exports") }}
            </UiButton>
        </UiDropdownMenuTrigger>
        <UiDropdownMenuContent align="end" class="min-w-48">
            <UiDropdownMenuLabel>{{
                i18n.t("exportData")
            }}</UiDropdownMenuLabel>
            <UiDropdownMenuItem
                v-for="definition in resource.exports"
                :key="definition.key"
                :disabled="
                    tableExports.isExporting.value ||
                    (definition.requiresSelection &&
                        actions.selectedCount.value === 0)
                "
                @select="tableExports.perform(definition)"
            >
                <Download aria-hidden="true" />
                {{ definition.label }}
            </UiDropdownMenuItem>
        </UiDropdownMenuContent>
    </UiDropdownMenu>

    <Dialog :open="queuedDialogOpen" @update:open="updateQueuedDialog">
        <DialogContent>
            <DialogHeader class="items-center text-center sm:text-center">
                <div
                    v-if="!isTerminal(tableExports.queuedExport.value?.status)"
                    class="flex size-12 items-center justify-center rounded-full bg-muted"
                    aria-hidden="true"
                >
                    <LoaderCircle
                        class="size-6 animate-spin text-muted-foreground"
                    />
                </div>
                <DialogTitle>
                    {{
                        tableExports.queuedExport.value?.status === "ready"
                            ? i18n.t("exportReady")
                            : tableExports.queuedExport.value?.status ===
                                "expired"
                              ? i18n.t("exportExpired")
                              : tableExports.queuedExport.value?.status ===
                                  "failed"
                                ? i18n.t("exportFailed")
                                : i18n.t("exportQueued")
                    }}
                </DialogTitle>
                <DialogDescription>
                    {{
                        tableExports.queuedExport.value?.message ??
                        (tableExports.queuedExport.value?.status === "ready"
                            ? i18n.t("exportReadyMessage")
                            : tableExports.queuedExport.value?.status ===
                                "expired"
                              ? i18n.t("exportExpiredMessage")
                              : i18n.t("exportQueuedMessage"))
                    }}
                </DialogDescription>
            </DialogHeader>
            <DialogFooter class="sm:justify-center">
                <UiButton v-if="tableExports.queuedExport.value?.url" as-child>
                    <a
                        :href="tableExports.queuedExport.value.url"
                        :download="
                            tableExports.queuedExport.value.filename ?? ''
                        "
                    >
                        {{ i18n.t("downloadExport") }}
                    </a>
                </UiButton>
                <UiButton
                    v-else
                    variant="outline"
                    @click="updateQueuedDialog(false)"
                >
                    {{ i18n.t("close") }}
                </UiButton>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <Dialog
        :open="tableExports.error.value !== null"
        @update:open="(open) => !open && tableExports.clearError()"
    >
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{ i18n.t("exportFailed") }}</DialogTitle>
                <DialogDescription>
                    {{ tableExports.error.value }}
                </DialogDescription>
            </DialogHeader>
            <DialogFooter>
                <UiButton @click="tableExports.clearError()">
                    {{ i18n.t("close") }}
                </UiButton>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
