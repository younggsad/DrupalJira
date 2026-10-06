# Task 9.2 critical journey matrix

Review: self-reviewed against acceptance criteria 1–16, existing 9.1 tests,
active form displays, workflow, permissions, board JavaScript, document bundle,
and custom TimeLog form. This is not an independent reviewer sign-off.

All new cases use isolated scenarios and manager UI authentication. Project/Task
creation targets are created through forms in their own test. No case consumes
another case's IDs or data. Drush owns supporting fixtures, preflight, exact
ownership registration, and cleanup only; it does not perform business assertions.

| Requirement / journey | Business risk | File and test coverage | Fixture dependencies | Persistence verified | Lower-level owner |
| --- | --- | --- | --- | --- | --- |
| 1–2: Project default and edit | Wrong delivery method retained | `project.spec.mjs`: Project defaults to Kanban and an edit to Scrum persists | Manager with opt-in journey role; own UI Project | Reloaded display and reopened edit form show Kanban, then Scrum | Drupal functional tests: allowed values/default configuration |
| 3–4, 6: Task create/edit | Lost Project, assignee, estimate or description | `task.spec.mjs`: Task creation and editing persist Project, Backlog, assignee, decimal estimate and description | Manager/regular accounts; own UI Project and Task | Initial Backlog, Project and description on reload; reopened fields; edited title, assignee, 5.25-hour estimate, description and retained Project | Kernel/functional tests: reference validation, decimal storage and widget conversion |
| Save paths: normal edit vs real moderation | Workflow moves unexpectedly or transition action is lost | `task.spec.mjs`: existing Task edit case plus Original Content Moderation action persists transitions independently of normal Save | Separate own UI-created Project/Task per case; privileged manager | Normal creation/edit remain Backlog after reload; original action moves through In progress, Review, Done and reopen; every state checked after reload and on live board; Review selection refreshes action label without a save | Drupal functional/kernel tests: exhaustive permission and invalid transition constraints |
| 5: Required Project | Orphan Task created | `task.spec.mjs`: Task without required Project shows validation and is not created | Isolated scenario and accounts; unique attempted title | Visible Project validation, retained creation URL/title, absence from fixture board | Drupal functional tests: exhaustive required-field internals and database non-creation |
| 7–8: Board placement and opening | Created work missing or wrong Task opens | `board-workflow.spec.mjs`: UI-created Task appears in its Project Backlog and opens from the board | Own UI Project/Task | Fresh board navigation; modal shows exact title, Project and description; absent from another Project | Views kernel/functional tests: query joins/filter combinations |
| 9–10: Complete workflow | State changes lost or wrong column | `board-workflow.spec.mjs`: Board drag and drop persists Backlog → In Progress → Review → Done → reopen | Own UI Project/Task; privileged manager | Actual pointer drag/drop for every transition; visible expected column before and after reload; reopen means Done → In Progress | Content Moderation functional tests: exhaustive transitions, invalid states and permissions |
| 11–12: Image and PDF attachment | Attachments disappear after save | `task-media.spec.mjs`: Media Library attaches an image and PDF that persist on the Task | Isolated image/PDF Media; own UI Project/Task | Media Library selection, saved/reloaded image alt text and PDF filename, reopened widget shows both names | Core Media Library functional tests: generic upload, pagination, selection internals; manual PDF content/accessibility review |
| 13–14: Custom TimeLog and totals | Lost time or inaccurate remaining estimate | `timelog.spec.mjs`: Custom Log time form persists decimal hours and updates Task statistics | Own scenario backlog Task: estimate 8.00, existing log 2.00 | UI adds 1.25 hours; reloaded total 3.25 and remaining 4.75; listing/reload shows saved hours/date | Unit/kernel tests: rounding, cache invalidation, events and aggregate calculations; functional tests: invalid dates/over-estimate reasons |
| 15: Independence | Order conflicts and leaked data | Every new case | Random scenario namespace, exact title/bundle/owner intent ledger, temporary worker role | Each case provisions and cleans its own entities; repeated full runs validate reuse | Fixture lifecycle verification |
| 16: Reviewed scope | Unclear responsibility / duplicate tests | This matrix | None | Review includes existing coverage and exclusions | Independent human review remains optional |

## Existing coverage retained

`smoke.spec.mjs` retains anonymous board/modal, regular approval denial,
manager approval through the HTTP endpoint, fixture time summary, and Scrum-only
sprints. `media.spec.mjs` retains public image rendering and TXT download. These
integration/permission tests complement the new UI journeys. Their files are
unchanged, and their personas retain the original permission contract.

## Fixture and selector decisions

The existing manager role cannot create Projects/Tasks, start progress, submit
review, or access the custom Log time form. `journeyPermissions: true` adds a
non-admin, temporary worker-owned role to the fixture manager only. Existing site
roles/configuration are not expanded. This demonstrates journeys for a user with
the necessary permissions; it does not claim the shipped Project Manager role
can perform all of them. Deployment permissions require a separate product decision.

UI creation intent records exact title, bundle and manager owner before submission;
cleanup includes UI nodes and dependent TimeLogs. Titles include the isolated
scenario namespace. Renamed Tasks register both exact titles. PDF mode changes
only optional scenario document bytes/MIME; boolean Media mode still uses TXT.
PDF is allowed by the existing document bundle. Media is provisioned as selectable
supporting data, and the target Task receives both references through Media Library.

Estimate input uses the actual Hours + Minutes widget: 3h30m stores 3.50 hours,
and 5h15m stores 5.25 hours. Public summaries use decimal-hour formatting.
Board sections/cards have no accessible names: existing `data-status` and
`data-task-id` attributes are the narrow stable fallbacks. They scope assertions
about visible titles/columns and drive native Playwright drag/drop. Response
observation waits for the board's own save before reload; tests do not call the
status endpoint. All other locators use roles, labels, text and scopes.

## Intentionally untested lower-value scenarios

- Generic Drupal core field validation, entity-reference internals and every invalid value: Drupal functional/kernel tests.
- Generic Media Library upload/filter/pagination behavior: core functional tests; this suite owns attaching existing isolated image/PDF Media to a Task.
- Generic Content Moderation API behavior and exhaustive workflow permutations: kernel/functional tests. Only the required path and reopen are UI-owned here.
- Render-array structure, JavaScript implementation details and CSS dimensions/classes: unit/kernel tests where meaningful; visual/manual review for layout.
- Browser/device permutations, keyboard drag alternatives, PDF content fidelity and exhaustive accessibility: focused manual verification or a separate accessibility/visual suite.
- TimeLog over-estimate validation, event subscribers and every rounding boundary: Drupal functional/unit/kernel tests.

## Runtime validation

See the final validation record below for exact commands and results. Failed runs
are diagnosed using retained Playwright error contexts/traces and corrected without
removing business assertions. Default Chromium, one worker, zero retries and the
existing timeouts/artifact configuration remain unchanged.

Media Library type switches use their accessible button roles and keyboard Enter
activation. Trace/screenshot review found overlapping pointer hit areas in the
custom theme, which prevent Playwright's ordinary center click from reaching the
Image switch. No forced clicks or programmatic selection are used. Pointer
interaction/layout needs separate manual review and a focused theme fix; keyboard
selection and attachment persistence are covered. The TimeLog listing navigates
to its last page when pagination exists, then verifies the unique Task's new row.

Two application fixes were necessary for these journeys: the global entity
presave hook accepts `EntityInterface` and narrows to Task nodes (configuration
entities previously caused a TypeError); normal Task creation preserves Backlog. The form correction below keeps the
original Drupal submit action for moderation and adds a distinct normal Save
action to creation and editing; no copied transition button remains.

### Validation record — 2026-10-07

| Command | Final result |
| --- | --- |
| `ddev describe` | Local DrupalJira running |
| `ddev drush cr` | Pass; discovered the creation-form hook |
| `npx playwright test tests/e2e/project.spec.mjs` | 1/1 pass |
| `npx playwright test tests/e2e/task.spec.mjs` | 2/2 pass |
| `npx playwright test tests/e2e/board-workflow.spec.mjs` | 2/2 pass |
| `npx playwright test tests/e2e/task-media.spec.mjs` | 1/1 pass |
| `npx playwright test tests/e2e/timelog.spec.mjs` | 1/1 pass |
| `npx playwright test tests/e2e/smoke.spec.mjs tests/e2e/media.spec.mjs` | 6/6 pass; unchanged files |
| `npx playwright test` | 13/13 pass, 4.3 minutes |
| `npx playwright test` (second invocation) | 13/13 pass, 4.1 minutes; no failures/conflicts observed |
| `ddev exec php -l tests/e2e/drupal/fixtures.php` | Pass |
| `ddev exec php -l web/modules/custom/drupaljira_board/drupaljira_board.module` | Pass |
| `ddev exec vendor/bin/phpcs web/modules/custom/drupaljira_board/drupaljira_board.module tests/e2e/drupal/fixtures.php` | Pass |
| `ddev exec vendor/bin/phpstan analyse web/modules/custom/drupaljira_board/drupaljira_board.module --no-progress` | Pass |
| `git diff --check` | Pass |
| `file tests/e2e/assets/fixture.pdf` | PDF 1.4, one page |

JavaScript syntax command (pass):

```bash
for file in tests/e2e/fixtures.mjs tests/e2e/helpers/journeys.mjs tests/e2e/project.spec.mjs tests/e2e/task.spec.mjs tests/e2e/board-workflow.spec.mjs tests/e2e/task-media.spec.mjs tests/e2e/timelog.spec.mjs; do node --check "$file" || exit; done
```

Additional focused syntax checks executed while implementing:
`node --check tests/e2e/helpers/journeys.mjs` and
`node --check tests/e2e/task-media.spec.mjs` (both passed).

Final read-only lifecycle command:

```bash
ddev drush php:script .playwright/verification/task92-inspect.php --script-path=/var/www/html
node --input-type=module -e 'import { readdir } from "node:fs/promises"; for (const directory of [".playwright/auth", ".playwright/inputs"]) console.log(directory, (await readdir(directory)).length);'
```

The ignored inspection probe queried UI test nodes, temporary journey roles and
E2E namespace state without deleting anything. All three lists were empty after
the second full run. Private auth and input directories each contained zero
entries. An earlier read-only inspection also found no leftover UI nodes.

Initial runs failed at the sandbox's Docker socket restriction and were rerun
with approved local DDEV/browser access. Subsequent diagnostic failures exposed
the config-entity hook type error, autocomplete role mismatch, combined status
text, Media type button names/pointer overlap, and TimeLog message/pager names.
Retained error contexts/output and the Media screenshot were inspected. The
fixture docblock initially failed PHPCS and was corrected. These were resolved
by the documented code/locator fixes; required business assertions were retained.
No production roles, workflow configuration, CSS, existing specs or Playwright
configuration were changed. Pointer Media type switching remains the explicit
manual/theme follow-up above.
