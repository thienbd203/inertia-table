<script setup lang="ts">
import {
    Bookmark,
    Check,
    Circle,
    Pencil,
    RefreshCcw,
    Save,
    Star,
    Trash2,
    Users,
} from "@lucide/vue";
import { ref } from "vue";
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
    UiDropdownMenuSeparator,
    UiDropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { UiInput } from "@/components/ui/input";
import { useTableContext } from "@/context/tableContext";
import type { TableView } from "@/types";

const { views, i18n } = useTableContext();
const editorMode = ref<"create" | "rename" | null>(null);
const viewName = ref("");
const deletingView = ref<TableView | null>(null);

function openCreate() {
    viewName.value = "";
    editorMode.value = "create";
}

function openRename() {
    if (!views.selectedView.value) return;
    viewName.value = views.selectedView.value.name;
    editorMode.value = "rename";
}

function submitEditor() {
    const name = viewName.value.trim();
    if (!name || views.isMutating.value) return;

    if (editorMode.value === "create") {
        views.create(name);
    } else if (editorMode.value === "rename" && views.selectedView.value) {
        views.rename(views.selectedView.value, name);
    }

    editorMode.value = null;
}

function removeSelected() {
    if (!deletingView.value || views.isMutating.value) return;
    views.remove(deletingView.value);
    deletingView.value = null;
}
</script>

<template>
    <UiDropdownMenu v-if="views.resource.value">
        <UiDropdownMenuTrigger as-child>
            <UiButton variant="outline" :aria-label="i18n.t('views')">
                <Bookmark aria-hidden="true" />
                {{ views.selectedView.value?.name ?? i18n.t("views") }}
                <Circle
                    v-if="views.isDirty.value"
                    class="size-2 fill-current"
                    :aria-label="i18n.t('viewHasChanges')"
                />
            </UiButton>
        </UiDropdownMenuTrigger>
        <UiDropdownMenuContent align="end" class="min-w-56">
            <UiDropdownMenuLabel>{{
                i18n.t("savedViews")
            }}</UiDropdownMenuLabel>
            <UiDropdownMenuSeparator />
            <UiDropdownMenuItem
                v-if="views.resource.value.items.length === 0"
                disabled
            >
                {{ i18n.t("noSavedViews") }}
            </UiDropdownMenuItem>
            <UiDropdownMenuItem
                v-for="view in views.resource.value.items"
                :key="String(view.id)"
                @select="views.applyView(view)"
            >
                <Check
                    class="size-4"
                    :class="
                        String(views.selectedView.value?.id) === String(view.id)
                            ? 'opacity-100'
                            : 'opacity-0'
                    "
                    aria-hidden="true"
                />
                <span class="flex-1">{{ view.name }}</span>
                <Star
                    v-if="view.isDefault"
                    class="size-3.5 fill-current"
                    :aria-label="i18n.t('defaultView')"
                />
                <Users
                    v-if="view.isShared"
                    class="size-3.5"
                    :aria-label="i18n.t('sharedView')"
                />
            </UiDropdownMenuItem>

            <UiDropdownMenuSeparator />
            <UiDropdownMenuItem
                v-if="views.resource.value.canCreate"
                @select="openCreate"
            >
                <Save aria-hidden="true" />
                {{ i18n.t("saveView") }}
            </UiDropdownMenuItem>
            <template v-if="views.selectedView.value">
                <UiDropdownMenuItem
                    v-if="
                        views.isDirty.value &&
                        views.selectedView.value.canUpdate
                    "
                    @select="views.update(views.selectedView.value)"
                >
                    <Save aria-hidden="true" />
                    {{ i18n.t("updateView") }}
                </UiDropdownMenuItem>
                <UiDropdownMenuItem
                    v-if="views.isDirty.value"
                    @select="views.reset"
                >
                    <RefreshCcw aria-hidden="true" />
                    {{ i18n.t("resetView") }}
                </UiDropdownMenuItem>
                <UiDropdownMenuItem
                    v-if="views.selectedView.value.canUpdate"
                    @select="openRename"
                >
                    <Pencil aria-hidden="true" />
                    {{ i18n.t("renameView") }}
                </UiDropdownMenuItem>
                <UiDropdownMenuItem
                    v-if="
                        views.selectedView.value.canDefault &&
                        !views.selectedView.value.isDefault
                    "
                    @select="views.setDefault(views.selectedView.value)"
                >
                    <Star aria-hidden="true" />
                    {{ i18n.t("setDefaultView") }}
                </UiDropdownMenuItem>
                <UiDropdownMenuItem
                    v-if="views.selectedView.value.canShare"
                    @select="
                        views.setShared(
                            views.selectedView.value,
                            !views.selectedView.value.isShared,
                        )
                    "
                >
                    <Users aria-hidden="true" />
                    {{
                        views.selectedView.value.isShared
                            ? i18n.t("unshareView")
                            : i18n.t("shareView")
                    }}
                </UiDropdownMenuItem>
                <UiDropdownMenuItem
                    v-if="views.selectedView.value.canDelete"
                    variant="destructive"
                    @select="deletingView = views.selectedView.value"
                >
                    <Trash2 aria-hidden="true" />
                    {{ i18n.t("deleteView") }}
                </UiDropdownMenuItem>
            </template>
        </UiDropdownMenuContent>
    </UiDropdownMenu>

    <Dialog
        :open="editorMode !== null"
        @update:open="(open) => !open && (editorMode = null)"
    >
        <DialogContent>
            <DialogHeader>
                <DialogTitle>
                    {{
                        editorMode === "rename"
                            ? i18n.t("renameView")
                            : i18n.t("saveView")
                    }}
                </DialogTitle>
            </DialogHeader>
            <UiInput
                v-model="viewName"
                :aria-label="i18n.t('viewName')"
                :placeholder="i18n.t('viewName')"
                @keydown.enter="submitEditor"
            />
            <DialogFooter>
                <UiButton variant="outline" @click="editorMode = null">
                    {{ i18n.t("cancel") }}
                </UiButton>
                <UiButton
                    :disabled="!viewName.trim() || views.isMutating.value"
                    @click="submitEditor"
                >
                    {{ i18n.t("save") }}
                </UiButton>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <Dialog
        :open="deletingView !== null"
        @update:open="(open) => !open && (deletingView = null)"
    >
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{ i18n.t("deleteView") }}</DialogTitle>
                <DialogDescription>
                    {{
                        i18n.t("deleteViewMessage", {
                            name: deletingView?.name ?? "",
                        })
                    }}
                </DialogDescription>
            </DialogHeader>
            <DialogFooter>
                <UiButton variant="outline" @click="deletingView = null">
                    {{ i18n.t("cancel") }}
                </UiButton>
                <UiButton
                    variant="destructive"
                    :disabled="views.isMutating.value"
                    @click="removeSelected"
                >
                    {{ i18n.t("delete") }}
                </UiButton>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
