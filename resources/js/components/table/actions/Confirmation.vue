<script setup lang="ts">
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog";
import { LoaderCircle } from "@lucide/vue";
import { UiButton } from "@/components/ui/button";
import { useTableContext } from "@/context/tableContext";

const { actions } = useTableContext();

function updateOpen(open: boolean) {
    if (!open && !actions.isPerformingAction.value) actions.cancelAction();
}
</script>

<template>
    <Dialog
        :open="Boolean(actions.pendingAction.value)"
        @update:open="updateOpen"
    >
        <DialogContent>
            <DialogHeader>
                <DialogTitle>
                    {{
                        actions.pendingAction.value?.action.confirmation?.title
                    }}
                </DialogTitle>
                <DialogDescription>
                    {{
                        actions.pendingAction.value?.action.confirmation
                            ?.message
                    }}
                </DialogDescription>
            </DialogHeader>
            <DialogFooter>
                <UiButton
                    variant="outline"
                    :disabled="actions.isPerformingAction.value"
                    @click="actions.cancelAction"
                >
                    {{
                        actions.pendingAction.value?.action.confirmation
                            ?.cancelLabel
                    }}
                </UiButton>
                <UiButton
                    :variant="
                        actions.pendingAction.value?.action.variant ?? 'default'
                    "
                    :disabled="actions.isPerformingAction.value"
                    @click="actions.confirmAction"
                >
                    <LoaderCircle
                        v-if="actions.isPerformingAction.value"
                        class="size-4 animate-spin"
                    />
                    {{
                        actions.pendingAction.value?.action.confirmation
                            ?.confirmLabel
                    }}
                </UiButton>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
