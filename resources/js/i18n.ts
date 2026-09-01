import {
    computed,
    inject,
    provide,
    ref,
    type App,
    type ComputedRef,
    type InjectionKey,
    type Plugin,
    type Ref,
} from "vue";

export const en = {
    actions: "Actions",
    addFilter: "Add filter",
    ascending: "Asc",
    bulkActions: "Bulk actions",
    cancel: "Cancel",
    clearAllFilters: "Clear all filters",
    clauseAfter: "After",
    clauseBefore: "Before",
    clauseBetween: "Between",
    clauseContains: "Contains",
    clauseEndsWith: "Ends with",
    clauseEqualOrAfter: "On or after",
    clauseEqualOrBefore: "On or before",
    clauseEquals: "Equals",
    clauseGreaterThan: "Greater than",
    clauseGreaterThanOrEqual: "Greater than or equal",
    clauseIn: "In",
    clauseIsFalse: "Is false",
    clauseIsNotSet: "Is not set",
    clauseIsSet: "Is set",
    clauseIsTrue: "Is true",
    clauseLessThan: "Less than",
    clauseLessThanOrEqual: "Less than or equal",
    clauseNotBetween: "Not between",
    clauseNotContains: "Does not contain",
    clauseNotEndsWith: "Does not end with",
    clauseNotEquals: "Does not equal",
    clauseNotIn: "Not in",
    clauseNotStartsWith: "Does not start with",
    clauseStartsWith: "Starts with",
    close: "Close",
    columns: "Columns",
    descending: "Desc",
    downloadExport: "Download export",
    defaultView: "Default view",
    delete: "Delete",
    deleteView: "Delete view",
    deleteViewMessage: 'Delete the saved view "{name}"?',
    editFilter: "Edit {filter} filter",
    exportData: "Export data",
    exportExpired: "Export expired",
    exportExpiredMessage: "This export is no longer available to download.",
    exportFailed: "Export failed",
    exportQueued: "Your export is being processed",
    exportQueuedMessage:
        "We're now exporting your data! You can close this dialog and we'll notify you once it's done.",
    exportReady: "Export ready",
    exportReadyMessage: "Your export is ready to download.",
    exports: "Export",
    filters: "Filters",
    firstPage: "First page",
    goToPage: "Go to page {page}",
    hideColumn: "Hide",
    lastPage: "Last page",
    loading: "Loading",
    manyRowsSelected: "{count} rows selected",
    nextPage: "Next page",
    no: "No",
    noResults: "No results found.",
    noRowsSelected: "No rows selected",
    oneRowSelected: "1 row selected",
    optionsSelected: "{count} options selected",
    page: "Page {page}",
    pageOf: "Page {page} of {pages}",
    pagination: "Pagination",
    pinColumn: "Pin column",
    previousPage: "Previous page",
    removeFilter: "Remove {filter} filter",
    rowsPerPage: "Rows per page",
    rowActions: "Row actions",
    searchPlaceholder: "Search…",
    selectAllMatching: "Select all {count} matching results",
    selectAllResults: "Select all matching results",
    selectCurrentPage: "Select current page",
    selectOptions: "Select options",
    selectRow: "Select row",
    save: "Save",
    savedViews: "Saved views",
    saveView: "Save view",
    setDefaultView: "Set as default",
    sharedView: "Shared view",
    shareView: "Share view",
    today: "Today",
    noSavedViews: "No saved views",
    renameView: "Rename view",
    resetView: "Reset changes",
    unshareView: "Stop sharing",
    updateView: "Update view",
    unpinColumn: "Unpin column",
    viewHasChanges: "View has unsaved changes",
    viewName: "View name",
    views: "Views",
    yes: "Yes",
} as const;

export type TableMessageKey = keyof typeof en;
export type TableMessages = Record<TableMessageKey, string>;
export type TableMessageOverrides = Partial<TableMessages>;
export type TableMessageParams = Record<string, string | number>;

export const vi: TableMessages = {
    actions: "Thao tác",
    addFilter: "Thêm bộ lọc",
    ascending: "Tăng dần",
    bulkActions: "Thao tác hàng loạt",
    cancel: "Hủy",
    clearAllFilters: "Xóa tất cả bộ lọc",
    clauseAfter: "Sau",
    clauseBefore: "Trước",
    clauseBetween: "Trong khoảng",
    clauseContains: "Chứa",
    clauseEndsWith: "Kết thúc bằng",
    clauseEqualOrAfter: "Bằng hoặc sau",
    clauseEqualOrBefore: "Bằng hoặc trước",
    clauseEquals: "Bằng",
    clauseGreaterThan: "Lớn hơn",
    clauseGreaterThanOrEqual: "Lớn hơn hoặc bằng",
    clauseIn: "Thuộc",
    clauseIsFalse: "Là sai",
    clauseIsNotSet: "Chưa được đặt",
    clauseIsSet: "Đã được đặt",
    clauseIsTrue: "Là đúng",
    clauseLessThan: "Nhỏ hơn",
    clauseLessThanOrEqual: "Nhỏ hơn hoặc bằng",
    clauseNotBetween: "Ngoài khoảng",
    clauseNotContains: "Không chứa",
    clauseNotEndsWith: "Không kết thúc bằng",
    clauseNotEquals: "Không bằng",
    clauseNotIn: "Không thuộc",
    clauseNotStartsWith: "Không bắt đầu bằng",
    clauseStartsWith: "Bắt đầu bằng",
    close: "Đóng",
    columns: "Cột",
    descending: "Giảm dần",
    downloadExport: "Tải file xuất",
    defaultView: "Chế độ xem mặc định",
    delete: "Xóa",
    deleteView: "Xóa chế độ xem",
    deleteViewMessage: 'Xóa chế độ xem đã lưu "{name}"?',
    editFilter: "Chỉnh sửa bộ lọc {filter}",
    exportData: "Xuất dữ liệu",
    exportExpired: "File xuất đã hết hạn",
    exportExpiredMessage: "File xuất này không còn khả dụng để tải xuống.",
    exportFailed: "Xuất dữ liệu thất bại",
    exportQueued: "Dữ liệu của bạn đang được xuất",
    exportQueuedMessage:
        "Chúng tôi đang xuất dữ liệu của bạn. Bạn có thể đóng hộp thoại này và chúng tôi sẽ thông báo khi hoàn tất.",
    exportReady: "Dữ liệu xuất đã sẵn sàng",
    exportReadyMessage: "File xuất đã sẵn sàng để tải xuống.",
    exports: "Xuất",
    filters: "Bộ lọc",
    firstPage: "Trang đầu",
    goToPage: "Đi đến trang {page}",
    hideColumn: "Ẩn",
    lastPage: "Trang cuối",
    loading: "Đang tải",
    manyRowsSelected: "Đã chọn {count} dòng",
    nextPage: "Trang sau",
    no: "Không",
    noResults: "Không tìm thấy kết quả.",
    noRowsSelected: "Chưa chọn dòng nào",
    oneRowSelected: "Đã chọn 1 dòng",
    optionsSelected: "Đã chọn {count} tùy chọn",
    page: "Trang {page}",
    pageOf: "Trang {page} / {pages}",
    pagination: "Phân trang",
    pinColumn: "Ghim cột",
    previousPage: "Trang trước",
    removeFilter: "Xóa bộ lọc {filter}",
    rowsPerPage: "Số dòng mỗi trang",
    rowActions: "Thao tác cho dòng",
    searchPlaceholder: "Tìm kiếm…",
    selectAllMatching: "Chọn tất cả {count} kết quả phù hợp",
    selectAllResults: "Chọn tất cả kết quả phù hợp",
    selectCurrentPage: "Chọn trang hiện tại",
    selectOptions: "Chọn tùy chọn",
    selectRow: "Chọn dòng",
    save: "Lưu",
    savedViews: "Chế độ xem đã lưu",
    saveView: "Lưu chế độ xem",
    setDefaultView: "Đặt làm mặc định",
    sharedView: "Chế độ xem được chia sẻ",
    shareView: "Chia sẻ chế độ xem",
    today: "Hôm nay",
    noSavedViews: "Chưa có chế độ xem đã lưu",
    renameView: "Đổi tên chế độ xem",
    resetView: "Đặt lại thay đổi",
    unshareView: "Dừng chia sẻ",
    updateView: "Cập nhật chế độ xem",
    unpinColumn: "Bỏ ghim cột",
    viewHasChanges: "Chế độ xem có thay đổi chưa lưu",
    viewName: "Tên chế độ xem",
    views: "Chế độ xem",
    yes: "Có",
};

export type TableI18n = {
    locale: ComputedRef<string> | Ref<string>;
    t: (key: TableMessageKey, params?: TableMessageParams) => string;
};

export type InertiaTableI18nOptions = {
    locale?: string;
    messages?: TableMessageOverrides;
};

const tableI18nKey = Symbol(
    "MusingInertiaTableI18n",
) as InjectionKey<TableI18n>;

function interpolate(
    template: string,
    params: TableMessageParams = {},
): string {
    return template.replace(/\{(\w+)\}/g, (match, key: string) =>
        Object.hasOwn(params, key) ? String(params[key]) : match,
    );
}

export function createTableI18n(
    locale: Ref<string> | ComputedRef<string>,
    messages: Ref<TableMessageOverrides> | ComputedRef<TableMessageOverrides>,
    fallback?: TableI18n,
): TableI18n {
    return {
        locale,
        t(key, params = {}) {
            const message = messages.value[key];

            if (message === undefined && fallback) {
                return fallback.t(key, params);
            }

            return interpolate(message ?? en[key], params);
        },
    };
}

const defaultI18n = createTableI18n(ref("en-US"), ref({}));

export function provideTableI18n(i18n: TableI18n): void {
    provide(tableI18nKey, i18n);
}

export function useTableI18n(): TableI18n {
    return inject(tableI18nKey, defaultI18n);
}

export function createInertiaTable(
    options: InertiaTableI18nOptions = {},
): Plugin {
    return {
        install(app: App) {
            app.provide(
                tableI18nKey,
                createTableI18n(
                    computed(() => options.locale ?? "en-US"),
                    computed(() => options.messages ?? {}),
                ),
            );
        },
    };
}
