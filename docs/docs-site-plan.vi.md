# Kế hoạch triển khai docs site — Musing Inertia Table

## 1. Mục tiêu

Tách tài liệu sử dụng khỏi `README.md` thành một docs site có điều hướng, tìm kiếm,
kiểm tra link và deploy tự động. Sau khi hoàn thành:

- `README.md` phục vụ onboarding trong khoảng 5 phút;
- docs site là nguồn tài liệu đầy đủ cho người dùng package;
- tài liệu maintainer vẫn nằm cùng repo nhưng được tách khỏi hướng dẫn người dùng;
- mọi pull request thay đổi docs đều phải build thành công;
- docs trên nhánh `master` được deploy tự động lên GitHub Pages.

Giới hạn cho `README.md` sau khi rút gọn là **không quá 300 dòng**, mục tiêu thực tế
180–250 dòng. README chỉ giữ:

1. mô tả package và badges;
2. screenshot hoặc GIF ngắn;
3. feature matrix;
4. requirements;
5. installation và quick start tối thiểu;
6. link tới docs, playground, GitHub và package registries;
7. trạng thái pre-1.0 và license.

Saved Views, Actions, Queues, Filters, Exports, Relationships, customization và
API reference phải được đọc đầy đủ trên docs site.

## 2. Hiện trạng

- `README.md`: 1.313 dòng, vừa làm landing page, tutorial, guide, API reference,
  vận hành queue và hướng dẫn release.
- `docs/architecture.md`: 513 dòng, dành cho maintainer và mô tả contract nội bộ.
- `docs/customization.md`: 88 dòng, là user guide nhưng chưa có navigation chung.
- `docs/api-stability.md`: 53 dòng, là policy cho contributor và package consumer.
- `docs/implementation-plan.vi.md`: kế hoạch nội bộ, không nên xuất hiện trong
  navigation hoặc search của public docs.
- Chưa có docs build script, docs CI, GitHub Pages workflow hay link checker.
- Package frontend đã dùng Vue 3, Vite, TypeScript và npm; không cần thêm một hệ
  sinh JavaScript thứ hai.

## 3. Quyết định kỹ thuật

### 3.1. Static site generator

Dùng **VitePress** đặt trực tiếp trong thư mục `docs/`.

Lý do:

- phù hợp với Vue/Vite hiện có;
- Markdown là nguồn nội dung, dễ review cùng code;
- file-based routing và default documentation theme đáp ứng đủ yêu cầu;
- có sidebar, previous/next links, code highlighting và local full-text search;
- build ra static HTML và deploy được bằng GitHub Pages;
- theme có thể mở rộng bằng Vue khi cần demo tương tác, nhưng giai đoạn đầu không
  cần custom theme phức tạp.

Docs toolchain dùng Node 22 trong CI. Cài VitePress dưới `devDependencies`, khóa
phiên bản qua `package-lock.json` và không tải dependency từ CDN lúc runtime.

### 3.2. Hosting và URL

Giai đoạn đầu deploy bằng GitHub Pages:

```text
https://thienbd203.github.io/inertia-table/
```

VitePress dùng `base: '/inertia-table/'`. Giá trị site URL và base phải nằm tại
một chỗ trong `docs/.vitepress/config.ts` để sau này chuyển sang custom domain
không phải sửa link trong từng Markdown file.

Các link nội bộ trong docs dùng đường dẫn VitePress không có `.md`. Asset dùng
đường dẫn từ `/images/...` trong site. README dùng đường dẫn tương đối tới cùng
file vật lý trong `docs/public/images/` để không nhân đôi screenshot.

### 3.3. Ngôn ngữ

Public docs giai đoạn đầu viết bằng **English**, cùng ngôn ngữ với README và API.
Các plan tiếng Việt là tài liệu nội bộ và bị loại khỏi navigation/search.

Không dựng i18n routing trong phase đầu. Khi có nhu cầu tài liệu tiếng Việt thật,
tách thành một phase riêng với `/vi/`; không trộn hai ngôn ngữ trong cùng một page.

### 3.4. Nguồn sự thật

- Docs site là nguồn đầy đủ cho cách dùng package.
- README chỉ chứa một quick start tự đủ và link sâu tới docs.
- Mỗi capability chỉ có một page chính. Page khác dùng link, không copy cả đoạn.
- Example code phải dùng API public hiện tại và bám phiên bản resource hiện tại.
- `docs/implementation-plan.vi.md` và `docs/docs-site-plan.vi.md` không được publish
  hoặc index bởi local search.
- Release instructions dành cho maintainer, không nằm trong README onboarding.

## 4. Information architecture

```text
docs/
├── .vitepress/
│   ├── config.ts
│   └── theme/
│       ├── index.ts
│       └── custom.css
├── public/
│   └── images/
│       ├── table-overview.png
│       └── filters-and-actions.png       # chỉ thêm khi hình thứ hai có giá trị
├── index.md                              # landing page
├── guide/
│   ├── getting-started.md
│   ├── configuration.md
│   ├── table-definitions.md
│   ├── columns.md
│   ├── search-and-filters.md
│   ├── relationships.md
│   ├── pagination-and-url-state.md
│   └── multiple-tables.md
├── features/
│   ├── summaries.md
│   ├── saved-views.md
│   ├── actions.md
│   ├── queues.md
│   └── exports.md
├── customization/
│   ├── translations.md
│   ├── rendering-and-slots.md
│   ├── styling.md
│   └── headless-api.md
├── reference/
│   ├── php-api.md
│   ├── vue-api.md
│   ├── resource-schema.md
│   ├── configuration.md
│   ├── artisan-commands.md
│   └── compatibility.md
├── internals/
│   ├── architecture.md
│   ├── api-stability.md
│   ├── development.md
│   └── releasing.md
├── implementation-plan.vi.md             # excluded from build/search
└── docs-site-plan.vi.md                  # excluded from build/search
```

`guide/configuration.md` giải thích các lựa chọn thường gặp và dẫn người đọc theo
use case. `reference/configuration.md` liệt kê đầy đủ key, type, default và tác
động. Hai page có mục đích khác nhau và không lặp nguyên bảng cấu hình.

Sidebar gồm bốn nhóm theo thứ tự:

1. Getting Started;
2. Guides;
3. Features;
4. Customization;
5. Reference;
6. Internals.

Top navigation giữ gọn: `Guide`, `Features`, `API`, `Playground`, `GitHub`.
Search dùng provider `local`; chưa cần Algolia hoặc dịch vụ ngoài.

## 5. Nội dung README sau khi rút gọn

### 5.1. Outline bắt buộc

```text
# Musing Inertia Table
badges
one-sentence value proposition
pre-1.0 warning

overview screenshot
Docs | Playground | Packagist | npm

## Features
compact feature matrix

## Requirements
compatibility table

## Installation
composer + npm + Tailwind source

## Quick start
one Table class
one controller response
one Vue DataTable render

## Documentation
links to primary docs sections

## Development
short link to contributor page

## License
```

### 5.2. Feature matrix

Matrix chỉ cho biết capability có sẵn và link tới page chính. Không giải thích
toàn bộ API trong matrix.

| Area | Capability | Link đích |
| --- | --- | --- |
| Query | search, sort, pagination, relationships | guide pages |
| Filters | text, numeric, set, boolean, date, lazy options | filters |
| Columns | presentation, layout, sticky, summaries | columns/summaries |
| Workflows | row actions, bulk actions, queues | actions/queues |
| Data | exports, all-matching selection | exports/actions |
| Personalization | saved views, URL state, multiple tables | feature/guide pages |
| UI | renderer, slots, translations, headless composables | customization |

### 5.3. Screenshot và demo

- Chụp từ playground hiện tại ở viewport desktop sạch, không chứa dữ liệu nhạy cảm.
- Ưu tiên PNG tối ưu dung lượng dưới 500 KB; thêm dark-mode screenshot chỉ khi
  giao diện khác đáng kể.
- Alt text mô tả bảng và các control chính.
- Screenshot trong README trỏ tới `docs/public/images/table-overview.png`.
- CTA `Playground` trỏ tới repository playground cho tới khi có live demo URL.
- Không dùng animated GIF lớn trong phase đầu. Nếu cần thể hiện tương tác, dùng
  video/GIF riêng sau khi docs nền tảng đã ổn định.

## 6. Mapping nội dung hiện tại

| Nguồn hiện tại | Đích | Cách xử lý |
| --- | --- | --- |
| README intro + Highlights | README + `docs/index.md` | Viết lại ngắn, không copy toàn bộ |
| Requirements | README + `reference/compatibility.md` | README giữ bảng ngắn; reference giải thích chi tiết |
| Installation + Tailwind | README + `guide/getting-started.md` | README giữ lệnh tối thiểu |
| Releases | `internals/releasing.md` | Xóa khỏi README |
| Config dump | `reference/configuration.md` | Chuyển thành bảng key/default và ví dụ theo nhóm |
| Quick start | README + `guide/getting-started.md` | README giữ happy path; docs thêm giải thích |
| Anonymous tables | `guide/table-definitions.md` | Chuyển nguyên contract, biên tập lại |
| Pagination modes | `guide/pagination-and-url-state.md` | Gộp với URL semantics |
| Internationalization | `customization/translations.md` | Tách từ `docs/customization.md` |
| Columns | `guide/columns.md` | Tách API và ví dụ theo loại column |
| Sizing, ordering, sticky | `guide/columns.md` | Giữ cùng page để tránh phân mảnh |
| Summary footer | `features/summaries.md` | Tách capability và query semantics |
| Badges, images, navigation, empty states | `guide/columns.md` | Đặt dưới presentation/navigation |
| Search and filters | `guide/search-and-filters.md` | Bao gồm `SetFilter::lazy()` hiện tại |
| Relationship queries | `guide/relationships.md` | Tách riêng vì có sorter và eager loading |
| Actions | `features/actions.md` | Row, bulk, selection, authorization, confirmation |
| Queued actions | `features/queues.md` | Queue setup, lifecycle, retries, expiry |
| Exports | `features/exports.md` | Sync, queued, scopes, adapters, security |
| Slots and headless API | customization pages + `reference/vue-api.md` | Guide theo use case; reference liệt kê contract |
| URL state | `guide/pagination-and-url-state.md` | Giải thích namespace và normalization |
| Multiple tables | `guide/multiple-tables.md` | Ví dụ đầy đủ hai bảng |
| Saved Views | `features/saved-views.md` | Setup migration, scope, defaults, sharing, locking |
| Development | `internals/development.md` | Xóa commands dài khỏi README |
| `docs/customization.md` | `docs/customization/*` | Chia theo nhiệm vụ, xóa file cũ sau khi link được sửa |
| `docs/architecture.md` | `docs/internals/architecture.md` | Di chuyển, chỉnh link và frontmatter |
| `docs/api-stability.md` | `docs/internals/api-stability.md` | Di chuyển, giữ nguyên policy |

Không xóa đoạn nào khỏi README trước khi nội dung tương ứng tồn tại ở page đích và
đã được kiểm tra với code hiện tại.

## 7. Quy chuẩn cho từng page

Mỗi user guide page nên có:

1. một câu nói rõ khi nào cần tính năng;
2. prerequisites nếu có migration, worker hoặc optional dependency;
3. một happy-path example chạy được;
4. giải thích server contract và frontend behavior;
5. security/authorization semantics khi liên quan;
6. edge cases thực tế;
7. link tới API reference và page liên quan;
8. phần `Troubleshooting` chỉ khi có lỗi thường gặp cụ thể.

Quy tắc code sample:

- dùng namespace và method đang tồn tại trong `src/` hoặc export từ
  `resources/js/index.ts`;
- không giới thiệu internal class như API public;
- mỗi PHP sample phải đủ `use` statements cần thiết khi copy độc lập;
- không dùng `remote filter`, `optionsUsing()` hoặc các API đã bị xóa;
- filter model lớn dùng `pluckOptionsFromModel(...)->lazy()`;
- ví dụ queue phải nêu worker và persistence requirement;
- URL examples phải dùng namespace `table[<name>]` hiện tại;
- dùng cùng domain mẫu (`Topic`, `Category`) để giảm tải nhận thức.

## 8. Thiết lập VitePress

### DS01 — Scaffold docs runtime

**Phụ thuộc:** không có.

**File dự kiến:**

- `package.json`
- `package-lock.json`
- `.gitignore`
- `docs/.vitepress/config.ts`
- `docs/.vitepress/theme/index.ts`
- `docs/.vitepress/theme/custom.css`
- `docs/index.md`

**Công việc:**

- thêm VitePress vào `devDependencies`;
- thêm scripts `docs:dev`, `docs:build`, `docs:preview`;
- cấu hình title, description, base, clean URLs, last updated và local search;
- cấu hình GitHub edit links, social link và navigation rỗng có type checking;
- exclude hai file plan tiếng Việt khỏi build/source và search;
- ignore `docs/.vitepress/cache` và `docs/.vitepress/dist`;
- tạo landing page tối thiểu để chứng minh build hoạt động.

**Nghiệm thu:**

- `npm ci` chạy được từ lockfile;
- `npm run docs:build` thành công;
- `npm run docs:preview` phục vụ đúng dưới base `/inertia-table/`;
- build output không chứa hai file plan.

### DS02 — Navigation, theme và shared metadata

**Phụ thuộc:** DS01.

**Công việc:**

- khai báo nav/sidebar theo information architecture ở mục 4;
- thêm footer, social links, edit link, last updated và outline level hợp lý;
- custom CSS chỉ đặt brand colors, typography nhỏ và image framing;
- giữ default theme semantics, keyboard navigation và responsive layout;
- tạo placeholder page cho mọi route để link có thể được kiểm tra sớm.

**Nghiệm thu:**

- không có dead internal link;
- sidebar active state đúng ở mọi nhóm;
- navigation sử dụng được ở mobile và desktop;
- light/dark theme không làm code block hoặc warning box mất tương phản.

## 9. Task cards chuyển nội dung

### DS03 — Getting started và table fundamentals

**Phụ thuộc:** DS02.

**Page:** `guide/getting-started.md`, `guide/configuration.md`,
`guide/table-definitions.md`, `reference/artisan-commands.md`,
`reference/compatibility.md`.

**Công việc:**

- chuyển installation, Tailwind setup, first table, controller và Vue render;
- giải thích generated table so với anonymous table;
- tách compatibility và Artisan command reference;
- bảo đảm happy path có thể hoàn thành trong 5 phút.

**Focused checks:** đối chiếu command với `src/Commands`, provider tags, constructor và
public methods trong `src/Table.php`.

### DS04 — Columns và summaries

**Phụ thuộc:** DS02.

**Page:** `guide/columns.md`, `features/summaries.md`.

**Công việc:**

- chuyển column types, formatting, sort/search, navigation, empty state;
- mô tả sizing, ordering, pinning, sticky header/footer;
- tách summary aggregates, filtered query semantics và custom summary;
- dẫn styling hooks sang customization thay vì lặp CSS variables.

**Focused checks:** đối chiếu toàn bộ public column classes, `Variant`, summary
aggregates và serialized fields với code/tests.

### DS05 — Search, filters và relationships

**Phụ thuộc:** DS03.

**Page:** `guide/search-and-filters.md`, `guide/relationships.md`.

**Công việc:**

- document search allowlist và mọi built-in filter;
- document clauses, custom apply callbacks và filter normalization;
- mô tả lazy set options bằng `pluckOptionsFromModel()->lazy()`;
- document relationship paths, eager loading và relationship sorter;
- thêm bảng “use this when” cho eager loading, Power Joins và custom callbacks.

**Focused checks:** chạy filter/relationship tests liên quan và tìm toàn repo để
không còn nhắc API remote filter đã xóa.

### DS06 — Actions và queues

**Phụ thuộc:** DS03.

**Page:** `features/actions.md`, `features/queues.md`.

**Công việc:**

- tách row/bulk/row-and-bulk actions, selection scopes và authorization;
- document confirmation, destructive state, idempotency và callback outcomes;
- đưa queue configuration, worker, lifecycle, polling, expiry và cleanup vào page
  queues;
- phân biệt rõ synchronous action với managed queued action.

**Focused checks:** đối chiếu Action public API, endpoints, jobs, queue status enum
và tests trước khi viết final examples.

### DS07 — Exports và Saved Views

**Phụ thuộc:** DS03.

**Page:** `features/exports.md`, `features/saved-views.md`.

**Công việc:**

- document export scopes, sync/queued flow, signatures, summaries và optional
  XLSX/PDF adapters;
- document migration setup, scoping, default views, sharing, optimistic locking
  và search persistence;
- đặt operational setup cạnh feature cần nó, không để trong README.

**Focused checks:** đối chiếu config, migration tags, model contracts, endpoint
payloads và optional package behavior.

### DS08 — URL state, pagination và multiple tables

**Phụ thuộc:** DS03.

**Page:** `guide/pagination-and-url-state.md`, `guide/multiple-tables.md`.

**Công việc:**

- chuyển full/simple/cursor pagination;
- giải thích URL namespace, replace/preserve behavior và normalized state;
- document hai hoặc nhiều table trên cùng page với tên và partial props độc lập;
- thêm ví dụ deep link có thể đọc được nhưng không hard-code cursor token.

**Focused checks:** đối chiếu `resources/js/url.ts`, `TableState` và PHP state
normalization tests.

### DS09 — Customization và Vue reference

**Phụ thuộc:** DS02, DS04.

**Page:** toàn bộ `customization/*`, `reference/vue-api.md`.

**Công việc:**

- chia `docs/customization.md` thành translations, rendering/slots, styling và
  headless API;
- document exported components, composables, props, events và slots;
- phân biệt public `tb-*` hooks với internal shadcn components;
- thêm một ví dụ custom cell và một ví dụ headless nhỏ.

**Focused checks:** lấy export list trực tiếp từ `resources/js/index.ts`, types và
`docs/api-stability.md`; không đoán public API từ file nội bộ.

### DS10 — PHP API, config và resource reference

**Phụ thuộc:** DS03–DS09.

**Page:** `reference/php-api.md`, `reference/resource-schema.md`,
`reference/configuration.md`.

**Công việc:**

- tạo reference theo class/method group, không sao chép source code;
- ghi type, default, return chaining và capability constraint;
- document resource schema v2 và optional/additive fields;
- chuyển config dump thành bảng theo nhóm kèm default thực trong config file;
- link từ reference tới guide có use case tương ứng.

**Focused checks:** so sánh method names bằng reflection hoặc script inventory;
so sánh default với `config/inertia-table.php` và serialized fixtures.

### DS11 — Maintainer docs

**Phụ thuộc:** DS02.

**Page:** `internals/*`.

**Công việc:**

- di chuyển architecture và API stability, giữ git history nếu thuận tiện;
- chuyển Development và Releases từ README;
- document test commands, contract fixtures, release gates và version alignment;
- gắn nhãn rõ “Maintainer documentation”.

**Nghiệm thu:** contributor tìm được mọi command cũ của README qua tối đa hai lần
click; public guide không bị lẫn release secrets hoặc package publishing steps.

## 10. README cutover

### DS12 — Viết lại README

**Phụ thuộc:** DS03–DS11 đã có nội dung thật.

**Công việc:**

- viết README theo outline mục 5;
- tạo feature matrix có deep links tới docs site;
- giữ một quick start đầy đủ nhưng tối thiểu;
- thêm screenshot và CTA;
- xóa các section dài chỉ sau khi page đích tồn tại;
- sửa mọi link từ issue templates hoặc package metadata nếu cần.

**Nghiệm thu:**

- README không quá 300 dòng;
- người dùng mới có thể cài và render bảng đầu tiên chỉ bằng README;
- mọi capability nâng cao có link rõ tới docs;
- không còn config dump, queue lifecycle hay full API reference trong README;
- không có nội dung remote filter đã bị xóa.

## 11. CI và deployment

### DS13 — Docs quality workflow

**Phụ thuộc:** DS01.

Tạo `.github/workflows/run-docs.yml` chạy khi thay đổi:

- `README.md`;
- `docs/**`;
- `package.json`;
- `package-lock.json`;
- chính workflow.

Workflow dùng Node 22, `npm ci` và `npm run docs:build`. VitePress build phải fail
khi có dead internal links. Không chạy cả PHP matrix cho thay đổi chỉ liên quan
docs.

Thêm `npm run docs:build` vào release gate hoặc gọi reusable docs workflow từ
`release.yml` để một release không thể publish với docs không build được.

### DS14 — GitHub Pages deployment

**Phụ thuộc:** DS13.

Tạo `.github/workflows/deploy-docs.yml`:

- trigger khi push `master` có thay đổi docs/tooling và cho phép
  `workflow_dispatch`;
- permissions tối thiểu: đọc contents, ghi Pages và OIDC token theo yêu cầu Pages;
- concurrency một deployment, không hủy deployment đang publish giữa chừng;
- build bằng Node 22 và `npm ci`;
- upload `docs/.vitepress/dist` bằng Pages artifact;
- deploy bằng GitHub Pages environment;
- workflow output hiển thị public URL.

Sau merge lần đầu, chọn GitHub Pages source là **GitHub Actions** trong repository
settings. Đây là bước cấu hình repository duy nhất ngoài code.

### DS15 — Link và content audit cuối

**Phụ thuộc:** DS03–DS14.

Chạy audit tự động và thủ công:

```bash
npm ci
npm run docs:build
npm run format:check
npm run types:check
npm test
composer analyse
composer test
```

Audit nội dung:

- tìm API cũ và thuật ngữ đã xóa;
- kiểm tra mọi method/class trong snippets;
- kiểm tra links README ở GitHub rendering và docs dưới base path;
- kiểm tra code blocks, tables, admonitions, mobile navigation và local search;
- kiểm tra direct refresh ở một route lồng sâu;
- kiểm tra 404 page;
- kiểm tra screenshot không quá lớn;
- xác nhận edit link trỏ đúng source file và branch `master`.

## 12. Thứ tự triển khai và khả năng song song

```text
DS01
  └─ DS02
      ├─ DS03 ─┬─ DS05
      │        ├─ DS06
      │        ├─ DS07
      │        └─ DS08
      ├─ DS04 ─── DS09
      └─ DS11

DS03–DS09 + DS11
  └─ DS10
      └─ DS12

DS01 ─ DS13 ─ DS14
DS12 + DS14 ─ DS15
```

Sau DS02, các nhóm DS03, DS04, DS06, DS07, DS08 và DS11 có thể giao cho model
khác nhau. Không giao hai model sửa cùng `config.ts`, README hoặc cùng page.
DS10 và DS12 thực hiện sau để tránh tạo reference/links từ nội dung chưa ổn định.

## 13. Quy tắc giao task cho model khác

Mỗi lượt chỉ giao một task card hoặc một nhóm page có cùng ownership. Prompt phải
có:

```text
Implement task DSxx from docs/docs-site-plan.vi.md.

Read the current implementation and tests before documenting an API. Treat source
code and tests as authoritative. Do not document removed remote-filter APIs. Keep
changes inside the files owned by DSxx. Run the focused checks in the task card and
npm run docs:build. Report changed files, validation, and any contract ambiguity.
Do not rewrite unrelated docs or README unless DSxx explicitly owns them.
```

Report bắt buộc sau mỗi task:

1. page/file đã tạo hoặc sửa;
2. API source và tests đã đối chiếu;
3. commands đã chạy và kết quả;
4. link hoặc contract còn chưa chắc chắn;
5. phần nào của README đã đủ điều kiện để xóa ở DS12.

## 14. Definition of Done

Docs site chỉ được coi là hoàn tất khi:

- public URL hoạt động trên GitHub Pages;
- docs build là required check cho thay đổi docs;
- README không quá 300 dòng và hoàn thành được first-table flow;
- screenshot, demo link và feature matrix hiện diện;
- Saved Views, Actions, Queues, Filters, Exports và Relationships có page riêng;
- local search tìm thấy các API và use case chính;
- mọi nội dung cũ trong README đã được migrate hoặc chủ động loại bỏ;
- không còn tài liệu về remote filter APIs đã xóa;
- config/reference khớp code hiện tại;
- existing PHP/JavaScript test suites vẫn pass;
- plan nội bộ tiếng Việt không xuất hiện trên public site;
- architecture, API stability, development và release docs có đường dẫn rõ cho
  maintainer.

## 15. Phạm vi chưa làm trong phase đầu

- docs đa ngôn ngữ;
- Algolia DocSearch hoặc hosted search service;
- interactive playground nhúng trực tiếp vào docs;
- tự sinh toàn bộ PHP API reference từ reflection;
- versioned docs cho từng release;
- custom domain;
- analytics, cookie banner hoặc user tracking.

Các mục này chỉ được thêm sau khi site nền tảng đã deploy ổn định và nội dung
README migration hoàn tất.
