# UI verification catalog

This is a manual browser catalog, not an automated test report. Start with
`npm run test:consumer`, then `node tests/Consumer/serve.mjs`. Use the printed
origin below; port numbers change. `/topics` resets table URL state and gets a
fresh SQLite database with Alpha (published), Beta (draft), Gamma (published).
No user data is reset. Close menus before resetting the URL.

## Reproduction modes

```sh
# Normal responses
node tests/Consumer/serve.mjs
# Slow responses carrying the lazy-options header
node tests/Consumer/serve.mjs --lazy-delay=2000
# First lazy-header request fails; subsequent attempts succeed
node tests/Consumer/serve.mjs --lazy-delay=1000 --lazy-fail-once
```

Restart resets the failure counter and request numbering. The header also travels
with later table navigation after options are loaded, so latency affects those
requests too. Terminal JSON records URL, partial props and lazy-header presence;
use browser Network to distinguish cancellation from server completion. A 503
deliberately exercises Inertia's HTTP error UI; do not suppress its default handler.

## Scenario inventory

Cancellation control: with slow mode enabled, add Status, then click the fixture's
**Cancel pending requests** button before the response. It calls Inertia 3's
`router.cancelAll()`; this is a fixture control, not a package feature. Reopen
Status to retry. Request logs now include `received`, `finished` or `disconnected`
phases and elapsed milliseconds, so cancellation can be distinguished from a
response that already completed.

Unless a row says otherwise, its status is **not-run**. Prior observations below
come from DX02 at package `2a53a8f` plus its focus fix (included in that commit),
PHP host in this repository, Node 22.22.2, PHP 8.4.23, Vue 3.5.42 and Inertia Vue
3.7.0. Browser was Codex in-app browser; viewport was not recorded, so these are
functional observations rather than a responsive baseline.

| ID        | Setup and steps                                                                                                                             | Expected                                                | Status / evidence                                                 |
| --------- | ------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------- | ----------------------------------------------------------------- |
| SSR       | `/topics`; inspect server HTML and console                                                                                                  | Three rows; no hydration warnings                       | pass in DX02 after nested-button fix                              |
| CSR       | `/topics?csr=1`; search Beta                                                                                                                | Only Beta; partial navigation works                     | pass in DX02                                                      |
| SORT      | `/topics?csr=1`; Name → Desc                                                                                                                | Gamma, Beta, Alpha; URL sort `-name`                    | pass in DX02                                                      |
| LAZY      | `/topics`; Filters → Status → options → Draft                                                                                               | Options load; selection/focus remain on Draft           | pass in DX02; final console empty                                 |
| REOPEN    | Close and reopen loaded Status options                                                                                                      | No extra options request; selection preserved           | pass; see follow-up                                               |
| CLOSE     | Slow mode; add Status then close editor before reply                                                                                        | Reply never reopens editor or steals focus              | pass after fix; see findings                                      |
| RETRY     | Failure mode; open Status; close error UI and retry                                                                                         | Busy clears; retry loads options; applied state remains | pass including applied Draft; see 2026-09-09 follow-up            |
| MULTI     | Slow mode; choose Published and Draft quickly                                                                                               | Both survive response; all three rows                   | delayed sequential choices pass; overlapping responses not proven |
| URL       | `/topics?table[topics][filters][status][enabled]=1&table[topics][filters][status][clause]=in&table[topics][filters][status][value][]=draft` | Beta; after loading options label is Draft              | pass including checked Draft after retry                          |
| SEARCH    | Type Beta rapidly; inspect request log                                                                                                      | Only final debounced query is sent                      | not-run (single-value search only tested)                         |
| RACE      | Type Beta then sort before debounce completes                                                                                               | Final query and sort reflect latest intent              | not-run                                                           |
| HISTORY   | Search/sort then Back/Forward through existing entries                                                                                      | Restores state according to replace policy              | not-run                                                           |
| EMPTY     | `/topics?table[topics][search]=missing-topic`                                                                                               | No results, no inappropriate create CTA                 | not-run                                                           |
| KEYBOARD  | Tab/Enter/Escape through search, menus, filter                                                                                              | Useful focus order; focus returns on close              | not-run                                                           |
| MOBILE    | 320/375/768/1280px, zoom 200%, light/dark                                                                                                   | Readable controls, no clipped overlays                  | not-run                                                           |
| RANGE     | Numeric/date range with only one endpoint, then both                                                                                        | Incomplete value not sent                               | pass; numeric and date browser evidence below                     |
| TWO       | Two tables; interact with each independently                                                                                                | URL/loading/focus scoped correctly                      | not-run; fixture needed                                           |
| SELECTION | All matching plus exclusions across pages                                                                                                   | Correct count and result identity reset                 | not-run; fixture needed                                           |
| VIEW      | Save/conflict/failure paths                                                                                                                 | No false success; draft retained                        | not-run; playground fixture needed                                |
| ACTION    | Pending/accepted/completed and double click                                                                                                 | Accurate feedback; no double submit                     | not-run; playground fixture needed                                |
| LAYOUT    | Sticky, resize, RTL, long Vietnamese labels, slots                                                                                          | No clipped portal or incorrect logical offsets          | not-run; fixture needed                                           |

## Findings

### 2026-09-09: date range browser verification

Packed consumer now includes Created (DateFilter). In-app browser, normal
response mode, baseline `0f17ccd` plus working-tree fixes. Initial URL Between
2026-01-01 and 2026-01-04 restored the selected calendar range and all three rows.
Selecting January 2 as a new start did not send a request. Selecting January 4
as the end sent one partial request, completed in 333ms. The applied chip became
2026-01-02, 2026-01-04 and results were exactly Beta/Gamma. Console had no warnings
or errors. A component test using the real Reka RangeCalendarRoot also verifies
that one endpoint emits nothing and completing both endpoints emits once.
Full JS suite: 138 tests passed; typecheck, formatting and packed consumer passed.

### Browser confirmation: incomplete draft survives partial reload

Consumer-only Reload table props button and Ctrl+Enter shortcut call real
`router.reload({ only: ['topics'] })`. The shortcut listens in capture phase so
it works inside portaled filter content; its document listener is removed on
unmount. No package API or production UI control was added.

Started with URL ID Equals 2 (Beta). Opened ID, changed draft to Between and
typed lower bound 1 without an upper bound. Ctrl+Enter sent a partial request
carrying the original Equals 2 URL; server logged completion at 317ms. After
completion editor remained Between with lower bound 1 and focus on that input;
applied URL/results stayed Equals 2/Beta. Console was empty. This verifies the
equivalent-state draft fix using the packed package and real Inertia/Vue.
Server-state changes remain covered by the separate Equals 42 component test.

### Unchanged applied-state reload preserves editor draft

Component regression reproduced a reset: choose Between, enter lower bound 15,
then replace the resource with an equivalent JSON copy (as an options reload
can do). Previously the editor reverted to Equals and lost the incomplete range.
`useFilterEditor` now watches the serialized applied enabled/clause/value tuple,
so replacing an equivalent state object does not reset a draft. A companion
test verifies that changed server state (Equals 42) still replaces the draft.
Both pass. This narrowly fixes equivalent-state reloads; it does not claim
protection against an older response carrying a genuinely different state.

### 2026-09-09: numeric range browser verification

Packed consumer with the debounce fix, in-app browser, normal response mode.
Filters → ID → Between: entering only lower bound 2 left all three rows and
sent no request (server log still contained only the initial page). Entering
upper bound 3 sent one partial request with `[2, 3]`. Reload preserved Between,
the 2–3 chip, and exactly Beta/Gamma. Numeric complete/incomplete range and URL
restoration pass. Rapid clear-before-debounce is covered by component regression;
this browser run did not reproduce that sub-300ms gesture. Date range is covered
by the subsequent verification above.

## Filter state ownership (UI01)

| State                                             | Owner                                                   | Synchronization / boundary                                                                                                           |
| ------------------------------------------------- | ------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------ |
| Applied filter values and allowed clauses/options | Laravel TableResource                                   | Browser requests changes; server normalizes the resulting resource                                                                   |
| Current editor clause/value                       | `useFilterEditor`                                       | Initializes from resource; watches server state; calls `useTable.setFilter` on apply                                                 |
| Text/numeric input and incomplete range draft     | `FilterValueControl`                                    | Local draft before debounce; range sends only with both endpoints; pending send cancelled on clause change/incomplete range/disposal |
| Loaded lazy option data                           | Resource filter `options` / `lazyLoaded`                | Server response supplies data; loading flags do not own the options                                                                  |
| Lazy request loading and requested-header set     | `useTable`                                              | Tracks attributes separately; finish releases loading; unsuccessful load can retry                                                   |
| Popover open/closed                               | `ActiveFilter.isOpen`                                   | User actions or explicit initial auto-open; lazy completion no longer forces reopening                                               |
| Initial editor auto-open request                  | `DataTable.pendingFilterPopover`                        | Consumed after editor opens                                                                                                          |
| Focus                                             | Popover open lifecycle → `FilterEditor` → value control | Focus is requested when opening; pending lazy response must not steal it                                                             |

Known remaining risk to verify: server-state watchers in `useFilterEditor` and
`FilterValueControl` can synchronize drafts on new props. Independent async
reloads and editing during those responses still need browser coverage; the
ownership table documents current code, not proof that every race is resolved.

### 2026-09-09: stale debounced range values

Two component regressions failed before the fix in `FilterValueControl.vue`:
typing scalar 15 then switching to between still emitted 15 after 300ms;
typing range 15–35 then clearing its second endpoint still emitted 15–35.
The control now cancels pending debounce on clause change, incomplete range and
scope disposal. All three focused regressions pass, including a complete edited
range 15–40 being emitted once. This is component evidence, not browser timing.
The consumer now has Filters → ID (NumericFilter) so numeric range scenarios can
be reproduced through the real Laravel query. The subsequent Created fixture
and date range verification above cover the date interaction.

### 2026-09-09: applied-state retry and overlapping visits

Same package baseline `0f17ccd` with the working-tree consumer controls.
In-app browser, no package source edits needed for these cases.

- Opened the URL scenario with applied Draft, then opened its editor under
  `--lazy-fail-once`. After 503 and Escape, URL/state still held Draft and only
  Beta appeared. Reopening retried successfully; Draft label and checked option
  were restored. Applied-state retry and URL-label restoration pass.
- Under `--lazy-delay=10000`, began with Draft, added Published, then removed
  Draft in one tool call with an intervening UI state read. Server received both
  visits before either completed; request 3 disconnected at 604ms when request 4
  superseded it. Final URL had only Published, rows were Alpha/Gamma, and console
  was empty. This verifies interrupted overlapping filter visits, not two
  independently successful async reloads merging out of order.
- The earlier two-value addition remains a separate sequential test. Do not
  generalize this add/remove overlap result to every multi-filter/clause race.

### 2026-09-09: real cancellation

Baseline `0f17ccd`, consumer rebuilt with the cancellation control; package code
unchanged. In-app browser, `/topics`, `--lazy-delay=10000`.
Opened Status then clicked Cancel pending requests: server logged request 2 as
`disconnected` at 5344ms, before the planned response. Reopening Status started
request 3. This checks actual browser abort rather than only mocked callbacks.
Request 3 finished at 10333ms; the browser then displayed Published and Draft,
with no warnings/errors. Cancellation followed by retry **passes** for a filter
without an applied value. Applied-state preservation remains a separate case.
Added a unit test for two different lazy filters finishing in reverse order:
loading remains independent, a successful filter is retained, and an unfinished
one can retry. That test does not establish browser response-merge ordering.

### 2026-09-08: closing during lazy loading

Package/host baseline `bbe9424`, then working-tree fix in `ActiveFilter.vue`.
Used `/topics` with `--lazy-delay=10000` in the in-app browser; viewport not
recorded (focus-only verification, not geometry coverage).

1. Filters → Status starts the delayed options request.
2. Before it finishes, click the search field. Editor closes and search has focus.
3. Before fix: completion reopens the editor and focuses Select options.
4. After fix: editor stays closed, search retains focus, console is empty.
5. Reopen editor/options: Published and Draft are available from that response.

`CLOSE`: **pass after fix**, previously reproduced failure. Removed the watcher
that forced the popover open after loading; explicit user open/close now owns it.
`REOPEN`: cached options observed here; selected-value verification follows below.
Regression test covers closing while a pending lazy response finishes. The
consumer was rebuilt from tarball before the final browser run.

Failure mode transport check: first lazy-header request returned 503, the next
returned 200 with two loaded options. This validates fixture injection only;
`RETRY` browser recovery was subsequently checked below.

### Follow-up at 9484eec

Same packed consumer code as the preceding fix; no package source change in this
follow-up. In-app browser, `/topics`, viewport not recorded.

- With `--lazy-fail-once`, opening Status displayed Inertia's error dialog.
  Escape dismissed it. Reopening Status and its options loaded Published/Draft.
  No stuck busy state. This began without an applied filter value; preservation
  of a pre-existing applied value on failure remains unverified.
- With `--lazy-delay=10000`, selected Published then Draft. Final URL contained
  both values, both checkboxes remained checked, and Alpha/Beta/Gamma appeared.
  Escape then reopening options preserved both selections. Request log contained
  the initial page, lazy load and two filter visits; reopening sent no request.
  Browser console was empty on this origin. Tool timing did not establish that
  the second click preceded the first response, so overlapping-response coverage
  still requires a controlled test.
- Added unit regressions for retry after `onFinish` without loaded options and
  for a synchronous router exception. They verify loading clears and another
  attempt is possible; they do not simulate browser network cancellation.

- Fixed in DX02: Filters/Columns/Actions nested buttons caused browser HTML
  repair and hydration mismatches. Reproduction: initial SSR, before interaction.
- Fixed in DX02: opening set filter called `focus()` on a Button component that
  did not expose it. Reproduction: Filters → Status. Final browser focus was on
  Select options, and console warnings/errors were empty.

For each new finding record package/host commit, exact URL, server mode, browser,
viewport, steps, expected/actual, console and screenshot path under `build/`.
Do not mark the whole UI task done based on these two fixes or DOM unit tests.
