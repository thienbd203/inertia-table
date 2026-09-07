# Kế hoạch triển khai theo task — Musing Inertia Table

Ngày: 2026-09-06. Mốc source ban đầu đã đọc: `426ae1b`.

> **Trạng thái cập nhật sau triển khai:** toàn bộ task bắt buộc B00, F01–F04, C01–C02, Q01–Q05, R01–R03, J01–J02 và V01 đã hoàn tất trên nhánh `refactor/table-hook-safety-net`, tại `1968576`. Các card bên dưới được giữ làm hồ sơ scope, quyết định và kiểm tra; không còn là backlog cần triển khai lại.
>
> Các việc tách riêng cũng đã được xử lý: D01 có policy và regression; D02–D04 được audit, xác nhận safeguards hiện có đã thỏa yêu cầu nên không tạo diff hành vi; O01–O04 đã triển khai khi có use case rõ ràng. Chuỗi commit triển khai bắt đầu từ `f57d86f` và kết thúc ở `1968576`.
>
> Kiểm chứng cuối: local chạy `composer test` (221 passed, 1 skipped contract test không có generated artifact), PHPStan, Pint, focused JS pagination tests; GitHub Actions của `e59bef7` đã xanh cho PHPStan, style và ma trận SQLite/MySQL/PostgreSQL. Các workflow JS và PHP↔TypeScript contract đã xanh ở commit frontend gần nhất `afe8c4b`.

Workspace tham chiếu: `/Users/thienbd/Desktop/Personal/toolbelt-inertia-table`. Các đường dẫn bên dưới tính từ root repository; tìm theo tên method/test khi line numbers thay đổi.

Tài liệu này chứa các quyết định kiến trúc đã thống nhất sau khi đối chiếu hai plan và hướng dẫn thực thi chi tiết. Model triển khai chỉ cần đọc quy tắc chung, task được giao và các source/test files được liệt kê; không cần các file plan cũ hoặc audit lại toàn repo.

## 1. Kết quả cần đạt và phạm vi đã chốt

- Sửa các lỗi enum/input, remote option ordering, transaction connection và queue lifecycle có regression cụ thể.
- Có kiểm tra PHP resource → TypeScript consumer và URL frontend → PHP normalized state thực sự liên thông.
- Gom phần lặp trong các class hiện có: aggregate expressions vào `SummaryAggregate`; pagination/capabilities/options vẫn trong `Table`; Saved View mutations về `Views`; clause predicates về `filters.ts`.
- Thu hẹp dependency type của `useExports`.
- Giữ public fluent APIs, named parameters, subclass hooks, query scope, resource v2 và persistence compatibility.

Không tạo `SummaryQuery`, `TablePagination`, `OperationContext`, generic operation repository, renderer registry hoặc state-provider pipeline trong các task bắt buộc. Không đổi `resolveState()`/`stateRequest()` thành protected. Không gộp hai context interfaces. Không coi remote option query là authorization boundary của table data. Cursor expression sorts tiếp tục là tổ hợp không được hỗ trợ.

Các ý tưởng `usePolling`, `fromArray()`, clause metadata và cache helper là backlog tùy chọn. Không cần làm chúng để hoàn tất kế hoạch chính.

## 2. Cách giao việc để giảm chi phí

1. Mỗi lượt giao một task, hoặc một nhóm nhỏ đã nêu ở bảng dưới. Không gửi yêu cầu chung “refactor toàn package”.
2. Đọc file đích và tests trực tiếp trước; chỉ mở dependency khi đang giải quyết một câu hỏi cụ thể. Không lặp audit và không tạo thêm plan lớn.
3. Với bug: viết regression, chứng minh fail vì lỗi dự kiến, fix nhỏ nhất, chạy lại tests liên quan. Với refactor: dùng coverage đã đủ, chỉ bổ sung characterization còn thiếu; tests không đổi expectations sau khi move code.
4. Sau một task, chạy focused tests và checks liên quan. Full suites chạy ở baseline/final hoặc khi thay đổi ảnh hưởng rộng; không chạy coverage, build và toàn DB matrix sau mỗi lần sửa một dòng.
5. Không đổi dependency versions/lockfiles, formatter toàn repo, namespace hoặc APIs ngoài task để làm suite xanh. Ghi nhận lỗi môi trường/baseline riêng.
6. Các task cùng sửa `Table.php`, jobs hoặc dispatchers phải chạy tuần tự. Không để nhiều model ghi cùng file. User chọn model nào thực hiện; tài liệu không yêu cầu thêm agent hoặc trả phí cho dịch vụ khác.
7. Báo cáo kết thúc ngắn theo mẫu mục 10. Không lặp lại toàn tài liệu hoặc đổ toàn bộ test logs vào handoff.
8. Chỉ commit/mở PR/push khi task được giao có yêu cầu đó. Có thể hoàn thành một diff cục bộ và báo cáo kiểm tra mà chưa commit.

### Nhóm giao việc

| Nhóm | Task IDs | Mức cần suy luận/review | Ghi chú chi phí |
|---|---|---|---|
| Baseline | B00 | Nhỏ | Làm một lần cho checkout đang triển khai |
| Type/input fixes | F01 + F02 | Nhỏ | Hai diff độc lập; chung PHP tooling |
| Views connection | F03 | Vừa | Có database rollback test thật |
| Remote pagination | F04 | Vừa | Đọc query và cursor behavior; cần SQL regression |
| Contract bridge | C01 rồi C02 | Vừa–cao | Tách fixture freshness khỏi round-trip runtime |
| Public queue errors | Q01 | Vừa | Đổi public message có chủ đích |
| Reservation errors | Q02A rồi Q02E | Cao | Cùng ý tưởng nhưng khác callbacks/status contracts |
| Expiry/execution | Q03 rồi Q04 | Cao | Review kỹ terminal/file/lock races; không gộp với abstraction |
| Export locale | Q05 | Vừa–cao | Phải đọc serialized snapshots cũ |
| Summary | R01 | Nhỏ–vừa | Chỉ expression mapping, giữ một query |
| Table helpers | R02A + R02B | Vừa | Cùng file, review hai logical diffs riêng |
| Views ownership | R03 | Vừa | Sau connection fix, move behavior nguyên trạng |
| Frontend cleanup | J01 + J02 | Nhỏ–vừa | Chung JS tooling; không đổi polling |
| Final validation | V01 | Vừa | Full required checks một lần trên kết quả cuối |

Không có yêu cầu dùng model mạnh nhất cho mọi nhóm. Các nhóm queue cần review tập trung vào state transitions và compatibility; việc nhỏ không cần mang toàn bộ ngữ cảnh queue vào lượt triển khai.

## 3. Thứ tự và phụ thuộc

Mặc định thực hiện:

`B00 → F01/F02/F03/F04 → C01 → C02 → Q01 → Q02A → Q02E → Q03 → Q04 → Q05 → R01 → R02A → R02B → R03 → J01 → J02 → V01`.

Dấu `/` chỉ các task không phụ thuộc nhau, không phải yêu cầu chạy đồng thời. Một bugfix đã có regression không phải đợi toàn bộ contract infrastructure. Nhóm frontend J01/J02 có thể làm sớm sau baseline; giữ R01/R02/R03 sau characterization liên quan.

| Task | Phụ thuộc bắt buộc | Map sang plan kiến trúc |
|---|---|---|
| B00 | Không | P0 baseline/docs |
| F01, F02, F03, F04 | B00 | P1; F03 là P7 được đưa lên sớm |
| C01 | B00 | P0 contract fixture |
| C02 | C01 | P0 URL round-trip |
| Q01 | B00 | P2 |
| Q02A | B00 | P3 reservation action |
| Q02E | Q01; xem kết quả Q02A | P3 reservation export |
| Q03 | Q01, Q02E | P3 expiry/terminal behavior |
| Q04 | Q03 | P3 concurrent execution/cleanup |
| Q05 | Q01, Q04 | P3 locale/serialized compatibility |
| R01 | B00 + characterization summary; C01 khuyến nghị | P4 |
| R02A, R02B | C01/C02 + hook characterization | P5 |
| R03 | F03 | P6 |
| J01, J02 | B00 | P8/P9 |
| V01 | Các task bắt buộc đã chọn ở trên hoàn tất | P9/release checks |

Mapped-sort fallback, bulk authorization, fingerprint policy và remote value allowlist nằm ở D01–D04 (mục 9), không được âm thầm xử lý như một phần task bắt buộc. Ghi chúng là deferred khi bàn giao, không gọi chúng là đã sửa.

## 4. Quy tắc kiểm tra và môi trường

Các lệnh dưới đây lấy từ `composer.json`, `package.json`, `vite.config.ts` và workflows hiện tại. Chưa chạy chúng trong bước viết kế hoạch này.

### 4.1. Kiểm tra đầu lượt

```sh
git status --short
git rev-parse --short HEAD
php -v
node --version
```

Đọc `AGENTS.md` áp dụng nếu có ở checkout mới. Giữ nguyên thay đổi chưa commit của user. Mốc source khác `426ae1b` không tự là blocker: đối chiếu symbols/task scope, ghi khác biệt có ảnh hưởng; không reset checkout về mốc audit.

PHP cần thỏa `^8.3`; Node cần thỏa `^20.19.0 || >=22.12.0`. Dùng dependency runtime đã có. Chỉ cài dependencies khi thiếu và được phép trong môi trường triển khai; không dùng `composer update` để vô tình nâng cả cây dependencies trong task sửa code. CI đang tự giải matrix dependencies theo workflow, đó là công việc của CI.

**Database test phải là database dùng riêng, được phép xóa.** `tests/TestCase.php::setUp()` gọi `Schema::dropAllTables()`. Không trỏ `DB_*` sang application/production database. SQLite mặc định là `:memory:`; tests connection khác phải tạo connection test riêng và cleanup sau test.

### 4.2. Focused checks

```sh
vendor/bin/pest tests/ColumnSerializationTest.php
vendor/bin/pint --test src/Columns/BadgeColumn.php tests/ColumnSerializationTest.php
composer analyse -- --no-progress
npm test -- tests-js/filters.test.ts tests-js/useFilterEditor.test.ts
npm run types:check
```

Thay danh sách file theo từng card. Nếu formatter cần sửa, chỉ format files vừa thay đổi, rồi chạy `--test`/`--check` lại. `npm run format` và `composer format` hiện format rộng; không chạy chúng mặc định cho diff nhỏ.

### 4.3. Baseline và final checks

```sh
composer test
composer analyse -- --no-progress
vendor/bin/pint --test
npm run format:check
npm run types:check
npm test
npm run build
git diff --check
```

Không coi các lệnh local này là đã chạy full compatibility matrix. Trước release, dùng required workflows hiện có cho Laravel/Inertia/PHP, Node và MySQL/PostgreSQL; SQL tasks cần results trên drivers liên quan. Cache concurrency cần shared-cache tests riêng của Q04.

Build/test có thể tạo `dist`, `.phpunit.cache`, `build/report.junit.xml`; đây là output tooling, không phải source cần commit. Các plan dưới `docs/` hiện bị `.gitignore` bỏ qua: không force-add plan, sửa ignore hoặc commit roadmap ngoài yêu cầu của user. Fixtures mới phải đặt trong đường dẫn test được theo dõi.

## 5. Task cards — baseline và bugfix nhỏ

### B00 — Baseline và sửa tài liệu contract bị lệch

**Đọc:** `composer.json`, `package.json`, `.github/workflows/run-tests.yml`, `run-js-tests.yml`, `docs/api-stability.md`, `docs/architecture.md`, `docs/customization.md`, `UPGRADING.md`, `CLAUDE.md`; chỉ mở source tương ứng khi kiểm chứng câu trong docs.

**Thực hiện:**

1. Ghi HEAD, trạng thái working tree, runtime/dependencies có sẵn. Chạy baseline mục 4.3 một lần nếu môi trường đáp ứng; lưu tóm tắt pass/fail và command.
2. Sửa mô tả schema Saved View hiện là 2 và tương thích đọc cũ theo code; phân biệt root resource version với persistence version.
3. Sửa mô tả icon resolver: tên icon không tự resolve nếu chưa có resolver; giữ documentation của slots/custom rendering.
4. Phân biệt exported `Ui*` symbols với filesystem layout internal. Không đánh dấu public methods đang được ứng dụng dùng thành internal chỉ để giảm nghĩa vụ BC.
5. Cập nhật mô tả scripts/CI theo files hiện có. Không nâng version package hoặc dependencies.

**Kết quả:** docs chính xác, baseline failures được phân loại, production source không đổi. Không kết luận có coverage mới từ việc đọc test names. Nếu dependency/tool bị thiếu, báo phần chưa kiểm tra thay vì sửa workflow để bỏ check.

### F01 — BadgeColumn nhận Variant trực tiếp

**Scope:** `src/Columns/BadgeColumn.php`, `tests/ColumnSerializationTest.php`.

**Thực hiện:**

1. Tạo model test tối thiểu có attribute của badge. Test `variant(Variant::Success)` trả cell meta `'variant' => 'success'`; trước fix phải tái hiện `TypeError`.
2. Thêm `Variant` vào union của private `resolveMappedProperty()`. Không đổi public `variant()` signature hoặc cách chuyển enum thành value trong `resolveCellMeta()`.
3. Kiểm tra variant string, map trả enum/string, closure trả enum/string và map không khớp vẫn giữ behavior. Không mở helper public/protected.

**Chạy:** `vendor/bin/pest tests/ColumnSerializationTest.php`; scoped Pint; PHPStan nếu signature change gây cảnh báo.

**Done:** enum trực tiếp chạy được, strings/maps/closures không đổi, diff chỉ helper type và regression phù hợp. Không cần build frontend.

### F02 — Range normalization không truy cập index chưa tồn tại

**Scope:** `src/Filters/NumericFilter.php`, `src/Filters/DateFilter.php`, `tests/FilterRangeTest.php`.

**Policy đã chọn:** range phải có đúng hai phần tử và cả keys `0`/`1`; associative/sparse input không đáp ứng trả `null`. Chỉ thêm guard trước index access; giữ cách dựng output hiện tại cho input đã hợp lệ, kể cả insertion order khác. Không tự `array_values()` trước validation để biến malformed input thành range hợp lệ.

**Thực hiện:**

1. Regression cho `[]`, một/ba phần tử, `[1 => value, 2 => value]`, `['from' => value, 'to' => value]`, keys thiếu `0` hoặc `1`. Bao phủ `between` và `not_between`.
2. Check shape/keys trước truy cập. Numeric tiếp tục dùng `is_numeric()` và chuyển numeric strings như hiện tại; date tiếp tục chấp nhận hai strings theo policy hiện có.
3. Giữ `[0, '10']`, negative/decimal numeric values, range đảo chiều và date strings hiện hợp lệ. Không thêm date parsing, đổi timezone, reorder endpoints hoặc mở rộng ngày cuối range trong PR này.
4. Test qua table query: malformed range bị disable/ignore như incomplete range hiện tại và không phát warning.

**Chạy:** `vendor/bin/pest tests/FilterRangeTest.php tests/TableStateTest.php`; scoped Pint và PHPStan.

**Done:** không undefined index/warning; normalized output/SQL của range hợp lệ không đổi. Đây là fix validation, không phải refactor shared parser.

### F03 — Saved View transaction dùng connection của model

**Scope:** `src/Http/Controllers/ViewController.php`, `tests/ViewsTest.php` hoặc file focused mới `tests/ViewConnectionTest.php`.

**Thực hiện:**

1. Tạo `TableView` subclass dùng connection test thứ hai, schema tương ứng và table definition cấu hình `Views::modelClass()`.
2. Tạo hai views cùng scope. Trong default switching, gây exception sau khi DB đã cập nhật default cũ nhưng trước khi lưu default mới (ví dụ model event của view đích). Assert DB của connection thứ hai rollback cả hai thay đổi và `lock_version` không đổi.
3. Thay bốn `DB::transaction()` trong update/destroy/setDefault/share bằng transaction trên connection của model/query đang được thao tác. Có thể dùng `$model->getConnection()->transaction(...)`; giữ lock query và writes trên cùng configured model connection.
4. Chạy stale version, scope, sharing và default tests hiện có. Restore config/model listeners, disconnect và cleanup connection test trong finally/teardown.

**Chạy:** `vendor/bin/pest tests/ViewsTest.php` và file mới nếu có; scoped Pint, PHPStan. Chạy driver cases trên test DB thích hợp; SQLite rollback test không chứng minh concurrent row locking của MySQL/PostgreSQL.

**Done:** regression rollback đỏ → xanh, endpoint redirects/error keys/authorization giữ nguyên. Không chuyển mutations vào `Views` trong task này; R03 làm việc đó sau.

### F04 — Remote options có unique tie-breaker

**Scope:** `src/Filters/SetFilter.php`, `tests/RemoteFilterTest.php`; đọc `FilterOptionRequest` và callback API trước khi chỉnh query.

**Thực hiện:**

1. Thêm dataset nhiều option có cùng `name`, custom `orderBy('name')`, page size 2. Theo `nextCursor` tới hết, assert IDs đúng thứ tự tổng thể, không thiếu/lặp.
2. Giữ default order theo configured option value khi chưa có order. Với query trả model rows, bổ sung qualified primary key của model làm tie-breaker nếu chưa được order bằng key qualified/unqualified. Giữ toàn bộ custom orders và direction; tie-breaker ascending giống table cursor hiện tại.
3. Bao phủ custom primary key, custom descending label order và trường hợp key đã nằm trong orders. Không assume attribute luôn tên `id`, không append key hai lần.
4. Giữ option search escaping, dependencies, selected-label hydration và facet count/query budget. Không thêm membership validation của filter value.
5. Với custom select, bảo đảm cursor có các ordered values cần thiết theo Laravel behavior; không thay `select` thành `*` làm lộ dữ liệu. Nếu query grouped/distinct/raw expression không thể bổ sung key mà giữ semantics, ghi case và giới hạn rõ; không tự xây generic SQL rewriter trong task này.

**Chạy:** `vendor/bin/pest tests/RemoteFilterTest.php`; SQL matrix cho cases mới trước merge/release; `npm test -- tests-js/useRemoteFilterOptions.test.ts` nếu endpoint shape bị ảnh hưởng (shape không được đổi).

**Done:** option pages trên dataset tĩnh có ties không mất model rows; payload/authorization/hydration cũ giữ nguyên. Nếu optionValue không unique theo domain, không tuyên bố tie-breaker tự giải quyết deduplication toàn bộ logical options.

## 6. Task cards — contract bridge

### C01 — Fixture PHP thực, freshness check và frontend consumer

**Đọc:** `src/TableResource.php`, `Table::resolve()`, `resources/js/types.ts`, `tests/TableTest.php`, `tests/TestCase.php`, `tests-js/fixtures.ts`, `tests-js/harness.ts`, `tests-js/DataTable.test.ts`, `vite.config.ts`, `tsconfig.json`, hai test workflows.

**Files mới đề xuất:**

- `tests/Support/ContractTable.php` và model/support liên quan nếu cần, namespace test riêng; không import classes định nghĩa trong một test file khác.
- `tests/ContractResourceTest.php`.
- `tests-js/contracts/resource.generated.ts` (fixture được commit, có marker generated).
- `tests-js/contractResource.test.ts`.

**Thiết kế đã chọn:** PHP sinh module TypeScript có object literals `satisfies TableResource<ContractItem>`. Fixture là artifact của producer, không tự viết tay cho khớp TS. Generator chỉ dành cho tests, không thêm production schema generator.

**Thực hiện:**

1. Dựng dataset/table nhỏ nhưng có text/number/badge, filters, bulk action, export, summary, row `_table`, views enabled và case views null. Có separate cases full/simple/cursor/unpaginated; không cần mọi tổ hợp tính năng.
2. Model test tắt timestamps hoặc cố định thời gian, IDs, locale, host, app key và query input. Signed endpoints nếu có dùng key/clock cố định; không strip endpoint, capabilities hoặc fields khó ổn định.
3. PHP test render resource thật qua `resolve()->toArray()`, encode object literal và module wrapper ổn định. Dùng `JSON_THROW_ON_ERROR`; giữ shapes array/object/null do production serialize. So output với fixture đã commit.
4. Mode mặc định chỉ compare và fail nếu stale. Thêm update mode tường minh chỉ cho local, ví dụ `INERTIA_TABLE_UPDATE_CONTRACTS=1 vendor/bin/pest tests/ContractResourceTest.php`; CI từ chối update mode. Sau regeneration luôn chạy lại mode compare.
5. Generated module import type từ `../../resources/js/types`, khai báo `ContractItem` phản ánh dataset rồi dùng `satisfies`. Không dùng `as TableResource`, `as any` hoặc nới union của production types để che mismatch. Nếu phát hiện mismatch thật, báo riêng bug contract và sửa có test/BC review, không đồng thời làm broad refactor.
6. Chốt format deterministic: generator output và Prettier phải thống nhất. Có thể exclude riêng generated fixture khỏi formatting bằng `.prettierignore` với lý do rõ; nó vẫn nằm trong TS checking. Không tắt formatting/typecheck cho folder tests.
7. Frontend test import fixture, mount bằng cơ chế giống `DataTable.test.ts`/harness, kiểm tra actual row value, badge meta, capability behavior, pagination nullability và summary display. Mocks router/network không tự dựng lại resource.
8. Giữ `topicResource()` và fixtures thiếu optional fields. Test minimal resource cũ vẫn render được khi thiếu exports/views/summaries và additive capabilities.
9. PHP workflow phải chạy khi fixture đổi (`tests-js/contracts/**`); JS workflow phải chạy khi PHP producer/support/generator và fixture đổi. Thêm path filters tương ứng cho cả push/pull_request. Không thêm PHP runtime vào mọi Node matrix job chỉ để đọc fixture đã commit.

**Checks:**

```sh
vendor/bin/pest tests/ContractResourceTest.php
npm run types:check
npm test -- tests-js/contractResource.test.ts tests-js/DataTable.test.ts
npm run format:check
```

**Done:** sửa fixture thủ công hoặc để stale làm PHP test fail; producer field sai kiểu làm types check fail sau cập nhật fixture; consumer behavior sai làm Vitest fail. Thử các thay đổi lỗi trong working tree rồi hoàn nguyên chúng trước bàn giao. Không commit mutation thử nghiệm.

**Giới hạn:** exact fixture freshness giữa Laravel/Inertia versions có thể khác ở URLs/paginator output. Kiểm tra trên supported matrix. Nếu là khác biệt framework hợp lệ, dùng variants khai báo theo compatibility target hoặc chạy canonical-generation check trong job target cố định và contract shape checks trên matrix; không loại field hoặc bỏ matrix để giả vờ deterministic.

### C02 — URL do frontend sinh được PHP normalize thật

**Phụ thuộc:** C01. **Scope:** `resources/js/url.ts` và `TableState` chỉ đọc; thêm contract tests/support, một workflow contract nhỏ nếu cần, scripts test nếu hữu ích.

**Files mới đề xuất:** `tests-js/contractUrl.test.ts`, `tests/ContractUrlTest.php`, `tests/Fixtures/url-contract-cases.json`, `.github/workflows/run-contract-tests.yml`. Artifact tạm đặt dưới `build/contracts/`, không commit output mỗi run.

**Thiết kế:** Vitest dùng `tableUrl()` thật sinh JSON chứa URLs; PHP test trong cùng contract job đọc artifact đó, tạo Request và resolve/normalize qua đúng table definition. Input cases và normalized expectations độc lập nằm trong fixture chung. Không dùng PHP test tự viết lại query string.

**Thực hiện:**

1. Cases gồm host params và table thứ hai; search/sort/filter changes; page/cursor reset; `__reset`; scalar/multiple/range/valueless filters; numeric zero, boolean false, dấu phẩy và ký tự JSON; pinned/order/width state ở nơi URL contract hiện hỗ trợ.
2. Chọn assertions theo normalized values hiện có; không ép boolean/string hoặc malformed input sống sót khi server chủ ý normalize/disable.
3. Vitest test luôn kiểm tra URL behavior. Chỉ ghi artifact khi env `INERTIA_TABLE_URL_CONTRACT_OUTPUT` được chỉ định; file schema đơn giản `{cases:[{id,url}]}`, không chứa PHP snippets hoặc arbitrary commands.
4. PHP test chỉ chạy bridge khi env `INERTIA_TABLE_URL_CONTRACT_INPUT` có mặt; local suite thông thường có thể skip bridge với lý do rõ. Trong contract CI job, path được đặt bắt buộc: thiếu/invalid artifact phải fail, không skip. Document đây là check bắt buộc riêng, không tính skipped test là đã kiểm chứng round-trip.
5. Job dùng PHP/Node versions thuộc matrix đã hỗ trợ, cài dependencies theo conventions workflows hiện có. Chạy Vitest trước rồi PHP; JS/PHP source, cases, generator, dependencies và workflow changes phải trigger job.
6. Không thêm Node dependency vào runtime PHP package hoặc Laravel request path. `build/contracts` chỉ phục vụ tests.

**Lệnh sau khi task tạo tests:**

```sh
INERTIA_TABLE_URL_CONTRACT_OUTPUT=build/contracts/urls.json npm test -- tests-js/contractUrl.test.ts
INERTIA_TABLE_URL_CONTRACT_INPUT=build/contracts/urls.json vendor/bin/pest tests/ContractUrlTest.php
```

**Done:** URL được ghi bởi Vitest thực sự được PHP đọc; unknown host params/table khác không bị mất; thay đổi encoding sai làm bridge fail. Additive fields hợp lệ không tự là breaking change.

## 7. Task cards — queue, tách từng thay đổi hành vi

Queue tasks phải giữ action/export cache namespaces và access hashes hiện tại. Không rename snapshot classes. Không tự sửa fingerprint/idempotency semantics để tests dễ pass. Mỗi bug path cần test failure và retry/duplicate response, không chỉ happy path.

### Q01 — Public export failure message an toàn

**Scope:** `src/Jobs/GenerateQueuedExport.php`, `src/Exports/QueuedExportDispatcher.php`, `src/Exports/Export.php` nếu cần policy dùng chung trong export, `tests/QueuedExportTest.php`.

**Policy mặc định:** public message ổn định như `The export could not be completed.`; exception gốc vẫn được rethrow/report và truyền cho failure callback. Không đưa exception message/path/query/credentials vào polling payload.

**Thực hiện:**

1. Regression cho job `failed()` và dispatch exception, sử dụng exception có chuỗi đánh dấu thông tin triển khai. Assert public payload không chứa chuỗi đó; callback nhận đúng Throwable ban đầu.
2. Đặt default message ở owner `Export` hoặc internal policy nhỏ trong module hiện có. Có thể thêm phương thức tương tự `Action::publicFailureMessage()` và fluent override chỉ khi cần tránh breaking consumer customization; không tạo service/interface.
3. Giữ status keys, signed endpoint/auth scope và URL clearing. Failure definition không resolve được vẫn có generic message an toàn.
4. Tránh double reporting hoặc nuốt exception gốc. Ghi thay đổi message trong changelog phù hợp.

**Chạy:** `vendor/bin/pest tests/QueuedExportTest.php tests/ExportTest.php`; scoped Pint/PHPStan.

**Done:** cả dispatcher và worker failure paths không lộ nội dung raw; exception reporting/callbacks vẫn hữu ích. Không chỉnh retries/locks cùng PR.

### Q02A — Action reservation không bị orphan khi chuẩn bị dispatch lỗi

**Scope:** `src/Actions/QueuedActionDispatcher.php`, repository chỉ khi thật sự cần primitive an toàn, `tests/QueuedActionTest.php`.

**Đọc kỹ:** `initialStatus()` cũng chạy `Action::resolve()`, selection count và dispatch redirect callback; chỉ di chuyển `try` quanh `resolvedTags()` là chưa đủ.

**Policy đã chọn:** operation đã reserve mà chuẩn bị dispatch thất bại phải có status `failed` đọc được theo đúng owner; giữ reservation để cùng idempotency key nhận lại cùng terminal result. Không xóa reservation vô điều kiện để chạy lại operation có thể đã dispatch.

**Thực hiện:**

1. Parameterized regression cho label/presentation resolver, selection count query, dispatch redirect, tags, middleware và chain ném trước dispatch; đồng thời giữ test dispatch itself throws.
2. Trước khi chạy callbacks sau reserve, chuẩn bị status fallback tối thiểu từ dữ liệu đã biết và các values không chạy application callbacks: ID, action key, generic label, nullable counters khi cần, deadline, access hash, signed endpoint, safe message fields.
3. Đặt toàn bộ snapshot/status enrichment/job preparation và dispatch sau reserve trong failure boundary. Khi catch, ghi failed từ status tốt nhất đã có, không gọi lại callback vừa lỗi; rethrow original exception.
4. Happy path không chạy callbacks nhiều lần hơn hiện tại. Duplicate path không overwrite status hiện có; callback failure khi chỉ đang đọc duplicate không được làm operation đang chạy của request đầu thành failed.
5. Cache backend lỗi không được xem là đã persist failed thành công; giữ lỗi gốc nếu status write cũng lỗi. Không thể hứa guarantee khi persistence hỏng, báo limitation rõ.

**Chạy:** `vendor/bin/pest tests/QueuedActionTest.php tests/ActionExecutionTest.php`; scoped Pint/PHPStan.

**Done:** retry cùng key nhận cùng ID và terminal failed, không có job enqueue trong pre-dispatch failure; jobs/callbacks successful không chạy thêm. Cache keys/fingerprint/access hash không đổi.

### Q02E — Export reservation failure boundary đầy đủ

**Phụ thuộc:** Q01; dùng cách tổ chức Q02A làm tham khảo, không gộp dispatchers.

**Scope:** `src/Exports/QueuedExportDispatcher.php`, `tests/QueuedExportTest.php`.

**Thực hiện:**

1. Regression cho filename callback, dispatch redirect, chain và dispatch throws sau reserve.
2. Tạo fallback status từ ID/type/table name/deadline/access hash/signed endpoint trước callbacks; fallback filename là tên an toàn không gọi user resolver.
3. Enrichment filename/path/snapshot, callbacks và job dispatch cùng failure boundary. Catch ghi `failed`, `url=null`, message của Q01; giữ idempotency reservation và rethrow original exception.
4. Request duplicate có status hiện có không chạy filename/redirect/chain lần nữa và không reset status. Không thay fingerprint hiện đang không chứa state/selection trong task này.
5. Giữ queue connection/name/delay và chain semantics; không vô tình bỏ job settings khi di chuyển code.

**Chạy:** `vendor/bin/pest tests/QueuedExportTest.php`; scoped Pint/PHPStan.

**Done:** cùng key sau callback failure trả terminal result của cùng ID thay vì dispatched/queued không có job. Snapshot constructor/public wire fields cũ không đổi.

### Q03 — Expiry và terminal guards của queued export

**Scope:** `src/Exports/QueuedExportRepository.php`, `src/Jobs/GenerateQueuedExport.php`, `src/Jobs/CleanupQueuedExport.php`, `tests/QueuedExportTest.php`.

**Transitions mong muốn:**

| Hiện tại | Sự kiện | Kết quả |
|---|---|---|
| dispatched/processing, còn hạn | worker hợp lệ | processing → ready nếu store/delivery thành công |
| ready/failed/expired | duplicate delivery | Không export/notify lại; không reset processing |
| bất kỳ status chưa expired, đã quá deadline | status read | expired, URL và redirect không còn khả dụng; retention có giới hạn |
| snapshot hết hạn | worker bắt đầu hoặc store vừa kết thúc | Không publish ready URL; cleanup output nếu đã tạo |
| ready | cleanup đến hạn | Xóa file và URL, chuyển expired |

**Thực hiện:**

1. Test `expiresAt <= time()` bằng timestamp quá khứ/thời gian test có kiểm soát; chú ý code dùng native `time()`, `Carbon::setTestNow()` đơn thuần không đổi native clock.
2. Repository đọc status quá hạn chuyển expired, clear delivery/redirect/message fields phù hợp và giữ access hash. Không refresh retention vô hạn mỗi lần đọc expired. Giữ retention export hiện có (một ngày sau transition) nếu chưa có policy thay thế.
3. Worker kiểm tra deadline và terminal state trước `processing`, rồi kiểm tra lại trước publish ready để job chạy lâu không hồi sinh expired status.
4. Giữ cleanup job đang có và failure cleanup. Không làm storage deletion trong status GET chỉ để lazy expiry hoạt động; status invalidation và file retention là hai việc riêng.
5. Test duplicate after ready/failed/expired, missing legacy optional fields, cleanup delayed và storage failure. Native cleanup failures phải quan sát được/retry theo queue behavior, không falsely claim file đã xóa.

**Chạy:** `vendor/bin/pest tests/QueuedExportTest.php`; scoped Pint/PHPStan.

**Done:** expiry và sequential duplicate behavior đúng. Chưa tuyên bố concurrent safety chỉ từ task này; Q04 kiểm tra concurrent workers/file cleanup trước khi kết luận queue lifecycle hoàn tất.

### Q04 — Execution lock và races giữa generate/failure/cleanup

**Mức review:** cao. **Scope:** export repository, `GenerateQueuedExport`, `CleanupQueuedExport`, queued tests; test-support/workflow cho shared cache nếu chưa có.

**Boundary:** lock theo operation ID, trong namespace export riêng. Không dùng lock idempotency request làm execution lock, không gộp action/export repositories.

**Thực hiện:**

1. Thêm export `executionLock(id, seconds)` theo Laravel cache lock API. Đọc `ExecuteQueuedAction::handle()`/`executionLockSeconds()` để giữ conventions; không copy terminal policy của action.
2. Acquire trước khi kiểm tra status và store; `finally` release. Khi contention, re-read terminal state: terminal thì return; nonterminal thì release job với delay hữu hạn như convention hiện có, không busy-loop hoặc mark failed.
3. Trong lock, re-read status sau acquire để tránh TOCTOU. Sau store/delivery resolution và kiểm tra deadline, lên lịch cleanup trước khi publish ready và gọi `notifyReady()`. Nếu notification callback lỗi sau ready: giữ file/ready status, report Throwable cho Laravel, không retry toàn bộ export hoặc gọi lại notification trên duplicate delivery. Đây là policy thay đổi có chủ đích, cần regression và changelog riêng trong diff Q04; không mô tả nó là extraction thuần. Nếu dispatch cleanup lỗi trước ready, dùng failure/retry path hiện có và không công bố URL như đã hoàn tất.
4. `failed()` của delivery cũ không được xóa file/overwrite status ready của delivery đã hoàn tất. `CleanupQueuedExport` và generate phải phối hợp cùng operation lock hoặc invariant tương đương để cleanup không xóa giữa store rồi để worker publish URL trỏ file mất.
5. Lock TTL phải có quan hệ rõ với worker timeout/retry_after và thời gian execution hỗ trợ. Convention action là `max(retry_after + 60, 120)`; không coi công thức đó đủ cho exporter chạy lâu hơn TTL. Document giới hạn cấu hình và test owner-safe release khi lock đã hết hạn; không hứa exactly-once cho arbitrary-duration user callbacks.
6. Giữ tests array cache nhanh cho transitions. Thêm dedicated integration dùng shared Redis store hoặc store thực được CI hỗ trợ, ít nhất hai processes nhận cùng operation. Dùng barrier/signals có timeout hữu hạn, không dựa vào sleep may rủi; test rằng chỉ một writer/ready notification hoàn tất trong điều kiện lock hợp lệ.
7. Không dùng production Redis/cache. Test namespace riêng, cleanup keys cụ thể; không flush shared cache toàn server. Workflow thiếu shared cache phải hiện rõ check chưa chạy; không silent-skip required concurrency gate.

**Race matrix tối thiểu:** hai generate; generate + cleanup tới hạn; late failed callback sau ready; lock contention rồi terminal; lock release khi store/notify/context release lỗi; TTL expiration/reacquisition và stale owner release.

**Chạy:** `vendor/bin/pest tests/QueuedExportTest.php` + dedicated concurrency tests; command/store/env phải được ghi trong handoff sau khi thiết kế support cụ thể. Không dùng DB matrix trên array cache làm evidence cho lock.

**Done:** transition/file ownership tests pass, shared-cache run có evidence, locks không bị giữ khi exception; compatibility/key namespace giữ nguyên. Nếu không có hạ tầng concurrency, có thể bàn giao diff và focused checks nhưng phải ghi Q04 chưa hoàn tất gate đó.

### Q05 — Capture/restore locale cho queued export

**Scope:** `src/Exports/QueuedExportSnapshot.php`, `QueuedExportDispatcher.php`, `src/Jobs/GenerateQueuedExport.php`, queued export tests; rollout note.

**Thực hiện:**

1. Capture `app()->getLocale()` lúc dispatch. Thêm field nullable/optional cuối constructor để không đổi named arguments cũ; không sửa order/tên fields hiện có.
2. Trong handle và failure-callback path, lưu previous worker locale, restore actor/context, set captured locale trước resolve table/export/labels/formatters/callbacks. Trong finally luôn khôi phục locale kể cả context release lỗi; không đồng thời thay global Request binding nếu không có regression riêng.
3. Snapshot cũ được unserialize không chạy constructor: đọc locale theo cách chịu được property chưa được khởi tạo (`isset`/`??`), không dựa chỉ vào constructor default. Khi thiếu locale, giữ previous worker locale theo behavior cũ; không bịa locale từ actor.
4. Test dispatch locale khác worker, translated labels/formatter, notifyReady/notifyFailure locale và worker locale sau success/failure. Literal labels vẫn nguyên.
5. Test payload serialized từ shape cũ bằng fixture hoặc process dùng class shape cũ; không chỉ tạo snapshot mới và truyền `locale:null` rồi gọi đó là compatibility test.
6. Kiểm tra new payload → old worker riêng. `final readonly` snapshot khiến thêm property không mặc nhiên rolling-safe. Nếu old worker không đọc được new payload, rollout phải pause dispatch/drain hoặc restart cập nhật workers trước khi producers phát snapshot mới; document deployment order và rollback. Không thay serialization format cả hệ thống để tránh viết rollout note.

**Chạy:** `vendor/bin/pest tests/QueuedExportTest.php`; serialization compatibility probe/test; scoped Pint/PHPStan.

**Done:** locale đúng và được restore, old payload chạy được trên new code, rollout limitation được ghi rõ. Không đổi context interfaces, hashes hoặc expiry policy cùng task.

## 8. Task cards — refactor nhỏ và frontend

### R01 — Built-in expression mapping vào SummaryAggregate

**Scope:** `src/Summaries/SummaryAggregate.php`, `src/Table.php::resolveBuiltInSummaries()`, `tests/SummaryTest.php`.

**Thiết kế:** thêm method enum, ví dụ `expression(?string $wrappedAttribute): string`, đánh dấu phục vụ nội bộ. Input là identifier đã được grammar wrap bởi caller; enum không biết Builder/Request/Table.

**Thực hiện:**

1. Đọc existing summary tests: built-ins batch một query, grouped/distinct/empty query, custom callback cloning. Bổ sung case thiếu trước khi move, không viết test chỉ mirror từng dòng `match` nếu integration đã đủ.
2. Move chính `match` COUNT/COUNT DISTINCT/SUM/AVG/MIN/MAX; custom vẫn throw unsupported nếu gọi nhầm. Count không cần attribute.
3. Table giữ clone/reorder/fromSub, grammar wrapping, aliases, one query và casting Count/CountDistinct về int. Giữ unsupported-null definition behavior; không biến invalid configuration thành SQL rỗng.
4. Custom callbacks vẫn qua `summariesForQuery()` nhận query clone và đúng table subclass. Không cho từng summary tự chạy query.

**Chạy:** `vendor/bin/pest tests/SummaryTest.php tests/ExportTest.php tests/QueuedExportTest.php tests/RelationshipQueryTest.php`; PHPStan/scoped Pint.

**Done:** output/types/null/query count giữ nguyên; chỉ expression mapping đổi owner. Không thêm median hoặc class mới.

### R02A — Capabilities/options helpers trong Table

**Scope:** `src/Table.php::resolve()`, private helpers mới và `tests/TableTest.php`/contract fixtures nếu test cần thêm case.

**Thực hiện:**

1. Dùng các arrays/booleans đã resolve làm input helper. Không gọi lại `columns()`, `actions()`, `exports()`, `views()`, authorization hoặc query trong helper.
2. Tách riêng capabilities và options; giữ distinction `hasActions`/`hasBulkActions`/`hasExports`/`selectable`, `hasEmptyState` và resolved empty content.
3. Giữ config fallback, protected resolver hooks và timing hiện tại. Không memoize definitions hoặc đổi number of callback evaluations ngoài phạm vi đã characterize.
4. Preserve keys/values/types/defaults; exact expected tests có thể giữ nguyên. Không cập nhật fixture chỉ vì refactor làm field khác đi.

**Chạy:** `vendor/bin/pest tests/TableTest.php tests/AnonymousTableTest.php tests/ContractResourceTest.php`; PHPStan/scoped Pint. Nếu không có C01, bổ sung equivalent current payload characterization trước khi move.

**Done:** `resolve()` dễ đọc hơn, helper không làm thêm query hoặc broaden public API. Không đổi private state visibility.

### R02B — Pagination envelope helpers trong Table

**Scope:** `Table::paginate()`, `paginateFully/Simply/ByCursor()`, private helper; table/anonymous/isolation tests.

**Hợp đồng phải giữ:**

| Mode | currentPage/from/to | lastPage/total | links | cursors |
|---|---|---|---|---|
| Không paginate | page 1; from/to theo total, null khi rỗng | lastPage 1; total đếm collection | [] | null |
| Full | số hoặc null theo Laravel paginator | số | `paginationLinks()` override | null |
| Simple | page số; from/to theo paginator | null | [] | null |
| Cursor | null | null | [] | encoded previous/next hoặc null |

`perPage`, `selectableTotal`, `hasPreviousPage`, `hasNextPage` giữ ý nghĩa từng mode. Simple/cursor có thể vẫn chạy selection count nếu feature cần; không “tối ưu” bỏ count đó.

**Thực hiện:**

1. Characterize subclass `paginate`, `paginationLinks`, `serializeRow` overrides; cases empty/out-of-range, tied sorts/custom primary key, namespaced links và host query params.
2. Gom fields/defaults thật sự giống bằng private helper hoặc small array assembly; giữ rõ mode-specific values. Không tạo helper 13 positional args hay nhiều boolean flags để giảm dòng.
3. Fetch/serialize/order validation vẫn tại owner hiện có; `serializeModels()` tiếp tục gọi `$this->serializeRow()`. Không chuyển sang static/default implementation.
4. Giữ `normalizePaginationState()` và `stabilizeCursorOrder()` ở vị trí có dữ liệu phù hợp. Không gộp guard trước query hook/sort callback.

**Chạy:** `vendor/bin/pest tests/TableTest.php tests/AnonymousTableTest.php tests/MultiTableIsolationTest.php tests/SelectionTest.php`; `npm test -- tests-js/Pagination.test.ts tests-js/useTable.test.ts`; contract fixture/round-trip checks khi code liên quan thay đổi.

**Done:** mode values/hooks/query counts giữ nguyên. Nếu phần lặp không gom được mà tăng plumbing, giữ branch rõ ràng và báo kết quả; không cố tạo class để đạt chỉ tiêu LOC.

### R03 — Saved View mutations về Views

**Phụ thuộc:** F03. **Scope:** `src/Views.php`, `src/Http/Controllers/ViewController.php`, `tests/ViewsTest.php` và connection regression của F03.

**Boundary:** controller giữ Request validation, lookup visible model, authorization và HTTP redirect/error mapping. `Views` sở hữu write/lock/version/default invariants trên configured model connection.

**Methods nội bộ đề xuất (kiểm tra name collisions trước khi thêm):** `createRecord(Table, Request, string, array)`, `updateRecord(Table, TableView, array, int)`, `deleteRecord(TableView, int)`, `setDefaultRecord(TableView, int)`, `shareRecord(TableView, int, bool)`. Chỉ chọn parameters cần thiết sau khi đọc code; không tạo DTO/service/repository. Methods cần controller gọi có thể public với `@internal`; đây không phải lý do đổi existing public APIs thành internal.

**Thực hiện:**

1. Giữ lookup/authorization trước mutation như endpoint hiện tại. Thêm characterization nếu thiếu callback timing, model events, version conflicts và response errors.
2. Move `lockCurrentView()` và mutation blocks vào `Views`; dùng connection fix của F03. Không đổi scope hash/table name/user/tenant policy hoặc duplicate-name handling trong cùng task.
3. Cập nhật create/update/delete/default/share controller calls. Controller có thể giữ `throwDuplicateName()` vì nó ánh xạ database error sang HTTP validation.
4. Không đổi mass-assignment/model events thành raw SQL cho tiện. Default switching vẫn lock đúng phạm vi và increment đúng model/version.
5. Refactor không bổ sung transaction cho paths trước đây không cần nếu chưa có bug test; ghi thay đổi behavior riêng nếu phát hiện cần thiết.

**Chạy:** `vendor/bin/pest tests/ViewsTest.php` + F03 test file; PHPStan/scoped Pint; DB locking/default cases trên drivers liên quan.

**Done:** controller không còn tự triển khai lock/version/default mutation policy; endpoint contracts và rollback test giữ nguyên. `Views` dài hơn là chấp nhận được.

### J01 — Clause predicates dùng chung

**Scope:** `resources/js/filters.ts`, `DataTable.vue`, `components/table/filters/useFilterEditor.ts`, `FilterValueControl.vue`, `ActiveFilter.vue`; tests tương ứng.

**Thiết kế đã chọn:** helpers thuần `isRangeClause(clause: string): boolean` và `isValuelessClause(clause: string): boolean` trong `filters.ts`. Range = between/not_between; valueless = is_true/is_false/is_set/is_not_set. Consumers đang có computed cùng tên có thể import alias, giữ composable return API.

**Thực hiện:**

1. Test built-in và unknown clauses; unknown vẫn false, không phát exception.
2. Thay các list/check tương đương ở năm consumers; không chuyển toàn bộ filter chip lifecycle vào `useFilterEditor` của một filter.
3. Giữ immediate submit cho valueless, incomplete range không submit, đổi range ↔ scalar reset đúng, focus/display label và remote selected labels.
4. Giữ exports cũ. Helpers có thể export ở module nội bộ để consumers import, không thêm package-root public export nếu không cần.

**Chạy:**

```sh
npm test -- tests-js/filters.test.ts tests-js/useFilterEditor.test.ts tests-js/DataTable.test.ts
npm run types:check
```

Scoped Prettier check cho files đổi. **Done:** lists semantics có một owner, UI timing/slots/state không đổi; không thêm metadata/registry hoặc sửa PHP clause behavior.

### J02 — Thu hẹp input type của useExports

**Scope:** `resources/js/useExports.ts`, `tests-js/useExports.test.ts` và type-focused test nếu cần.

**Thực hiện:**

1. Đổi riêng parameter type `actions: UseActions<T>` thành `Pick<UseActions<T>, "selection" | "selectedCount">`.
2. Giữ parameter name/order/default callbacks/return type và runtime code. Không tách selection hoặc polling trong task này.
3. Kiểm tra full `UseActions` vẫn assignable; một object chỉ có hai members đúng types cũng compile và selected export chạy được. Không dùng type casts để ép test pass.

**Chạy:** `npm test -- tests-js/useExports.test.ts tests-js/useActions.test.ts`; `npm run types:check`; scoped Prettier.

**Done:** input contract hẹp đúng nhu cầu, consumer hiện có không phải sửa và runtime behavior nguyên trạng.

### V01 — Kiểm tra kết quả cuối và bàn giao

**Thực hiện:**

1. Kiểm tra task reports, outstanding failures và deferred D/O tasks. Không gọi task blocked/deferred là completed.
2. Chạy full checks mục 4.3 trên final tree; chạy C01 freshness và C02 runtime bridge; kiểm tra Q04 concurrency gate nếu queue fixes nằm trong đợt này.
3. Compile public imports/examples; giữ `index.ts` exported symbols, props/events/slots. Nếu muốn smoke-test built package, dùng temporary consumer đọc `dist` sau build, không xuất bản npm hoặc push release.
4. Đọc diff tập trung public signatures/named args, enum values, resource/URL/persistence shapes và context contracts. Giữ fixtures trước/sau có diff giải thích được cho bugfix; refactor thuần không đổi payload.
5. Required CI matrix kết quả thật cần được ghi rõ. Checks chưa chạy vì thiếu service/network không được báo pass.
6. Changelog/upgrade/rollout notes ghi public failure message, malformed input, option tie order, queue locale/snapshot compatibility và worker deployment requirements. Không tăng version hoặc publish nếu user chưa yêu cầu.

**Done:** danh sách tasks completed, tests/evidence và các quyết định D/O rõ; không còn tracked build output/format churn ngoài scope. O01–O04 đã được làm sau đợt chính vì đều có use case và characterization phù hợp.

## 9. Những việc phải giữ riêng, không để model tự quyết tiện tay

### D01 — Mapped-sort fallback cần quyết định semantics

**Kết quả đã chốt:** mapped rows đứng trước unmapped rows ở cả ASC và DESC; các mapped value vẫn sort theo direction trong nhóm, còn unmapped dùng giá trị gốc theo direction. Regression đã bao phủ thứ tự này trong `3dab21a`.

`Column::applyMappedSort()` đang bind tên attribute làm fallback; đây là chỗ đáng sửa, nhưng mapped values có thể là strings/numbers. Chưa chọn dùng raw original value hay một nhóm unmapped riêng. Numeric fallback `count(map)` của priority sort không hợp lệ cho map `x→100, y→200` hoặc `x→A, y→Z`.

Task riêng trước implementation: viết ví dụ input/expected cho asc/desc, mixed types, null và unmapped; đánh giá DB compatibility; chọn policy rồi cập nhật card này. Nếu chọn một nhóm unmapped riêng, nêu rõ nó đứng trước/sau theo mỗi direction và secondary order; nếu dùng raw value, phải giải thích mixed SQL types. Không sửa trong R01/R02/F04 và không claim đã giải quyết D01 khi chỉ thêm test skipped.

### D02 — Bulk authorized closure

**Kết quả audit:** request-level availability và model-level authorization đã tách riêng, đồng thời bulk execution đã giữ model-level check. Không cần thay đổi source hay default authorization.

Phân biệt request-level availability và model-level checks. Defaults `$authorized`/`$authorize` đều true; không “fix” bằng đổi default. Cần quyết định rowAndBulk behavior và enforcement lúc execute, không chỉ làm bulk button xuất hiện.

### D03 — Fingerprint/versioning và callback snapshot semantics

**Kết quả audit:** giữ fail-closed guard hiện tại cho definition drift. Không thêm normalization/versioning mới vì sẽ là thay đổi compatibility policy, không phải refactor an toàn.

Giữ fail-closed definition drift guard. Muốn giảm ảnh hưởng whitespace/line movement cần versioning policy và old pending job tests, không normalize source bằng regex cho nhanh. Sync/queued export callback state và request binding là contracts riêng, chỉ đổi trong PR có characterization/migration.

### D04 — Remote allowlist và query contracts ngoài phạm vi F04

**Kết quả audit:** remote option discovery vẫn tách khỏi validate filter value và row authorization; hydration giữ selected value khi options/dependencies thay đổi. Không có diff cần thiết.

Option discovery/dependency availability không tự là allowed filter values hoặc row authorization. Không validate against current option page, không làm mất selected label khi dependency đổi, không xóa `$request->table` khỏi `FilterOptionRequest`. Raw/grouped option query support cần contract riêng nếu F04 gặp giới hạn.

### O01–O04 — Tùy chọn, đã triển khai sau khi qua gate

| ID | Kết quả | Evidence |
|---|---|---|
| O01 | `usePolling` dùng chung polling/status parsing, có guard chống response cũ ghi đè response mới; giữ fixed delay và policy lỗi theo owner. | `a9b8ee3` |
| O02 | `TableState::fromArray()` dùng parser chung với `fromRequest()`, giữ Spatie Request isolation. | `a9b8ee3` |
| O03 | PHP resource phát `clauseValueKinds` additive; frontend dùng metadata này và vẫn fallback resource cũ. | `afe8c4b` |
| O04 | Cache helper nội bộ chia sẻ reserve/lock/raw status storage, bảo toàn cache key, hash và lifecycle riêng của action/export; lock có regression qua hai file-cache repository độc lập cùng storage. | `f45788f`, `1968576` |

Nếu user giao toàn bộ kế hoạch mà không nêu O tasks, chỉ làm các task bắt buộc; báo backlog còn lại khi kết thúc.

## 10. Prompt và mẫu bàn giao để dùng model khác

### Prompt triển khai một task

```text
Bạn đang ở repository toolbelt-inertia-table.
Triển khai task <TASK_ID> trong docs/implementation-plan.vi.md.

Đọc mục 1–4 và đúng task card được giao; chỉ đọc thêm source/tests cần thiết.
Không audit lại toàn repository, không lập lại kế hoạch, không triển khai task khác.
Giữ public APIs/named arguments/hooks/wire và persistence contracts đã nêu.
Với bug, tái hiện bằng regression rồi fix nhỏ nhất; với refactor, giữ behavior/tests expectations.
Chạy focused checks trong card; không chạy full matrix lặp lại nếu không có lý do mới.
Nếu source đã đổi, đối chiếu symbol và điều chỉnh nhỏ theo code hiện tại; không reset user changes.
Không tự triển khai D/O tasks, đổi dependencies, mở rộng architecture hoặc publish/push.
Kết thúc bằng report theo mẫu mục 10, nêu check chưa chạy và blocker thực sự nếu có.
```

Thay `<TASK_ID>` bằng một ID thật, ví dụ `F01`; có thể giao `F01 và F02` hoặc `J01 và J02` theo nhóm mục 2. Nếu cho phép model đi tiếp nhiều tasks, liệt kê explicit IDs và yêu cầu báo cáo sau mỗi logical task; không dùng “làm mọi thứ bạn thấy cần”.

### Prompt review diff

```text
Review diff của task <TASK_ID> theo docs/implementation-plan.vi.md.
Chỉ tìm lỗi behavior, thiếu regression, phá API/hooks/persistence, scope creep hoặc test không chứng minh claim.
Đối chiếu source và tests thực tế; không yêu cầu abstraction mới chỉ để giảm số dòng.
Nếu cần sửa, nêu file/method, tình huống tái hiện, severity và fix nhỏ nhất.
Không chỉnh production code trong lượt review này.
```

### Report bắt buộc sau mỗi task

```text
Task: <ID> — completed / incomplete / blocked
Base → current: <commit hoặc mô tả working-tree diff>
Changed files: <danh sách ngắn>
Behavior: <thay đổi gì hoặc invariant giữ nguyên>
Tests: <command, pass/fail, số test nếu output có>
Not run: <check/service thiếu, lý do>
Compatibility: <ảnh hưởng wire/API/snapshot hoặc none với bằng chứng>
Deferred: <D/O item liên quan; không lặp toàn backlog>
Next: <task tiếp theo theo dependency>
```

Completed chỉ khi acceptance checks bắt buộc của task có evidence. Nếu thiếu shared cache/DB matrix, ghi rõ phần đã làm và gate chưa hoàn tất; không sửa tests để giả lập pass.

### Trạng thái khởi đầu

Tất cả B/F/C/Q/R/J/V tasks trong tài liệu này đang **pending**. D/O tasks đang **deferred**. Model triển khai cập nhật report từng task trong handoff; không suy ra một task đã xong chỉ vì source hiện đã có một phần tương tự.

**Tài liệu này dừng ở lập kế hoạch. Chưa sửa production code, chưa tạo test implementation, chưa chạy task cards.**
