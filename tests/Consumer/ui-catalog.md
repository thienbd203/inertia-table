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

Unless a row says otherwise, its status is **not-run**. Prior observations below
come from DX02 at package `2a53a8f` plus its focus fix (included in that commit),
PHP host in this repository, Node 22.22.2, PHP 8.4.23, Vue 3.5.42 and Inertia Vue
3.7.0. Browser was Codex in-app browser; viewport was not recorded, so these are
functional observations rather than a responsive baseline.

| ID        | Setup and steps                                                                                                                             | Expected                                                | Status / evidence                                |
| --------- | ------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------- | ------------------------------------------------ |
| SSR       | `/topics`; inspect server HTML and console                                                                                                  | Three rows; no hydration warnings                       | pass in DX02 after nested-button fix             |
| CSR       | `/topics?csr=1`; search Beta                                                                                                                | Only Beta; partial navigation works                     | pass in DX02                                     |
| SORT      | `/topics?csr=1`; Name → Desc                                                                                                                | Gamma, Beta, Alpha; URL sort `-name`                    | pass in DX02                                     |
| LAZY      | `/topics`; Filters → Status → options → Draft                                                                                               | Options load; selection/focus remain on Draft           | pass in DX02; final console empty                |
| REOPEN    | Close and reopen loaded Status options                                                                                                      | No extra options request; selection preserved           | not-run                                          |
| CLOSE     | Slow mode; add Status then close editor before reply                                                                                        | Reply never reopens editor or steals focus              | pass after fix; see findings                     |
| RETRY     | Failure mode; open Status; close error UI and retry                                                                                         | Busy clears; retry loads options; applied state remains | not-run                                          |
| MULTI     | Slow mode; choose Published and Draft quickly                                                                                               | Both survive response; all three rows                   | not-run                                          |
| URL       | `/topics?table[topics][filters][status][enabled]=1&table[topics][filters][status][clause]=in&table[topics][filters][status][value][]=draft` | Beta; after loading options label is Draft              | row/state pass in DX02; label after load not-run |
| SEARCH    | Type Beta rapidly; inspect request log                                                                                                      | Only final debounced query is sent                      | not-run (single-value search only tested)        |
| RACE      | Type Beta then sort before debounce completes                                                                                               | Final query and sort reflect latest intent              | not-run                                          |
| HISTORY   | Search/sort then Back/Forward through existing entries                                                                                      | Restores state according to replace policy              | not-run                                          |
| EMPTY     | `/topics?table[topics][search]=missing-topic`                                                                                               | No results, no inappropriate create CTA                 | not-run                                          |
| KEYBOARD  | Tab/Enter/Escape through search, menus, filter                                                                                              | Useful focus order; focus returns on close              | not-run                                          |
| MOBILE    | 320/375/768/1280px, zoom 200%, light/dark                                                                                                   | Readable controls, no clipped overlays                  | not-run                                          |
| RANGE     | Numeric/date range with only one endpoint, then both                                                                                        | Incomplete value not sent                               | not-run; fixture needed                          |
| TWO       | Two tables; interact with each independently                                                                                                | URL/loading/focus scoped correctly                      | not-run; fixture needed                          |
| SELECTION | All matching plus exclusions across pages                                                                                                   | Correct count and result identity reset                 | not-run; fixture needed                          |
| VIEW      | Save/conflict/failure paths                                                                                                                 | No false success; draft retained                        | not-run; playground fixture needed               |
| ACTION    | Pending/accepted/completed and double click                                                                                                 | Accurate feedback; no double submit                     | not-run; playground fixture needed               |
| LAYOUT    | Sticky, resize, RTL, long Vietnamese labels, slots                                                                                          | No clipped portal or incorrect logical offsets          | not-run; fixture needed                          |

## Findings

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
`REOPEN`: cached options observed, but selected-value preservation is still not-run.
Regression test covers closing while a pending lazy response finishes. The
consumer was rebuilt from tarball before the final browser run.

Failure mode transport check: first lazy-header request returned 503, the next
returned 200 with two loaded options. This validates fixture injection only;
`RETRY` browser recovery remains not-run.

- Fixed in DX02: Filters/Columns/Actions nested buttons caused browser HTML
  repair and hydration mismatches. Reproduction: initial SSR, before interaction.
- Fixed in DX02: opening set filter called `focus()` on a Button component that
  did not expose it. Reproduction: Filters → Status. Final browser focus was on
  Select options, and console warnings/errors were empty.

For each new finding record package/host commit, exact URL, server mode, browser,
viewport, steps, expected/actual, console and screenshot path under `build/`.
Do not mark the whole UI task done based on these two fixes or DOM unit tests.
