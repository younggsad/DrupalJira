# Playwright E2E tests

Run commands from the repository root on the host. Node 24, npm, Docker/DDEV,
the installed Drupal site, and PHP dependencies are prerequisites. Tests do not
start DDEV, import configuration, or install Drupal automatically.

## Installation and execution

```bash
ddev start
ddev composer install
ddev drush status
npm ci
npx playwright install chromium
npm run test:e2e
```

On Linux, if Chromium reports missing system libraries, install them with
`npx playwright install-deps chromium` (requires system administrator access).
The suite initially supports Chromium only. `package-lock.json` locks the
Playwright version; browser installation must match that version.

```bash
# One file, one case, or a named browser project
npm run test:e2e -- tests/e2e/smoke.spec.mjs
npm run test:e2e -- --grep 'project manager can approve'
npm run test:e2e -- --project=chromium

# Interactive modes (require a display)
npm run test:e2e:headed
npm run test:e2e:debug -- --grep 'anonymous board'
npm run test:e2e:ui

# Isolation and repeatability checks
npm run test:e2e -- --repeat-each=3 --workers=2
```

In UI mode, selecting a test provisions its fixtures automatically. There is no
separate authentication setup project to select. Closing the process abruptly
can leave owned data behind; use the recovery commands below.

## Environment and secrets

The safe default is `BASE_URL=https://drupaljira.ddev.site`, confirmed by DDEV.
Other origins, credentials embedded in URLs, and non-root URL paths are rejected
before provisioning. Remote environments require a matching fixture backend;
changing only BASE_URL does not make remote execution safe or supported.

Copy `.env.e2e.example` to ignored `.env.e2e` for local overrides. Shell environment
values take precedence. No dotenv package is required; Node 24 loads the file.

| Variable | Default / meaning |
| --- | --- |
| `BASE_URL` | `https://drupaljira.ddev.site`; only this local origin is supported |
| `E2E_IGNORE_HTTPS_ERRORS` | `true` for the exact supported local DDEV origin; set `false` to require certificate trust |
| `E2E_MANAGER_PASSWORD` | Optional, at least 16 characters; otherwise randomly generated |
| `E2E_USER_PASSWORD` | Optional, at least 16 characters; otherwise randomly generated |
| `E2E_ARTIFACT_ID` | Random UUID per invocation; optional letters/digits/hyphens, maximum 100 characters |
| `CI` | When set, focused tests are forbidden |

Never put credentials in tracked files or command arguments. Automatic accounts
get unique usernames; the suite does not use personal accounts or UID 1.
Private runtime JSON inputs and storageState contain secrets and must stay local.
They are owner-readable and removed during normal cleanup. Do not upload traces
or HTML reports without reviewing them for session and page information.

Playwright's test and authentication contexts tolerate the local DDEV development
certificate by default, only after BASE_URL passes the exact local-origin guard.
Set `E2E_IGNORE_HTTPS_ERRORS=false` to require a trusted certificate and repair
the local DDEV/mkcert trust installation if needed. Other origins remain rejected
before contexts or fixtures are created. This does not change Node, curl, or
system TLS verification and must not be extended to arbitrary/production URLs.
Use these contexts only for the supported local site.

## Data model and fixtures

`tests/e2e/drupal/fixtures.php` runs only through Drush in DDEV project DrupalJira,
with the expected site UUID. Preflight validates active fields, workflow states,
and persona permissions. Configuration drift fails setup; the helper never
imports configuration or modifies roles.

The `test` export in `tests/e2e/fixtures.mjs` supplies:

| Fixture / option | Behavior |
| --- | --- |
| `personas` | Worker-scoped manager and regular accounts, with fresh UI login state |
| `persona` | `anonymous` by default; set to `manager` or `regular` using `test.use()` |
| `scenario` | Fresh test-scoped Kanban/Scrum Projects, four Tasks, and a TimeLog |
| `fixtureMedia` | `false` by default; `true` attaches an image and document |

Each Task references its fixture Project and regular assignee, estimates 8 hours,
and starts in backlog, in_progress, review, or done. Backlog has a 2-hour TimeLog
dated 2020-01-02, yielding 6 hours remaining. Entity IDs are returned in the
manifest; tests must never assume numeric IDs. UUIDs are deterministic within
each namespace, while namespaces isolate runs, workers, tests, and retries.

The existing Project Manager can approve Review → Done. Regular authenticated
users can edit any Task and reopen Review/Done → In Progress, but cannot approve.
Both inherit `administer time_log`. Neither existing role grants
`create time_log`, `start_progress`, or `submit_review`. Consequently the
dedicated `/task/{id}/log-time` form is inaccessible with these personas even
though the generic TimeLog administration routes are accessible. Tests preserve
this current permission contract rather than expanding it.

Task setup sets `moderation_state`; the existing board presave hook synchronizes
`field_status`. Setup skips only the moderation transition constraint when
creating snapshots, because initializing all states is not an interactive
transition. All other entity constraints and the configured state are checked.
Application authorization is tested through the CSRF-protected HTTP endpoint.
Regular-user approval currently returns HTTP 400, not 403.

TimeLogs are custom `time_log` entities, not nodes. Entity saves exercise their
cache hooks. Setup does not dispatch the write-off form's separate creation
event, and does not claim to test that event.

Media uses existing image/document bundles and fields; the PNG includes alt text.
The basic smoke suite needs no attachments. `media.spec.mjs` verifies the optional
Media infrastructure through the public Task page and document download.

New tests must import `test` and `expect` from `fixtures.mjs`, request `scenario`,
and use semantic locators or existing `data-task-id` / `data-status` attributes.
Use retrying assertions and observable response/navigation waits. Do not use
fixed sleeps. Assertions must use public HTTP/UI; Drush belongs only in fixture
setup, inspection for setup verification, and cleanup. No direct SQL is used.

## Manual fixtures and recovery

```bash
npm run e2e:seed -- --namespace manual-check
npm run e2e:reset -- --namespace manual-check

# Include optional Media infrastructure
npm run e2e:seed -- --namespace manual-media --media
npm run e2e:reset -- --namespace manual-media

# List namespaces remaining after interrupted runs
npm run e2e:reset -- --list
npm run e2e:reset -- --namespace NAME_FROM_LIST
```

Seed prints public account names and an entity manifest, never passwords. For
manual UI login, set the optional password variables in `.env.e2e` before seed.
Otherwise generated passwords are deliberately unavailable. Reset an existing
namespace before seeding it again; duplicate seed refuses to overwrite data.
Namespace names accept letters, digits, and hyphens, with a maximum of 100
characters. Do not reset a namespace while its tests are running.

Ownership is recorded in Drupal state before each save, including deterministic
UUIDs and owned file paths. Reset removes owned TimeLogs, Tasks and revisions,
Projects, Media/files, and accounts; it also removes TimeLogs created through
HTTP against owned Tasks. The custom TimeLog module's
`drupaljira_timelog_user_predelete()` hook deletes account-owned TimeLogs.
Only exact ownership entries are deleted, never title-prefix matches or whole
tables. Private local inputs/auth state matching the namespace are removed too.
Cleanup remains available when role/workflow configuration drifts, provided the
local target identity still matches. Empty upload directories are removed without
recursively deleting unrecognized files.

## Failure artifacts

Each invocation writes to its own namespace under `.playwright/`:

- `results/{artifact-id}/`: failure screenshots, traces, and runner metadata.
- `reports/{artifact-id}/`: HTML report retained after failed/interrupted runs.
- `auth/{worker-namespace}/`: temporary storageState, removed during teardown.
- `inputs/`: temporary private Drush input files, removed after each operation.

Authentication contexts explicitly trace UI login. On login failure, a screenshot
(when the page is available) and browser trace are retained beneath
`results/{artifact-id}/auth/{worker-namespace}/{persona}/` and attached to the
failing test's HTML report when the runner is available. Successful login traces
are discarded. Authentication traces can contain entered credentials as well as
cookies; keep them ignored and review them before sharing.

Successful tests retain no screenshots or traces; a wholly passing run removes
its HTML report. Small Playwright runner metadata may remain. Everything under
`.playwright/`, `.env.e2e*` except the example, and `node_modules/` is ignored.
Parallel invocations must not deliberately reuse the same E2E_ARTIFACT_ID.

```bash
# Open the latest retained failure report
npm run test:e2e:report

# Open a particular report or trace
npx playwright show-report .playwright/reports/ARTIFACT_ID
npx playwright show-trace .playwright/results/ARTIFACT_ID/TEST_DIRECTORY/trace.zip
```

The report viewer starts a local server; use its printed URL and Ctrl-C to stop.
The trace viewer opens its browser UI; a display is needed for its default mode.
Report viewing before any failure will have no retained report to open.

## Verification record — 2026-10-06

The initial run was blocked by local certificate trust. The scoped DDEV exception
now permits UI login and public HTTPS requests without changing system TLS.
Runtime verification also corrected three locator assumptions: column headings
now match their exact names, and Media/time-summary assertions use the existing
article markup rather than field classes absent from this theme. No Drupal code,
permissions, configuration, or fixture architecture changed in these fixes.

Executed checks:

```bash
npx --no-install playwright test --list
npm ci --dry-run --offline --ignore-scripts --no-audit --no-fund
npm ls @playwright/test
E2E_ARTIFACT_ID=verification-suite npm run test:e2e -- --project=chromium --workers=2
E2E_ARTIFACT_ID=verification-repeat-suite npm run test:e2e -- --project=chromium --repeat-each=3 --workers=2
```

Results: six tests discovered; Playwright 1.63.0 and the lock file are consistent;
the full suite passed 6/6 and the repeated parallel suite passed 18/18. Each case
also passed individually using `npm run test:e2e -- --grep 'CASE NAME' --workers=1`,
in reverse declaration order.

Temporary probes under ignored `.playwright/verification/` verified public
`/user/login` HTTP 200 through the configured Playwright context, both personas'
own `/user/{id}` pages, populated local-domain storageState cookies, ignored state
paths, and mode 0600. All three state/identity probes passed. A lifecycle probe
executed two complete setup/reset cycles with Media, checking exact counts
(2 users, 6 nodes, 1 TimeLog, 2 Media, 2 files), stable UUIDs, duplicate refusal
without accumulation, and idempotent cleanup.

Two deliberate failures verified diagnostics:

- `E2E_IGNORE_HTTPS_ERRORS=false` with the anonymous smoke case failed at local
  certificate verification as expected. Artifact ID `verification-auth-failure`
  retained the authentication screenshot and browser trace, both attached to its
  HTML report.
- An intentionally missing heading in an ignored probe failed as expected.
  Artifact ID `verification-test-failure-final` retained an HTML report, screenshot,
  and browser trace. The ordinary six-test suite contains no intentional failure.

Successful final runs retained no screenshots/traces or HTML reports. All fixture
namespaces, storageState files, private input files, uploaded fixture files, and
E2E image derivatives were gone after teardown. Entity-ID fingerprints matched
the pre-run baseline for nodes (25), users (6), Media (2), files (5), TimeLogs
(106), moderation-state entities (6), and node revisions (166).

Syntax checks, ignore/secret searches, unsupported-origin rejection, invalid TLS
option rejection, and `git diff --check` passed. No fixed timeout waits, tracked
credentials/cookies/auth state, production test targets, or global TLS-disable
setting were found. Headed/debug/UI modes are documented but were not exercised
in this headless verification session. Strict mode still requires local CA trust.
No commit or PR was created.

| # | Criterion | Status and evidence |
| --- | --- | --- |
| 1 | Dependency and lock file | PASS — consistent files and installed 1.63.0; not committed, as requested |
| 2 | Configuration and dedicated directory | PASS — configuration loads, six tests discovered |
| 3 | Safe environment default | PASS — local HTTPS works; other origins and invalid TLS options rejected |
| 4 | Secrets outside version control | PASS — ignored private state/input paths, mode checks, secret search |
| 5 | Deterministic setup/cleanup | PASS — two cycles, stable UUIDs, duplicate refusal, unchanged baseline |
| 6 | Manager, regular, anonymous auth | PASS — UI login, state/identity checks, and persona smoke cases |
| 7 | Failure artifacts | PASS — both failure types retain diagnostics; successful runs discard them |
| 8 | No fixed sleeps | PASS — no prohibited timeout waits in the suite |
| 9 | Usage documentation | PASS — matches the implementation; interactive modes require a display |
| 10 | LLM review | PASS — accepted/rejected recommendations in `docs/e2e-llm-review.md`; self-review |
| 11 | Public interface assertions | PASS — application assertions use HTTP/UI; Entity API only in helpers |
| 12 | Order independence | PASS — six standalone cases in reverse order, plus 18 parallel repetitions |

## Task 9.2 journeys

The focused Project, Task, board/workflow, Task Media and TimeLog specs document
coverage in [the Task 9.2 matrix](testing/task-9-2-test-matrix.md). They reuse the
same scenario, authentication, preflight and cleanup infrastructure.

`journeyPermissions: true` is an opt-in worker option for these specs. It grants
the fixture manager a temporary non-admin role for creation, early workflow
transitions, Media browsing and the custom Log time form. Existing smoke personas
and persistent site roles retain their permissions. `fixtureMedia: 'pdf'` selects
a PDF document alongside the image; `fixtureMedia: true` continues to use TXT.

UI creation helpers register exact title/bundle/owner intent in the existing
scenario ledger before submission, including renamed titles before editing.
Cleanup removes these nodes and dependent TimeLogs as well as seeded entities;
worker cleanup removes its temporary role after its accounts.
