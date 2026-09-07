# Kế hoạch DX, UI và khả năng bảo trì

Ngày lập: 2026-09-07. Source đã đối chiếu: `541347e`, nhánh `codex/docs-site`.

**Trạng thái: kế hoạch để triển khai, chưa thực hiện các task bên dưới.** Việc
đọc source khi lập plan không thay thế baseline test, browser audit hoặc kiểm
chứng trên ứng dụng mới. Không dùng điểm số đánh giá trước đó trong hội thoại
làm số đo chất lượng.

Quyết định của maintainer: tạm đóng băng feature, tập trung vào DX, UI và khả
năng bảo trì của tập capability hiện có. Plan này là backlog cho giai đoạn đó.
`implementation-plan.vi.md` là hồ sơ refactor cũ; không triển khai lại các task
đã hoàn tất. `docs-site-plan.vi.md` vẫn là tài liệu tham chiếu cho docs site;
chỉ khép lại phần publish/onboarding còn cần xác minh.

## 1. Kết quả cần đạt

1. Developer dùng một Laravel/Inertia/Vue app đã hoạt động có thể tạo bảng đầu
   tiên theo docs mà không mở source package hay nhờ giải thích bước bị thiếu.
2. IDE giúp hiểu callback, props, events, slots và dữ liệu row; cấu hình sai do
   developer được báo đúng vị trí và có hướng sửa.
3. Search, lazy filters, pagination, selection, Saved Views và column layout
   giữ state/focus nhất quán khi dùng chuột, bàn phím và mạng chậm.
4. Giao diện dùng được ở màn hình nhỏ, light/dark và ngôn ngữ hiện có; các lớp
   tùy biến của host tiếp tục hoạt động.
5. Maintainer sửa một hành vi mà biết file nào sở hữu state, test nào cần chạy
   và contract nào phải giữ. Refactor giảm độ phụ thuộc, không chỉ giảm dòng.

### Quy tắc feature freeze

- Nhận bugfix, tài liệu, type/PHPDoc, accessibility, UI polish, profiling và
  refactor phục vụ hành vi đang có.
- Giữ `SetFilter::lazy()`. Không khôi phục remote search/pagination API của
  filter, không thêm nút Apply hoặc bước xác nhận filter.
- Không thêm renderer mới, virtualization, filter builder, loại export mới,
  schema registry, plugin framework hoặc thay toàn bộ UI library.
- Giữ public PHP signatures, tên named arguments, subclass hooks, Vue exports,
  props/events/slots, resource v2, URL và các persistence contracts.
- Có thể làm type chính xác hơn khi consumer hợp lệ vẫn compile. Nếu type mới
  làm một use case hợp lệ không còn compile, đó là thay đổi compatibility cần
  được đánh giá, không phải mặc nhiên là cải thiện DX.
- Không thêm public option để né một bug mặc định. Nếu không thể sửa đúng mà
  không đổi contract, mô tả ví dụ trước/sau và ảnh hưởng để maintainer quyết định.
- Test fixture, trang demo và công cụ kiểm chứng nội bộ không tính là feature
  của package. Chỉ bổ sung đủ cho các kịch bản trong plan.
- Việc hoàn tất plan không tự động mở lại feature development hoặc phát hành
  `1.0`. Maintainer quyết định hai việc đó riêng.

## 2. Những gì đã thấy trong source

Các quan sát dưới đây giúp chọn task, không phải danh sách lỗi đã tái hiện.

| Quan sát tại mốc audit | Ý nghĩa với kế hoạch |
| --- | --- |
| `DataTable.vue` 249 dòng, đã compose `components/table/*` | Không đặt mục tiêu tách tiếp chỉ vì file mang tên DataTable |
| `useTable.ts` 668 dòng; gồm navigation, lazy options và column layout | Cần xác lập chủ sở hữu state trước khi xem xét tách nội bộ |
| `useActions.ts` 618 dòng; gồm selection, confirmation, execution và polling | Xem selection như một ranh giới có thể tách, giữ callbacks/public facade |
| `Table.php` 1.565 dòng, có nhiều helper private và hooks protected | Kích thước chỉ là tín hiệu; hook/query compatibility quan trọng hơn |
| `DataTable.vue::defineSlots` dùng index signature với `any` | Có cơ hội cải thiện autocomplete nhưng phải giữ dynamic/custom slots |
| PHPDoc của nhiều callback đã có, một số vẫn chỉ khai báo `Closure` | Điền chỗ thiếu theo call site thực tế, tránh thêm generics khắp repo |
| `validatedColumns/Filters` kiểm tra type; actions/exports còn kiểm tra trùng key | Error context chưa đồng đều; duplicate column/filter cần quyết định theo contract |
| `ActiveFilter.vue` có logic mở lại popover sau lazy load | Cần kiểm chứng focus và ý định đóng của user dưới latency; chưa kết luận lỗi |
| `FilterValueControl.vue` có draft input/range, `useFilterEditor` cũng giữ draft | Có nhiều nguồn state cần lập bảng đồng bộ và test thứ tự phản hồi |
| Vitest dùng `isolate: false` và alias Inertia mock dùng chung | Phải kiểm chứng cleanup, thứ tự file và không coi mock là integration thật |
| Nhiều component test xóa `document.body`, không thấy setup auto-unmount chung | Xóa HTML không chứng minh watchers/listeners/timers đã dispose |
| `docs/internals/development.md` ghi `tests/UrlContractTest.php` và chiều sinh artifact không khớp code | DX01 sửa theo `ContractUrlTest.php`, TS phát URL rồi PHP đọc |
| Docs site/config/CI đã tồn tại trong nhánh audit | Không dựng site lần hai; xác minh merge/deploy, links và ví dụ |
| `run-contract-tests.yml` chưa trigger trên toàn bộ serializer `src/**`; `release.yml` chưa gọi URL bridge | M05 rà coverage của workflow theo đường dữ liệu, tránh gate bỏ sót |
| `docs/internals/architecture.md` còn mục lịch sử `v0.1 scope`/restart từ spike | M06 phân biệt lịch sử với hướng dẫn hiện hành |

Số dòng thay đổi theo commit; không lấy chúng làm ngưỡng pass/fail. Kết quả
test/CI ở lượt trước là lịch sử, không được chép thành baseline của checkout mới.

## 3. Phạm vi repo và cách thực thi

- **Package:** repo chứa plan, `src/`, `resources/js/`, `tests/`, `tests-js/`,
  `docs/`, `stubs/`, config và workflows.
- **Playground:** repo riêng `../inertia-table-playground`; hiện có
  `app/Tables/ProductsTable.php`, `app/Http/Controllers/ProductController.php`,
  `resources/js/pages/Products/Index.vue`, `resources/js/app.ts`,
  `resources/css/app.css`, `routes/web.php`, `tests/Feature/ProductTableTest.php`.
  Đọc `AGENTS.md`, `.ai/rules` và skills áp dụng ở repo đó trước khi sửa.
- Playground đang tham chiếu package bằng đường dẫn local; nó hữu ích để thử
  tương tác nhưng không thay thế kiểm chứng npm tarball trong DX02.
- Tôn trọng server user đã mở, kiểm tra port hiện tại trước khi chạy thêm;
  không tự dừng server, reset dữ liệu playground hay ghi đè thay đổi của user.
- Các tên file **mới** trong card là đề xuất vị trí. Tái sử dụng file/harness
  cùng trách nhiệm nếu đã xuất hiện ở checkout triển khai.
- Model thực thi đọc card được giao, source/tests liên quan và các quy tắc
  chung. Không audit lại cả repo hoặc tạo plan con cho mỗi card.
- Một lượt thực hiện một task hoặc nhóm được chỉ định. Các task cùng sửa
  `Table.php`, `useTable.ts`, `useActions.ts`, test setup hoặc Vite config phải
  tuần tự. Tài liệu không yêu cầu mở thêm agent hay chọn một model cụ thể.
- Không nâng runtime/dependency hoặc đổi lockfile ngoài nhu cầu trực tiếp của
  task. Dùng công cụ đã có; nếu thiếu công cụ browser CI, đề xuất một bổ sung
  tối thiểu có lý do thay vì dựng cả hệ thống E2E mới.

### Phân loại và trạng thái task

- **S:** một vùng nhỏ, ít phụ thuộc; **M:** vài file cùng hành vi;
  **L:** qua PHP/Vue hoặc cần real-browser/consumer verification.
  Đây là mức độ phức tạp, không phải cam kết số giờ hoặc chi phí model.
- Trạng thái hợp lệ: `todo`, `doing`, `done`, `no-change`, `blocked`.
- `no-change` chỉ dùng cho phần audit/refactor có điều kiện, phải ghi bằng
  chứng tại sao code hiện tại đã đủ hoặc tách ra không có lợi.
- `blocked` ghi dependency cụ thể, việc độc lập còn có thể làm và thông tin
  cần maintainer cung cấp. Không bỏ qua yêu cầu rồi gọi toàn bộ plan là xong.

## 4. Backlog và thứ tự mặc định

| ID | Deliverable | Ưu tiên | Cỡ | Phụ thuộc | Trạng thái |
| --- | --- | --- | --- | --- | --- |
| Q00 | Baseline, inventory, freeze scope | P0 | S | — | todo |
| M01 | Test lifecycle và shared-worker isolation | P0 | M | Q00 | todo |
| DX01 | Docs live/onboarding và sửa commands lệch | P0 | S | Q00 | todo |
| DX02 | Consumer fixture dùng package đã đóng gói, CSR/SSR | P0 | L | M01, phần local guide của DX01 | todo |
| UI00 | Catalog kịch bản và baseline UI | P0 | M | Q00 | todo |
| UI01 | Filter draft, lazy loading và focus | P0 | L | M01, UI00 | todo |
| UI02 | Navigation/loading, URL và resource sync | P0 | L | UI01 | todo |
| UI03 | Keyboard, labels và overlay accessibility | P1 | M | UI00, UI01 | todo |
| UI04 | Responsive, theme và visual consistency | P1 | M | UI00, UI03 | todo |
| UI05 | Phản hồi action/export/view theo kết quả thật | P1 | M | UI02, UI03 | todo |
| DX03 | PHP callback docs và fluent API typing | P1 | M | Q00 | todo |
| DX04 | Vue props/events/slots và consumer types | P1 | M | DX02 | todo |
| DX05 | Error messages và declaration validation | P1 | M | Q00 | todo |
| DX06 | Generator output nhỏ, rõ và chạy được | P1 | S | DX03, DX05 | todo |
| DX07 | Recipes và troubleshooting đã chạy thử | P1 | M | DX01–DX06, UI01–UI05 | todo |
| M02 | Tách trách nhiệm nội bộ của useTable nếu có lợi | P2 | M | UI02, DX04 | todo |
| M03 | Tách selection khỏi action execution nếu có lợi | P2 | M | UI05, DX04 | todo |
| M04 | Rà PHP extension hooks và thu gọn hotspot có bằng chứng | P2 | L | DX03, DX05 | todo |
| M05 | CI gates theo contract và consumer | P0 | M | DX02, DX04, M01 | todo |
| M06 | Tài liệu ownership, maintenance và compatibility | P1 | S | M02–M05 | todo |
| M07 | Đo performance, sửa bottleneck đã xác nhận | P2 | M | UI01–UI05, M02–M04 | todo |
| V01 | Kiểm chứng cuối và bàn giao theo tiêu chí | P0 | L | Tất cả task trên có kết luận | todo |

Thứ tự triển khai khuyến nghị:

`Q00 → M01 → DX01 → DX02 → UI00 → UI01 → UI02 → UI03 → UI04 → UI05 →
DX03 → DX04 → DX05 → DX06 → DX07 → M02 → M03 → M04 → M05 → M06 → M07 → V01`.

M05 có thể làm ngay khi đủ dependencies, không cần đợi refactor P2. Lỗi P0 có
reproduction được sửa sớm; không đợi tất cả task về docs/type hoàn tất. Một PR
không gộp sửa hành vi UI và di chuyển cơ học phần code đó.

Blocker quyền deploy ở DX01 chỉ giữ phần publish chưa hoàn tất; DX02 và DX07
có thể tiếp tục sau khi commands/guide local đã đúng. V01 phải ghi rõ phần
publish chưa xác minh, không biến build local thành bằng chứng public deploy.

## 5. Task cards — baseline và DX

### Q00 — Ghi baseline có thể so sánh

**Đọc:** `CLAUDE.md`, manifests, `vite.config.ts`, workflows, API stability và
source/test được card sau tham chiếu. Kiểm tra hướng dẫn áp dụng ở checkout.

**Làm:**

1. Ghi HEAD/branch/working tree, PHP/Node và dependency versions thực cài. Đối
   chiếu source audit khi khác HEAD; không reset về commit trong plan.
2. Chạy bộ baseline ở mục 9 một lần; tách lỗi có sẵn, lỗi môi trường và failure
   do thay đổi mới. Ghi chính xác skips, đặc biệt URL bridge.
3. Lập inventory public surface từ PHP signatures/hooks, `resources/js/index.ts`,
   component props/events/slots, docs CSS và resource/persistence contract.
   Ghi các điểm dễ nhầm tên/default để DX03–DX05 xử lý; không rename tại đây.
4. Ghi baseline vào phụ lục A của plan. Không tính số test như một thước đo
   coverage; liệt kê journey nào có unit/contract/browser bằng chứng.

**Done:** baseline tái lập được, failures được phân loại và card tiếp theo có
đầu vào rõ. Không sửa production source hoặc quét formatter toàn repo.

### DX01 — Khép lại docs site và đường onboarding

**Scope:** README, `docs/guide/getting-started.md`,
`docs/internals/development.md`, `docs/reference/compatibility.md`, VitePress
config và workflow docs khi thật sự cần. Đối chiếu docs-site plan cũ.

**Làm:**

1. Xác minh nhánh docs đã merge/deploy chưa; không coi build thành công là site
   đã public. Ghi URL/commit/workflow đã kiểm chứng, hoặc blocker Pages cụ thể.
2. Sửa URL bridge instructions: TypeScript sinh URLs, PHP đọc bằng
   `INERTIA_TABLE_URL_CONTRACT_INPUT`; tên test là `ContractUrlTest.php`.
   Tách resource fixture được commit khỏi URL artifact trong `build/`.
3. Làm rõ prerequisites của quick start: Laravel/Inertia/Vue/Tailwind đã hoạt
   động, model/schema/route có sẵn hoặc ví dụ chỉ rõ cách tạo. Bảng phải có tên
   khớp prop để partial reload hoạt động. Không để người đọc tự đoán imports.
4. Dùng lockfile qua `npm ci` cho contributor; lời khuyên cài package cho host
   app vẫn đúng ngữ cảnh. Đối chiếu stylesheet, Tailwind source và peer deps.
5. Kiểm tra home, một deep link, search `lazy`, navigation mobile và 404; giữ
   plan nội bộ ngoài build/search/sitemap. Chỉ sửa lỗi thật, không redesign site.

**Kiểm chứng:** chạy các commands tài liệu vừa sửa, `npm run docs:build`, link
check và browser smoke. Public deploy chưa có quyền truy cập thì ghi blocked
cho phần đó và tiếp tục DX02/UI00; không giả định site đã live.

**Done:** không còn bước/command sai đã nêu; quick start có thể thực thi đầy đủ
và trạng thái publish được báo đúng sự thật.

### DX02 — Kiểm chứng như một consumer thật

**Scope:** manifests/build config nếu phát hiện lỗi đóng gói, một consumer
fixture tối thiểu đặt dưới `tests/Consumer/` (mới, nếu chưa có), README/guide.
Tái sử dụng Testbench/workbench cho PHP; playground phục vụ kiểm chứng UI.

**Làm:**

1. Tạo fixture nhỏ từ quick start: một model/table, một route/controller, một
   Vue page; đủ dữ liệu cho search/sort/filter. Ghi runtime và cách dựng lại.
2. Build và `npm pack` vào `build/consumer/`; cài tarball trong consumer tách
   khỏi source alias. Import root package và `style.css`, compile `.d.ts`, build
   Tailwind thực tế. Kiểm tra payload package không kéo docs/test/build artifacts.
3. Với PHP, xác minh autoload/service discovery, generator và config từ package
   theo cách fixture cài; nếu vẫn dùng path repository phải ghi rõ giới hạn,
   không gọi đó là kiểm chứng release archive.
4. Dùng Inertia/Vue thật trong consumer, không áp alias `tests-js/inertiaMock.ts`.
   Chạy CSR và đường SSR đúng entry/setup của phiên bản Inertia thực cài; kiểm
   tra HTML server và hydration của browser đều thành công.
5. Thêm browser smoke nhỏ cho search → sort → filter → reload URL. Giữ scenario
   có thể chạy từ clean checkout; artifacts/logs để dưới `build/`, không commit
   `node_modules`, database local hoặc tarball.

**Kiểm chứng:** consumer build/typecheck, một route SSR có nội dung table, browser
không hydration/console error, request/props cập nhật đúng khi partial reload.
Ghi phân biệt source-linked playground, packed consumer và SSR runner đã dùng.

**Done:** có bằng chứng package build dùng được bên ngoài repo; source tests
xanh không còn là bằng chứng duy nhất cho install/SSR. Không tắt SSR để pass.

### DX03 — PHP API dễ hiểu trong IDE

**Scope:** PHPDoc của `Table`, `Columns/*`, `Filters/*`, `Actions/Action`,
`Exports/Export`, `AnonymousTable` và examples consumer liên quan.

**Làm:**

1. Từ inventory Q00, đối chiếu callback signature với nơi gọi thật: thứ tự
   argument, nullability, return type, builder mutate/return và lifecycle.
2. Bổ sung PHPDoc chính xác cho fluent methods, collections và callback thiếu
   thông tin. Giữ tên tham số, native types, `static` và override compatibility.
3. Thử autocomplete/inferred type cho column formatter, query hook, action
   handler và export callback. Chỉ thêm generic ở nơi compiler/IDE chứng minh
   có lợi; không bắt mọi table consumer thêm annotations lan truyền.
4. Thêm fixture static-analysis nhỏ ở `tests/Consumer/` hoặc harness đã có để
   giữ use case hợp lệ. Không dùng `mixed`/ignore/baseline tăng để che regression.

**Kiểm chứng:** PHPStan của repo và consumer fixture, focused Pest cho callback
đụng tới, scoped Pint. PHPDoc-only không cần thêm runtime test phản chiếu source.

**Done:** docs và IDE mô tả đúng callback; fluent chains/subclasses cũ vẫn dùng
được. Chưa đạt model-specific inference phải ghi giới hạn, không hứa sai type.

### DX04 — Vue types và slots có autocomplete hữu ích

**Scope:** `DataTable.vue`, `types.ts`, `index.ts`, `context/tableContext.ts`,
slot composition và type fixtures của DX02.

**Làm:**

1. Liệt kê props/events/slots đang render từ source, đặc biệt payload `item`,
   `column`, table/actions scope và dynamic `cell(...)`/header/filter slots.
   Dùng tên thật trong `SlotOutlet` và docs, không tự đặt convention mới.
2. Thay `any` cho slots đã biết bằng type mô tả payload thật, giữ generic `T`
   xuyên `DataTable` và consumer. Giữ compatibility cho custom/dynamic slots.
3. Type fixture compile các examples thật bằng public package import; có
   negative case cho field/payload sai để xác nhận type không âm thầm thành `any`.
4. Kiểm tra emitted declarations sau build và với custom headless renderer.
   Ưu tiên inferred/exported types hiện có; chỉ export type mới nếu consumer
   thực sự cần đặt tên cho nó, không mở thêm runtime API.

**Kiểm chứng:** `npm run types:check`, package build, consumer typecheck; slot
behavior regressions qua component test hiện có. Không thêm snapshots toàn SFC.

**Done:** known slot payload có type hữu ích, event consumer hợp lệ compile;
custom slots không bị khóa cứng thành danh sách thiếu escape hatch.

### DX05 — Cấu hình sai được báo có ngữ cảnh

**Scope:** `Table.php::validated*`, cursor validation, Column/Filter validations
và optional adapter diagnostics; `tests/TableTest.php` và tests tương ứng.

**Làm:**

1. Lập bảng lỗi hiện có: caller, exception class, message, thông tin có thể
   thêm. Ưu tiên type sai trong definitions, duplicate action/export key,
   cursor sort không hỗ trợ và optional dependency thiếu.
2. Message cho lỗi lập trình cần tên table class, method/attribute/key liên
   quan, yêu cầu hợp lệ và hướng sửa. Giữ exception class/status khi có contract.
3. Duplicate column/filter chưa được reject như action/export: kiểm tra test,
   docs và use case override hợp lệ trước. Chỉ reject nếu chứng minh là invalid
   definition; nếu làm thay đổi behavior đã được hỗ trợ, ghi decision cần chốt.
4. Phân biệt declaration sai với request không tin cậy: URL attribute/clause
   lạ vẫn normalize/drop theo policy. Không biến request rác thành exception
   cấu hình, không trả stack/SQL/context nhạy cảm ra queue status hay browser.

**Kiểm chứng:** regression cho một lỗi có thật ở mỗi vùng sửa, valid definitions
và unknown-input normalization vẫn qua; PHPStan + scoped Pint.

**Done:** lỗi giúp developer tìm và sửa config, không mở rộng failure mode của
request hợp lệ hoặc thay authorization bằng error handling dễ dãi hơn.

### DX06 — Generator tạo điểm bắt đầu rõ ràng

**Scope:** `src/Commands/MakeTableCommand.php`, `stubs/table.stub`,
`tests/MakeTableCommandTest.php`, docs artisan/getting-started.

**Làm:**

1. Chạy generator hiện có cho model thường, model namespace lồng nhau và table
   name có/không hậu tố; đọc generated file trong consumer DX02.
2. Thêm return/PHPDoc cần thiết và comment hướng dẫn đúng chỗ; giữ ví dụ ngắn.
   Không sinh sẵn queue/view/export implementation hoặc thêm flag mới.
3. Đảm bảo output compile và table resolve được với model fixture. Giữ bảo vệ
   existing file, và `--force` chỉ ghi đè khi caller yêu cầu rõ.
4. Không bắt model phải tồn tại nếu command hiện cho phép scaffold trước model.
   Đồng bộ example với signature/schema thật, không chỉ test string contains.

**Kiểm chứng:** generator tests + generated consumer class resolve; scoped Pint,
PHPStan khi annotations/signatures liên quan.

**Done:** output có thể dùng ngay làm table tối thiểu và không gây mất app edits.

### DX07 — Recipes theo công việc của người dùng

**Scope:** các guide/features/customization/reference đang có; ưu tiên bổ sung
vào page đúng chủ đề, không sinh một site hoặc bộ API docs thứ hai.

**Làm:**

1. Hoàn thiện năm recipe đã chạy: table tối thiểu; lazy set filter; hai bảng
   cùng trang; custom cell/action; headless table tối thiểu.
2. Mỗi recipe có imports, host assumptions, expected behavior và đường link tới
   file/sample chạy được. Tránh giant example bật tất cả capabilities.
3. Troubleshooting tập trung vào lỗi đã tái hiện: missing CSS/Tailwind scan,
   prop/table name, partial reload, lazy options, SSR setup và declaration errors.
4. Phân biệt host error handling với package state; giải thích selected/all
   matching, Saved View dirty/reset, query scopes bằng ví dụ thực tế.
5. Update docs theo UI/DX đã hoàn tất. README giữ đường onboarding ngắn;
   advanced topics không quay lại README. Không quảng bá feature đã loại bỏ.

**Kiểm chứng:** chạy recipes trong fixture/playground, `npm run docs:build`,
kiểm tra links. Nếu đo onboarding, ghi cách đo theo mục 8 thay vì tự cho điểm.

**Done:** người dùng tìm được happy path và cách sửa vấn đề thường gặp mà không
cần đọc internal architecture hoặc hỏi lại maintainer.

## 6. Task cards — UI và hành vi tương tác

### UI00 — Catalog nhỏ, có trạng thái kiểm chứng

**Scope:** playground hiện có; consumer fixture DX02 khi cần integration tự động.
Tái sử dụng Product/Category models, tables, routes, pages và tests hiện hành.

**Làm:**

1. Tạo cách mở/reset từng scenario ở mục 8 bằng demo controls/route fixture nhỏ.
   Controls chỉ nằm trong playground. Không thêm setting vào package để demo.
2. Dữ liệu seed có tên/IDs ổn định, ngày/locale kiểm soát được. Không xóa database
   user đang dùng; seed/test database riêng hoặc thêm fixture scope an toàn.
3. Ghi URL, package/host commit, viewport, trạng thái, expected và actual; chụp
   before cho scenario có lỗi và lưu reproduction tối thiểu.
4. Dùng real browser để quan sát focus, portal, scroll, sticky, hydration và
   loading. DOM emulation không chứng minh được geometry hay accessibility.

**Done:** catalog hữu ích cho UI01–UI05, mỗi finding phân biệt lỗi đã tái hiện
với nghi vấn cần kiểm tra. Không cần Storybook hoặc live service mới.

### UI01 — Filter ổn định khi chọn và khi tải lazy options

**Scope:** `components/table/filters/*`, filter orchestration trong
`DataTable.vue`, `useTable.ts::loadFilterOptions/setFilter`, lazy PHP contract
chỉ khi regression dẫn tới backend. Tests: `LazyFilterTest.php`,
`useTable.test.ts`, `useFilterEditor.test.ts`, component tests liên quan.

**Làm:**

1. Vẽ bảng ownership: server applied state, draft value/clause, options loaded,
   loading request, popover open, focus target. Mỗi phần chỉ có một owner chính.
2. Tái hiện chuỗi: mở filter → lazy load → chọn nhiều giá trị nhanh → đổi clause
   → đóng trong lúc tải → phản hồi tới. Dùng latency cố định và hai filter cùng
   tải; kiểm tra cả URL khôi phục filter đã chọn.
3. Sửa tối thiểu để selection/draft không mất khi props thay mới; response cũ
   không ghi đè draft mới; user đã đóng thì callback không tự mở popover lại.
4. Giữ auto-apply: chọn set option áp dụng theo hành vi hiện tại, text debounce,
   range đủ hai đầu mới gửi. Không thêm Apply, remote endpoint hay lựa chọn UI
   đổi semantics. Focus/scroll không nhảy vì trigger bị thay/unmount không cần.
5. Lazy request thành công chỉ tải lần cần thiết theo contract; lỗi/cancel kết
   thúc loading, cho phép mở lại để thử lại và không xóa selection đã áp dụng.
   Giữ declared-option/allowlist và header scoped theo table.

**Kiểm chứng:** regression callback-order tại composable/component và browser
scenario có network/focus evidence. Đo request count, không chỉ nhìn screenshot.

**Done:** các chuỗi trên giữ đủ lựa chọn, không tự reopen, không stale overwrite;
thao tác bàn phím áp dụng được tương đương chuột, không có bước Apply mới.

### UI02 — Navigation/loading thống nhất với URL và server props

**Scope:** `useTable.ts`, `DataTable.vue`, `url.ts`, `useViews.ts` khi apply state;
tests URL, multi-table, navigation, saved views và consumer DX02.

**Làm:**

1. Lập transition cases: search debounce rồi sort/page; clear khi timer còn
   chờ; resize rồi filter; response cũ kết thúc sau response mới; view reset;
   reload URL; Back/Forward giữa những history entries thật sự tồn tại.
2. Giữ policy `replace`/`preserveState` hiện có. Back/Forward không được test
   bằng giả định mỗi filter tạo history entry mới. Server normalization vẫn là
   applied state; draft mới hơn phải có quy tắc đồng bộ rõ.
3. Chặn stale callback làm tắt loading của request mới, clear/reset không bị
   debounce phục hồi giá trị cũ; dispose timers/listeners đúng khi unmount.
4. `aria-busy`/loading indicator phản ánh request đang chạy, không nháy layout
   hoặc làm mất focus input đang gõ. Không disable toàn bộ bằng pointer overlay
   rồi để keyboard tiếp tục gây double submit. Rà từng control theo ý nghĩa.
5. Cancel/error không kẹt busy; không tự retry mutation. Giữ page/cursor reset,
   unrelated query params, table namespace và scoped reloadProps.

**Kiểm chứng:** focused navigation/view/URL tests; hai-table browser scenario;
URL bridge nếu serialization/normalization thay đổi. So sánh request count.

**Done:** UI và URL hội tụ về state đúng của thao tác cuối; lỗi/cancel hồi phục
được; thao tác bảng A không cập nhật state/loading của bảng B ngoài chủ đích.

### UI03 — Keyboard, labels và focus của overlays

**Scope:** toolbar, headers, rows, filters, pagination, action/view dialogs,
table composition; giữ vendored `components/ui` theo ranh giới hiện có.

**Làm:**

1. Duyệt từ search tới filter, sort, visibility, resize, pagination, row/bulk
   action bằng bàn phím. Mỗi control có accessible name, focus nhìn thấy và
   không dựa vào placeholder/icon/tooltip làm nguồn tên duy nhất.
2. Rà semantic table/header, `aria-sort`, selection state và label phân biệt
   từng row. Đừng chuyển thành ARIA grid nếu chưa có mô hình tương tác grid.
3. Escape/close trả focus về trigger còn tồn tại; nested dropdown/popover không
   giành focus hoặc đóng nhầm. Dialog có title/description, focus trap đúng;
   trigger bị xóa có fallback hợp lý trong cùng table.
4. Resize/reorder hiện có có đường dùng keyboard và labels phản ánh trạng thái;
   disabled state được truyền xuống native/primitive semantics phù hợp.
5. Rà status announcements vừa đủ, không đọc lại toàn bộ table sau mỗi ký tự.
   Kiểm tra thủ công bằng screen reader sẵn có cho journey tối thiểu.

**Kiểm chứng:** semantic/interaction tests theo role/name và browser keyboard;
ghi checklist screen-reader thực chạy. Không tuyên bố đạt chuẩn accessibility
toàn diện chỉ vì automated scan không báo lỗi.

**Done:** các tác vụ chính hoàn tất không cần chuột; focus và nhãn đủ hiểu ở
cả trạng thái bình thường, loading, disabled và dialog.

### UI04 — Layout responsive và theme nhất quán

**Scope:** `styles/data-table.css`, toolbar/viewport/pagination/header/cell và
filter/table composition; `docs/customization/styling.md` nếu cần cập nhật.

**Làm:**

1. Kiểm tra 320, 375, 768, 1280px và zoom 200%. Table được horizontal scroll
   trong viewport; toolbar/control không tràn page hoặc che pagination.
2. Rà spacing, control height, typography, focus/hover/disabled trên các UI
   hiện có. Dùng host theme tokens; kiểm chứng light/dark và label dài tiếng Việt.
3. Rà selector global như `[data-slot="table-container"]`: tái hiện ảnh hưởng
   lên bảng host không thuộc package trước khi scope CSS về `.tb-*`/wrapper.
4. Sticky header/footer/columns, resize, pin và menu portal không bị cắt hoặc
   chồng sai. Kiểm tra RTL vì code đã dùng logical insets; không thêm locale mới.
5. Tôn trọng reduced motion; giữ existing CSS variables/classes đã public.
   Không tạo theme configuration API hoặc sao chép primitive để đổi thẩm mỹ.

**Kiểm chứng:** ảnh before/after trên scenario đã cố định, scroll/resize bằng
browser, host table đối chứng ngoài wrapper, component tests semantic liên quan.

**Done:** layout usable trên viewports nêu trên, theme override cũ hoạt động,
không có style rò vào phần app không thuộc table.

### UI05 — Feedback action/export/Saved View có kết quả rõ

**Scope:** action/export/view components, `useActions.ts`, `useExports.ts`,
`useViews.ts`, `i18n.ts`; đối chiếu controller/status contract, không đổi schema.

**Làm:**

1. Ghi UX cho idle, submitting, accepted/queued, processing, success, validation
   error, forbidden/conflict, generic failure và cancel ở từng thao tác hiện có.
2. Chặn duplicate submit đúng control; pending kết thúc đúng lifecycle. UI
   không báo thành công khi mới dispatch; không đóng editor và làm mất dữ liệu
   khi server báo validation error nếu editor còn là nơi sửa lỗi.
3. Với Saved View rename/create, kiểm tra server errors và version conflict;
   giữ draft/name để user sửa hoặc reload, không tự ghi đè view của người khác.
4. Selection clear/preserve theo contract hiện có; dismiss queue dialog không
   tự hủy job hay đổi polling semantics. Export fail không mất selection.
5. Dùng error/state/callback đã có; labels package đi qua i18n. Nội dung lỗi
   kỹ thuật riêng của host đi qua handler host, không dựng notification system
   mới. Nếu thiếu public contract để sửa hợp lý, báo rõ phần cần quyết định.

**Kiểm chứng:** action/export/view unit và component tests; browser tối thiểu
confirmation, validation failure, queued status và view conflict trên fixture
an toàn. Không cần dựng worker infra production cho một UI test.

**Done:** user biết thao tác đang làm gì và sửa/đóng/thử lại bằng đường nào;
không có false success, double submit hoặc mất draft đã tái hiện.

## 7. Task cards — khả năng bảo trì

### M01 — Test environment tái sử dụng mà không rò lifecycle

**Scope:** `vite.config.ts`, `tests-js/inertiaMock.ts`, `tests-js/harness.ts`,
test setup mới nếu cần và các component tests đang tự mount.

**Làm:**

1. Dùng cleanup chung unmount wrappers trước khi dọn DOM/reset globals; dispose
   scopes, fake timers, spies, cookies/head và module-level configuration đã đổi.
   Chỉ cleanup state mà test đang dùng, không quét xoá toàn environment mù quáng.
2. Inertia mock giữ object identity khi module cache dùng chung nhưng reset
   state giữa tests; bổ sung lifecycle callbacks/listener unsubscribe chỉ khi
   test cần. Không mô phỏng toàn bộ router thành implementation thứ hai.
3. Đảm bảo test tự setup những gì nó cần; không dựa vào file khác đã gọi reset.
   Giữ alias mock chỉ cho unit/component config; consumer DX02 dùng adapter thật.
4. Chạy toàn suite với một worker và đảo thứ tự file theo hai seed cố định.
   Nếu order failure: tái hiện rồi sửa cleanup, không đổi assertion để che lỗi.

**Kiểm chứng:** `npm test`, typecheck và lệnh shuffle mục 9; hai Node CI hiện có.

**Done:** không order-dependent failure, pending timer/listener sau unmount
được kiểm chứng; không quay lại `vmThreads` khi Node matrix chưa hỗ trợ đường đó.

### M02 — Thu gọn useTable theo state ownership

**Scope:** `useTable.ts`, internal modules mới dưới `resources/js/` nếu cần,
`useTable.test.ts`; không đổi `index.ts` runtime exports.

**Làm:**

1. Dựa trên UI01/UI02, đánh dấu state thuộc navigation, layout và lazy options;
   liệt kê input/output/lifecycle của từng vùng.
2. Chọn tối đa một vùng extraction cho PR đầu: ưu tiên pure layout normalization
   hoặc nhóm layout state đã có boundary rõ. Không tách theo số dòng.
3. `useTable` giữ facade và một đường điều phối navigation; module mới nhận
   dependency hẹp, không import ngược toàn facade, không thêm global state hoặc
   DOM/shadcn vào headless navigation core.
4. Giữ watch timing, debounce, exposed refs/methods và callback order. Nếu phải
   truyền phần lớn facade qua lại để tách, dừng extraction và ghi `no-change`.

**Kiểm chứng:** UI01/UI02 regressions trước/sau giữ nguyên expectations, type
consumer và build. Không tái cấu trúc test theo tên hàm private mới.

**Done:** có responsibility map dễ theo dõi; extraction có lợi hoặc kết luận
`no-change` có bằng chứng. File ngắn hơn chưa đủ để coi là hoàn tất.

### M03 — Selection và action execution có ranh giới rõ

**Scope:** `useActions.ts`, helper internal nếu cần; `useActions.test.ts`,
Confirmation/queued dialog tests và headless consumer.

**Làm:**

1. Lập invariants selection: explicit keys, all matching/exclusions, anchor
   theo row key, unselectable rows, reset theo result identity, selected count.
2. Xem xét tách selection calculations/state thành internal unit nhận resource
   và row-key resolver. Giữ `useActions` public return và callback signatures.
3. Giữ confirmation count, permission/availability, accepted-202 selection
   reset và queued polling. Không gộp action/export thành generic operation
   engine; chúng có lifecycle/callback contracts khác nhau.
4. Review dependency direction/disposal. Không giảm khả năng đọc chỉ để tái
   sử dụng vài dòng hoặc tạo helper chỉ chuyển tiếp arguments.

**Kiểm chứng:** existing selection/range/action regressions, UI05 và consumer
types. Regression mới chỉ cho boundary hiện chưa được bảo vệ.

**Done:** selection ownership rõ, hành vi không đổi; extraction không có lợi
được kết luận `no-change` thay vì mở rộng phạm vi.

### M04 — PHP hotspots và extension hooks

**Scope:** `Table.php` trước; chỉ mở `Column`, `Action`, `Export`, `Views` khi
có coupling liên quan. Tests query, hooks, resource, exports/selection tương ứng.

**Làm:**

1. Lập map `resolve → state → query → paginate → serialize` và những đường reuse
   bởi selection, summary, Saved Views, exports. Phân biệt private helper với
   protected/public extension hook thật.
2. Dùng tests hiện có; thêm characterization cho hook override/callback order
   còn thiếu trước khi move code. Giữ method visibility/signature/named args và
   thứ tự gọi có nghĩa với consumer subclass.
3. Chọn một duplication hoặc coupling đã đo/đã gây khó sửa. Ưu tiên gom trong
   private helper hiện có; chỉ extraction service nếu dependency boundary rõ.
   Không mặc định tạo `TablePagination`, state pipeline hoặc registry.
4. Không cache definitions/query toàn cục: authorization, request, connection,
   locale và attributes có thể khác mỗi lần. Giữ shared query hook, dedup khi
   joins, cursor tie-breaker, scope và connection/transaction semantics.
5. Queue fingerprints/snapshots/persistence nằm ngoài refactor cơ học này;
   đụng tới chúng cần card riêng với regression compatibility cụ thể.

**Kiểm chứng:** focused Pest và PHPStan, resource/URL contracts; SQL thay đổi
thì xác nhận SQLite/MySQL/PostgreSQL qua jobs liên quan, không chỉ SQLite local.

**Done:** map hooks chính xác và một improvement có bằng chứng, hoặc `no-change`
giải thích vì sao cấu trúc hiện tại tốt hơn phương án tách đã xem xét.

### M05 — CI kiểm tra đúng các đường contract

**Scope:** `run-contract-tests.yml`, `run-js-tests.yml`, `run-tests.yml`,
`release.yml`, manifests khi thêm focused script; consumer DX02.

**Làm:**

1. Rà paths trigger của URL/resource/consumer checks theo source thật. Serializer
   PHP đổi phải được gate phù hợp, không chỉ khi TS types hay fixture đổi.
2. Release gate chạy URL bridge bên cạnh resource freshness, PHP/JS và docs;
   tái sử dụng workflow hiện có. Required check khác với workflow tồn tại:
   branch protection cần xác minh riêng nếu muốn enforce trước merge.
3. Wire consumer install/type/SSR smoke vào CI ở matrix nhỏ đại diện; giữ Node
   20/22 và PHP/DB matrix hiện có. Không nhân toàn bộ browser × DB × framework.
4. Shuffle single-worker là check nhắm test isolation, chỉ thêm job nhỏ/command
   hợp lý. Ghi failure seed để tái lập; không retry vô hạn hoặc ignore flaky.
5. Đánh giá peer range Inertia 2/3, Vue và build consumers đã được kiểm chứng ở
   đâu; ghi gap và chọn representative check, không tuyên bố full matrix nếu
   chỉ cài phiên bản mới nhất.

**Kiểm chứng:** YAML/workflow review, chạy scripts local, CI thực trên commit
cuối khi có quyền push. Test gate bắt artifact thiếu/sai bằng thử nghiệm cục bộ
đã khôi phục; không commit fixture sai hoặc publish package để thử.

**Done:** PR/release source liên quan không lách contract gate do paths thiếu;
consumer không dùng unit mock; reports không lẫn source test với integration.

### M06 — Tài liệu cho maintainer và compatibility

**Scope:** `CLAUDE.md`, `docs/internals/architecture.md`, `development.md`,
`api-stability.md`, `releasing.md`, `UPGRADING.md` và changelog khi có thay đổi.

**Làm:**

1. Cập nhật responsibility map theo kết quả M02–M04; bỏ hướng dẫn restart từ
   spike khỏi phần vận hành hiện tại hoặc đánh dấu rõ là lịch sử.
2. Kiểm tra PHP/Vue contract examples không drift với types/resource; ưu tiên
   link canonical reference khi copy shape đầy đủ gây lặp nguồn sự thật.
3. Ghi test ownership: PHP query/state, bridge, headless, renderer, packed
   consumer/browser. Thêm hướng dẫn reproduce bug và focused commands thực có.
4. Giữ SemVer/deprecation/persistence policy hiện có, bổ sung migration chỉ cho
   thay đổi thật. Tránh rewrite policy để hợp thức hóa breaking refactor.
5. Đảm bảo public CSS/hooks/types có chủ đích; không đánh dấu API đang được
   document/export là internal chỉ để giảm trách nhiệm bảo trì.

**Kiểm chứng:** đối chiếu source, chạy commands mới nếu có, docs build và links.

**Done:** maintainer mới biết mở file nào, chạy check nào và boundary nào giữ;
không còn hướng dẫn lịch sử gây hiểu nhầm là công việc phải thực hiện lại.

### M07 — Performance có số đo trước khi tối ưu

**Scope:** fixtures/catalog, vùng source bottleneck được xác nhận; không nâng
dependency hoặc bật cache rộng chỉ vì tool gợi ý.

**Làm:**

1. Ghi warm/cold runs riêng, runtime/dataset/machine; lấy median ít nhất ba lần
   cho thao tác đã ổn định. Dùng cùng fixture trước/sau, không chạy benchmarks
   cùng lúc với full test/build gây nhiễu.
2. Đo request count cho search debounce, lazy mở lại, resize release; query count
   cho table thường/relationships/summaries; payload size và browser interaction.
3. Sửa chỉ bottleneck có profiler/reproduction: N+1, query lặp, watcher work,
   render/layout thrash. Giữ scope/auth/locale và semantics đã chốt.
4. CI timing dao động không thành hard threshold máy local. Nếu thêm regression
   gate, ưu tiên deterministic count/correctness và giải thích threshold.

**Kiểm chứng:** số trước/sau, cùng behavior tests và UI scenario; tác vụ query
thay SQL chạy database checks tương ứng.

**Done:** biết bottleneck thực và đã xử lý trong scope, hoặc `no-change` có số
đo cho thấy chưa đáng tối ưu. Không thêm virtualization/cache abstraction.

## 8. Ma trận chấp nhận sản phẩm

Mỗi scenario ghi `pass/fail/not-run`, URL/fixture, commit, browser/runtime,
reproduction và bằng chứng. `not-run` không được tính là pass.

| Scenario | Tiêu chí | Task sở hữu |
| --- | --- | --- |
| Bảng đầu tiên | Docs đủ để tạo/render; không sửa internals/alias package | DX01, DX02 |
| PHP IDE | Callback args/return đúng; fluent chain và subclass hợp lệ | DX03, DX06 |
| Vue IDE | Typed row trong known slots/events; custom slots vẫn hợp lệ | DX04 |
| Cấu hình sai | Nêu table/method/key, yêu cầu hợp lệ, hướng sửa | DX05 |
| SSR/hydration | Server HTML có table, browser hydrate không lỗi | DX02 |
| Search nhanh | Gõ liên tục trong một khoảng debounce chỉ gửi giá trị cuối | UI02 |
| Lazy lần đầu/mở lại | Theo request contract; label/selection giữ, không request lặp vô cớ | UI01 |
| Lazy error/cancel | Busy kết thúc, mở lại thử được, không mất applied state | UI01 |
| Multi-select/range | Chọn nhanh không mất giá trị; range chưa đủ không gửi | UI01 |
| Đóng lúc đang tải | Response không tự mở popover hoặc giành focus | UI01 |
| Filter đã có trong URL | Reload khôi phục state và labels đúng sau lazy load | UI01, UI02 |
| Search/sort/page giao nhau | Timer/response cũ không hồi sinh state đã clear/reset | UI02 |
| Back/Forward | Những entry đang tồn tại khôi phục state đúng policy replace | UI02 |
| Hai table | State/URL/focus/loading độc lập theo phạm vi đã khai báo | UI02 |
| Selection/all matching | Count/exclusions chính xác; reset theo result identity | UI05, M03 |
| Saved View lỗi/conflict | Không false success, không mất draft cần sửa | UI05 |
| Action/export | Pending/accepted/completed phân biệt; không double submit | UI05 |
| Empty và no-results | Đúng server `emptyState`, không CTA create sai do filter | UI03, UI04 |
| Keyboard/screen reader | Hoàn tất main journeys, labels/focus/announcements hữu ích | UI03 |
| Viewport/theme | 320/375/768/1280px, zoom 200%, light/dark, tiếng Việt dài | UI04 |
| Sticky/resize/RTL | Scroll/overlay không cắt, logical offsets và keyboard đúng | UI03, UI04 |
| Host customization | Slots, CSS variables, theme và bảng ngoài package không hỏng | UI04, DX07 |
| Shared worker | Hai thứ tự test khác nhau, một worker, không state rò | M01 |
| Packed consumer | Root exports/style/types dùng được, không phụ thuộc source alias | DX02, M05 |

**Đo onboarding:** mục tiêu 5 phút tính từ một host Laravel/Inertia/Vue/Tailwind
đã chạy, dependencies sẵn sàng, tới bảng đầu tiên render được. Ghi thời gian
chờ download/setup riêng. Agent walkthrough chứng minh recipe chạy được; chỉ
gọi là user validation nếu người chưa biết package đã thử thật. Ghi số bước
bị thiếu/lần phải mở source thay vì tự nhận mục tiêu đã đạt.

**Ưu tiên bug:** P0 là main journey không hoàn tất, mất state/draft, thao tác
không đúng user intent, SSR/consumer install hỏng hoặc contract gate bỏ sót.
P1 là usability/diagnostics cần hoàn thiện. P2 là cleanup và optimization có
thể kết luận không cần đổi code. Lỗi data/auth đã phát hiện phải sửa trước UI polish.

## 9. Commands và chiến lược kiểm chứng

Đã đối chiếu scripts/test filenames với source lúc viết plan; **chưa chạy
baseline suite trong lượt lập plan**. Dùng runtime đáp ứng manifests hiện tại.

### Baseline Q00 và final V01

```sh
git status --short
git rev-parse --short HEAD
php -v
node --version
composer test
composer analyse -- --no-progress
vendor/bin/pint --test
npm run format:check
npm run types:check
npm test
npm run build
npm run docs:build
git diff --check
```

Database cho Pest phải là database test dùng riêng: `tests/TestCase.php` có
`Schema::dropAllTables()`. Không dùng DB của playground/application. Local mặc
định SQLite memory không chứng minh query compatibility trên mọi driver.

### Contract bridge theo đúng chiều hiện tại

```sh
# PHP resource -> committed TypeScript fixture: verify freshness
vendor/bin/pest tests/ContractResourceTest.php

# TypeScript URL -> generated artifact -> PHP normalized state
INERTIA_TABLE_URL_CONTRACT_OUTPUT=build/contracts/urls.json npm test -- tests-js/contractUrl.test.ts
INERTIA_TABLE_URL_CONTRACT_INPUT=build/contracts/urls.json vendor/bin/pest tests/ContractUrlTest.php
```

Chỉ khi contract thay đổi có chủ đích mới regenerate resource fixture bằng
`INERTIA_TABLE_UPDATE_CONTRACTS=1`, review diff rồi verify lại. Không edit tay
`tests-js/contracts/resource.generated.ts` hoặc dùng regeneration để bỏ qua
regression. URL artifact trong `build/` không commit.

### Shared-worker test order

```sh
npm test -- --maxWorkers=1 --sequence.shuffle.files --sequence.seed=17
npm test -- --maxWorkers=1 --sequence.shuffle.files --sequence.seed=83
```

Flags đã được đối chiếu `vitest --help --sequence` ở version đang cài. Chạy
kiểm tra này cho M01/final hoặc khi test setup/global state đổi, không sau mỗi
lần sửa docs.

### Focused checks giữa các task

- PHP bug: test liên quan, scoped Pint, PHPStan khi type/signature/cross-cutting
  đổi. Dùng regressions query/connection/hook hiện có trước khi thêm test mới.
- Vue bug: test file liên quan, typecheck; build/consumer khi thay exports,
  declarations, styles hoặc runtime imports. Browser cho focus/geometry/SSR.
- Refactor: tests giữ nguyên expectations trước/sau. Không đổi test chỉ để
  hợp tên helper mới và không tạo test rỗng “function tồn tại”.
- Docs: verify samples/commands sửa, docs build, links. Không cần full PHP suite
  chỉ vì sửa câu chữ. Format chỉ các file vừa thay đổi.
- Full compatibility matrix dùng CI hiện có ở gate phù hợp; report chính xác
  job/commit, không suy từ local pass ra CI xanh.

## 10. V01 — Điều kiện hoàn tất và bàn giao

1. Mỗi task có `done`, `no-change` hợp lệ hoặc blocker được liệt kê rõ;
   product-critical blocker chưa giải quyết thì chưa hoàn tất giai đoạn.
2. Ma trận mục 8 có kết quả và bằng chứng; bugs đã tái hiện có regression phù
   hợp, browser findings có kiểm chứng lại sau fix.
3. Chạy final checks mục 9 trên diff cuối; packed consumer/CSR/SSR và hai
   shared-worker orders. CI matrix/gates theo M05 có kết quả trên commit cuối
   khi có quyền chạy; chưa chạy phải ghi rõ.
4. So sánh public surface Q00 với cuối: signatures/named params, overrides,
   exports, slot payloads, resource/URL, Saved Views/queue snapshots và CSS.
   Mọi thay đổi cần migration có quyết định và ghi chú, không giấu dưới refactor.
5. Báo cáo trước/sau bằng onboarding steps, issues fixed, type examples,
   behavior matrix, dependency directions và số đo M07. Không chấm điểm cảm tính.
6. Docs/recipes đúng phiên bản; maintainer guide có ownership/test commands.
   Ghi rõ status local diff, commits, PR/merge/deploy thực tế. Không tự publish
   package/release chỉ để kết thúc plan.

### Prompt giao việc cho model triển khai

```text
Thực hiện task <ID> trong docs/dx-ui-maintainability-plan.vi.md.
Đọc mục 1–4, card được giao và commands liên quan; giữ feature freeze.
Đọc source/tests hiện tại và hướng dẫn repo áp dụng, không audit lại toàn repo.
Kiểm tra kết quả dependencies đã hoàn tất. Giữ thay đổi chưa commit của user.
Với bug, tái hiện rồi sửa nhỏ nhất; với refactor, giữ public behavior và tests.
Không thêm remote filter, Apply button, public feature hoặc dependency ngoài scope.
Chạy focused checks; nếu cần browser thì dùng fixture/catalog của plan.
Cập nhật trạng thái/bằng chứng của đúng card và báo theo mẫu bên dưới.
Nếu extraction không đem lại lợi ích, ghi no-change với lý do thay vì ép tách.
```

### Mẫu handoff mỗi task

```text
Task: <ID> — done | no-change | blocked
Base/HEAD và repo:
Vấn đề đã tái hiện hoặc mục tiêu:
Files thay đổi và hành vi trước/sau:
Public contract có ảnh hưởng: không / mô tả cụ thể
Checks đã chạy: command, pass/fail/skip và artifact/browser evidence
Checks chưa chạy và lý do:
Dependencies/blocker còn lại:
Task tiếp theo đủ điều kiện:
```

## Phụ lục A — Sổ baseline và quyết định triển khai

Phần này để model thực thi ghi kết quả thật, không điền bằng phỏng đoán.

| Mục | Giá trị |
| --- | --- |
| Baseline HEAD / working tree | Chưa ghi — Q00 |
| Runtime / installed dependencies | Chưa ghi — Q00 |
| Package / playground / packed consumer revision | Chưa ghi — Q00, DX02 |
| Docs public deploy status | Chưa xác minh trong lượt lập plan — DX01 |
| Baseline commands và failures | Chưa chạy — Q00 |
| Onboarding walkthrough / user validation | Chưa chạy — DX01, DX02, DX07 |
| UI scenario catalog / before evidence | Chưa tạo — UI00 |
| Final checks / CI commit / after evidence | Chưa chạy — V01 |

Decision log chỉ ghi các lựa chọn ảnh hưởng contract, state ownership, test
strategy hoặc lý do `no-change`. Không biến phụ lục thành transcript tool logs.
