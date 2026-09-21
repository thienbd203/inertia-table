# Kiểm thử tay playground — 2026-09-18

Thao tác trực tiếp bằng browser trên http://127.0.0.1:8001, dữ liệu 180 sản
phẩm. Không dùng unit tests để thay cho các kết luận dưới đây. Đây là smoke
test các hành trình đã liệt kê, không phải chứng nhận toàn bộ feature/matrix.

## Đã kiểm chứng

| Hành trình | Kết quả quan sát |
| --- | --- |
| Search không khớp | `zzzz-no-product-918` trả empty state; select-all disabled. Xóa bằng bàn phím trả dữ liệu. |
| Clear filters | Gỡ các điều kiện của view ban đầu, summary trở về 180 sản phẩm. |
| Sort giá tăng | Các dòng đầu lần lượt 109.565, 110.155, 116.810 đồng. |
| Page size / cursor | Đổi 50 thành 10 hiển thị 10 dòng; Trang sau đổi dữ liệu, Trang trước được bật. |
| Select all xuyên trang | 162 dòng selectable trong 180 sản phẩm; chuyển trang vẫn giữ selection. Bỏ chọn #92 còn 161. |
| Shift-click | Chọn #11 rồi Shift-click #16 trong thứ tự giá tăng chọn 3 dòng. |
| Bulk confirmation / cancel | Hiển thị đúng 162 đối tượng; Hủy đóng dialog. Không xác nhận lưu trữ hàng loạt. |
| Lazy category options | Mở editor thấy 6 options; chọn Âm thanh trả 30 sản phẩm, reset selection và cursor. Không đo số request bằng network instrumentation. |
| Nested overlays | Escape đóng options, Escape tiếp đóng editor. |
| Column visibility / resize | Hiện SKU được; resize bằng ArrowRight đổi 268 → 278. Bấm bỏ ghim được; chưa kiểm tra sticky khi scroll. |
| Row action | Bỏ nổi bật #11 chuyển Có → Không; bật lại chuyển Không → Có. |
| Saved View | Tạo `QA browser 2026-09-18`, reload vẫn có trong menu, chọn view áp dụng được; reset thay đổi trả summary về 30 sản phẩm. |
| Numeric range | Danh mục Âm thanh + giá 100000–500000 trả 6 dòng, cả 6 giá nằm trong khoảng. |
| Date | Chọn Hôm nay cho điều kiện Trước, chip hiện `< 2026-09-18`; 6 dòng đang có đều trước ngày này. Chưa thử biên bằng ngày và date range. |

## Vấn đề tái hiện được

1. **Lazy label mất sau reload:** chọn Danh mục → Âm thanh, reload hoặc áp dụng
   saved view. Chip hiện `Danh mục ∈ 1` thay cho tên. Dữ liệu vẫn lọc đúng.
2. **Chip cũ còn sau reset/chuyển view:** ở QA view thêm Giá và Ngày mở bán,
   sau đó Đặt lại thay đổi. Query/summary trở lại 30 sản phẩm nhưng chip
   `Giá =` và `Ngày mở bán <` còn trống. Chuyển sang view Test còn cả chip
   Danh mục trống bên cạnh các filter của Test. Chưa kết luận đây là hành vi
   cố ý giữ draft hay bug; giao diện không phân biệt rõ điều kiện chưa active.
3. **Warning ở dialog lưu view:** console báo thiếu `Description` hoặc
   `aria-describedby="undefined"` cho DialogContent. Không thấy console error.

## Chưa kết luận

- CSV sync: đã bấm Kết quả theo bộ lọc + tổng; browser tool không nhận download
  trong 15 giây. Không có lỗi hiện trên trang. Chưa xác nhận file/nội dung CSV,
  không coi timeout của công cụ là bằng chứng export hỏng.
- Queued export: đã bấm xuất nền, dialog đang xử lý xuất hiện và đóng được.
  Chưa quan sát hoàn tất/download; chưa kiểm tra worker có chạy hay không.
- Chưa chạy toàn bộ clause/type, sort giảm, reorder/drag, mobile/dark/RTL,
  queue failure/retry, xác nhận bulk mutation, sửa/xóa view hoặc screen reader.

## Dấu vết kiểm thử

- Đã trả bảng về Saved View `Test`; không sửa nội dung view Test.
- View `QA browser 2026-09-18` còn trong danh sách để đối chiếu (không đặt mặc định).
- #11 đã phục hồi `featured`, nhưng `updated_at` đổi do hai lần action.
- Có một yêu cầu queued export được tạo; chưa xác định trạng thái cuối.
- Không sửa implementation trong lượt kiểm thử này.
