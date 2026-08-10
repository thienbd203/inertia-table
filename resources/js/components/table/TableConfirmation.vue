<script setup lang="ts">
import { useTableContext } from "../../context/tableContext";
import { UiButton } from "../ui/button";

const { actions } = useTableContext();
</script>

<template>
    <div class="tb-confirmation-backdrop">
        <div role="alertdialog" aria-modal="true" class="tb-confirmation">
            <h2>
                {{ actions.pendingAction.value?.action.confirmation?.title }}
            </h2>
            <p>
                {{ actions.pendingAction.value?.action.confirmation?.message }}
            </p>
            <div class="tb-confirmation-actions">
                <UiButton variant="outline" @click="actions.cancelAction">
                    {{
                        actions.pendingAction.value?.action.confirmation
                            ?.cancelLabel
                    }}
                </UiButton>
                <UiButton @click="actions.confirmAction">
                    {{
                        actions.pendingAction.value?.action.confirmation
                            ?.confirmLabel
                    }}
                </UiButton>
            </div>
        </div>
    </div>
</template>
