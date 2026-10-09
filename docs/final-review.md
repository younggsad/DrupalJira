# DrupalJira Final Review

Review date: 2026-10-08. Reviewed `develop` at `3d2c83b`.

This report records the final review before merge/release. Findings were not fixed during the review. File links below are relative to this document.

**Historical baseline:** Findings and test records in sections 2–9 describe the original `develop` review. Sections 1, 10 and 11 now summarize the current I2 assessment; section 13 records its evidence. Historical severity labels are retained, not a count of current open defects.
The [second review of the TimeLog access-control changes](#12-second-review--timelog-access-control)
below records the current C2/H1/H2 implementation separately. Its scoped verdict
is **READY TO COMMIT**, not approval to release the entire project. This document
predates that implementation and must remain outside its focused commit.

## 1. Executive Summary

- **Current I2 status: deployment and tested legacy/migration behavior PASS; recovery assurance INCOMPLETE.**
- **Release recommendation: HOLD pending recovery verification.** Theme settings schema remains an open LOW finding.
- The original review found 2 CRITICAL, 5 HIGH, 7 MEDIUM, 1 LOW and 2 INFO findings. Those counts are historical; see section 13 for current reassessment.

On 2026-10-09, Core 11.4.8, Composer metadata/audit, clean installation from exported configuration, configuration import, cache rebuild, requirements, update status and cron were executed. Disposable-data tests exercised legacy reconciliation, revision preservation, pre-existing Task transitions and the migration lifecycle. Three read-only board browser tests passed; the full E2E suite was not rerun.

**Validation incident:** an attempted isolated installation selected the default database and replaced it. The existing 2026-10-09 20:51 snapshot was restored; Task IDs/revisions/statuses match the captured starting diagnostics. Update 10003 and cron were reapplied to recover schema/requirements health. Full database equivalence to the session's starting state cannot be proved. This overrides an unconditional release approval. No code fix, commit, push, branch switch or history modification was made.

## 2. Critical / High Findings

### C1 — CRITICAL: Exported configuration cannot be imported

**File/location:** [Task teaser display configuration](../config/sync/core.entity_view_display.node.task.teaser.yml), line 15, `dependencies.module`.

**Problem:** The configuration declares a dependency on `number`, which does not exist in this Drupal installation.

**Evidence:** Drupal's configuration import validator rejected synchronization:

> depends on … number … that will not be installed after import

**Why it matters:** This blocks deployment through configuration import.

**Recommended fix:** Regenerate the display configuration with valid dependencies. Also reconcile the three differing Task form/default/teaser configurations: the local site hides `field_status` and explicitly configures moderation, while the export differs. Validate the corrected export on a disposable deployment environment.

**Block release:** **Yes.**

### C2 — CRITICAL: Every authenticated user has full TimeLog administration

**File/location:** [Authenticated role](../config/sync/user.role.authenticated.yml), line 22; [TimeLogAccessControlHandler](../web/modules/custom/drupaljira_timelog/src/TimeLogAccessControlHandler.php), `checkAccess()`.

**Problem:** The authenticated role receives `administer time_log`. The access handler grants that permission unrestricted access without ownership checks.

**Evidence:** An in-memory ordinary authenticated account was allowed to delete an existing TimeLog belonging to another user. No deletion was performed.

**Why it matters:** Any logged-in user can alter or delete other users' time records and falsify project totals.

**Recommended fix:** Remove the administrative permission from the authenticated role. Define the intended create/view/edit/delete permissions, including ownership restrictions where appropriate, and enforce them through entity access and routes. Add negative authorization tests.

**Block release:** **Yes.**

### H1 — HIGH: Custom endpoints omit referenced entity access checks

**File/location:** [TimeLog routes](../web/modules/custom/drupaljira_timelog/drupaljira_timelog.routing.yml), lines 88–112, `write_off` and `project_stats`; [ProjectStatisticsBlock](../web/modules/custom/drupaljira_timelog/src/Plugin/Block/ProjectStatisticsBlock.php), `build()`; [TaskStatService](../web/modules/custom/drupaljira_timelog/src/Service/TaskStatService.php), `getProjectStats()`.

**Problem:**

- Statistics require only `access content`, without checking the requested Project's view access.
- Statistics queries explicitly bypass Task access.
- The logging form requires `create time_log`, without checking access to the referenced Task.

**Evidence:** With a Project made unpublished **only in memory**, anonymous entity view access was denied, but the statistics block still produced its output.

**Why it matters:** The statistics endpoint can expose restricted Project information and aggregate restricted Tasks. Users with logging permission can write against Tasks they cannot access.

**Recommended fix:** Require referenced entity access, validate bundles, and check parent Project access where needed. Filter statistics according to the intended visibility policy and propagate access cache metadata.

**Block release:** **Yes.**

### H2 — HIGH: Production debug routes allow unprotected writes and insufficiently protected reads

**File/location at original review:** [Debug routes](../web/modules/custom/drupaljira_timelog/drupaljira_timelog.routing.yml), former lines 49–86; former `src/Controller/TimeLogDebugController.php` in `drupaljira_timelog`, `crud()`, `list()`, `sum()`. The controller and these routes have since been removed for H2.

**Problem:** The CRUD endpoint accepts GET and performs database writes without CSRF protection. All three routes require only `access administration pages`, rather than TimeLog and Task access.

**Why it matters:** A permission intended for navigating administration pages authorizes data operations. An authenticated request can trigger the CRUD lifecycle without intentional form submission. The read endpoints also lack entity-level authorization.

**Recommended fix:** Remove these demonstration endpoints from production. If retained for development, gate them appropriately; use a CSRF-protected POST/form for mutations and explicit entity access for reads.

**Block release:** **Yes.** The mutating endpoint was not invoked during review.

### H3 — HIGH: Existing Tasks are incompatible with the board workflow

**File/location:** [Board hooks](../web/modules/custom/drupaljira_board/drupaljira_board.module), line 36, `drupaljira_board_entity_presave()`; [TimeLog update hook](../web/modules/custom/drupaljira_timelog/drupaljira_timelog.install), `drupaljira_timelog_update_10001()`; [TaskBoardController](../web/modules/custom/drupaljira_board/src/Controller/TaskBoardController.php), `updateStatus()`.

**Problem:** Among the 20 pre-existing Tasks:

- **18** have different `field_status` and `moderation_state` values.
- **14** use moderation state `published`, outside the board's supported workflow states.
- **2** lack a recognized board status and are omitted from the columns.

The synchronization hook copies moderation values directly into a field that accepts only the four board states. Copying `published` fails that field's validation.

**Why it matters:** Existing Tasks can disappear from the board or become impossible to transition through its endpoint. Fresh fixtures avoid this problem by explicitly assigning compatible states.

**Recommended fix:** Provide a controlled, idempotent update that reconciles existing Tasks according to their intended status. Preserve revision history and validate resulting entities. Test legacy Tasks, not only newly created fixtures.

**Block release:** **Yes.**

### H4 — HIGH: TimeLog canonical pages request a nonexistent form handler

**File/location:** [TimeLog canonical route](../web/modules/custom/drupaljira_timelog/drupaljira_timelog.routing.yml), line 17; [TimeLog entity](../web/modules/custom/drupaljira_timelog/src/Entity/TimeLog.php), form handlers.

**Problem:** The route specifies `_entity_form: time_log.default`, but the entity registers only add, edit, delete, and bulk-delete forms.

**Evidence:** Drupal throws `InvalidPluginDefinitionException`: the entity did not specify a `default` form class.

**Why it matters:** Authorized canonical requests fail instead of displaying a TimeLog.

**Recommended fix:** Make the canonical route render the entity with view access. Keep editing on the edit route.

**Block release:** **Yes.**

### H5 — HIGH: Backlog migration loses imported workflow statuses

**File/location:** [Backlog migration](../web/modules/custom/drupaljira_timelog/migrations/drupaljira_backlog.yml), line 15; `drupaljira_board_entity_presave()`.

**Problem:** The migration maps CSV statuses into `field_status` but never sets `moderation_state`. The presave hook overwrites the imported status with the default moderation state.

**Evidence:** An unsaved migration-shaped Task with `field_status = review` became `backlog` after invoking the hook.

**Why it matters:** Imported working, review, and completed Tasks lose their intended status.

**Recommended fix:** Map CSV status into `moderation_state` and let the hook derive `field_status`. Define how existing migrated records are corrected, and test import/update/rollback on disposable data.

**Block release:** **Yes** for the shipped migration functionality.

## 3. Medium Findings

| ID / severity | File and location | Problem and impact | Concrete recommended fix | Block release? |
| --- | --- | --- | --- | --- |
| **M1 — MEDIUM** | [Text Paragraph template](../web/themes/custom/drupaljira_theme/templates/paragraph/paragraph--text.html.twig), line 2 | Renders `content.body`; the configured field is `field_body`. Text Paragraph content is silently omitted. | Render `content.field_body`; add a documentation rendering regression. | **Yes**, for documentation functionality. |
| **M2 — MEDIUM** | [TimeLog entity](../web/modules/custom/drupaljira_timelog/src/Entity/TimeLog.php), line 89, `postSave()` | Reassigning a TimeLog invalidates only the new Task's cache tags. The old Task's cached time summary can remain incorrect. | Invalidate tags for both original and current Task references, or attach suitable TimeLog dependencies to summaries. | No; fix before relying on reassignment. |
| **M3 — MEDIUM** | [Statistics service](../web/modules/custom/drupaljira_timelog/src/Service/TaskStatService.php), line 38, `getLoggedHours()` / `getProjectStats()` | Each Project calculation queries logs per Task and loads every matching log merely to sum hours. Work scales with both Task and log counts. | Use grouped aggregate queries and batch calculations over the permitted Task IDs. | No. |
| **M4 — MEDIUM** | [Sprint access checker](../web/modules/custom/drupaljira_board/src/Access/SprintAccessCheck.php), line 33 | Boolean node access discards its cache contexts/tags. Early denied results also omit node dependencies. Cached access/menu results can become stale. | Obtain the node's cacheable access result and combine it with the Scrum condition, retaining metadata on denied paths too. | No; required before introducing restricted Projects. |
| **M5 — MEDIUM** | Former `TimeLogDebugController`, `sum()`, lines 174–184 (controller removed for H2) | Casts the aggregate result row array to float instead of reading `hours_sum`. A real sum of **14.25** becomes **1**. | Read the aggregate column from the first row, handling NULL as zero; alternatively remove the debug endpoint. | No. |
| **M6 — MEDIUM** | [Estimate field storage](../config/sync/field.storage.node.field_estimate.yml), line 1; active installed schema | Drupal reports `node.field_estimate` needs updating, while `updatedb:status` reports no available updates. A normal update run does not resolve the discrepancy. | Diagnose installed schema metadata versus actual tables and supply the appropriate tested repair/update. | **Yes**, until deployment schema health is established. |
| **M7 — MEDIUM** | [Success message styling](../web/themes/custom/drupaljira_theme/css/components/messages.css), line 19; color tokens | Success text contrast is approximately **3.54:1** at 14.5px, below the normal-text 4.5:1 threshold. | Use a darker success text color while retaining the existing surface color. | No; accessibility fix recommended. |

Drupal requires access checkers to retain the cacheability of their decisions; M4 follows that documented requirement. [Drupal access-checker cacheability guidance](https://www.drupal.org/docs/8/api/cache-api/access-checkers-cacheability).

## 4. Low / Informational Findings

### L1 — LOW: Theme settings lack configuration schema

**File/location:** [Theme settings](../config/sync/drupaljira_theme.settings.yml), line 1; missing theme `config/schema` definition.

**Problem/impact:** Drupal reports no schema for `drupaljira_theme.settings`, limiting typed configuration validation.

**Recommended fix:** Declare the settings schema using Drupal's theme-settings schema conventions.

**Block release:** No.

### I1 — INFO: Composer reports a core advisory; its affected feature is disabled

**File/location:** [Composer lock](../composer.lock), line 963, Drupal core 11.4.5.

**Observation:** Composer audit fails for **SA-CORE-2026-013**. The advisory concerns CKEditor XSS and identifies 11.4.7 as the fixed 11.4 release. CKEditor is absent from the enabled modules in this project, so the stated exploitation condition is not currently present. [Official advisory](https://www.drupal.org/sa-core-2026-013).

**Recommended fix:** Update to a supported patched release and rerun checks before enabling CKEditor.

**Block release:** No under the reviewed configuration; the audit remains failing.

### I2 — INFO: Passing tests do not establish deployment or legacy-data correctness

**File/location:** [E2E fixtures](../tests/e2e/drupal/fixtures.php), line 32, `e2e_preflight()` and `e2e_accounts()`; existing specs.

**Observation/impact:** Tests use active configuration, initialize compatible workflow states, and grant temporary journey permissions. Persistent roles lack `create time_log` and the early workflow transitions. No PHP unit/kernel suites were found.

**Recommended fix:** Add coverage for exported-config deployment, legacy workflow conversion, TimeLog authorization/canonical pages, Paragraph rendering, migration statuses, and reassignment cache invalidation. Explicitly define the intended persistent persona permissions.

**Block release:** No independently; the uncovered bugs above do block it.

## 5. Security Review

**Realistic security problems were found.** These include unrestricted TimeLog administration for authenticated users, missing referenced-entity authorization, and the unprotected debug mutation route.

Positive findings:

- Board status POST requires node update access and a CSRF header token.
- Workflow transitions use Drupal's transition-validation service.
- Reviewed Twig retains normal escaping; no custom `|raw` or trusted-markup bypass was found.
- No tracked credentials, authentication state, generated dependencies, or public uploads were identified.

The reported dependency XSS advisory is conditional on disabled CKEditor functionality; no active custom-code XSS vulnerability was established.

## 6. Drupal 11 Compatibility

PHP 8.4 syntax checks and configured PHPStan analysis pass. Custom plugin discovery succeeds.

**Incorrect Drupal API/configuration usage exists:** the nonexistent canonical form handler, invalid module dependency, aggregate result handling, and discarded access cacheability.

No additional deprecated Drupal API use was identified in the reviewed custom implementation. Procedural hooks alone are not a Drupal 11 compatibility failure.

## 7. Code Quality

| Check | Result |
| --- | --- |
| PHPStan, configured level 5, custom modules/theme | **PASS** |
| PHPCS, Drupal and DrupalPractice | **PASS** |
| Playwright Chromium suite | **17/17 PASS**, 4.3 minutes |
| PHP 8.4 syntax | **32 files PASS** |
| JavaScript syntax | **22 files PASS** |
| YAML parsing | **199 files PASS** |
| Theme Twig parsing | **21 templates PASS** |
| Composer metadata/lock consistency | **PASS** |
| Composer security audit | **FAIL:** one conditional core advisory |
| Configuration import validation | **FAIL:** nonexistent `number` dependency |
| Configuration synchronization status | **Three Task displays differ** |
| Database update status | No pending update hooks |
| Drupal requirements | **FAIL:** estimate schema mismatch; cron last ran one month ago |
| Cache rebuild | **PASS** |
| Git diff whitespace check | **PASS** |

The host has no PHP executable. Initial sandboxed DDEV commands failed because Docker socket access was denied; checks were rerun successfully with approved container access. No configuration import, database update, migration, or cron execution was performed.

Commands included:

```bash
ddev drush status
ddev exec vendor/bin/phpstan analyse --no-progress
ddev exec vendor/bin/phpcs --report=summary
npx playwright test --project=chromium --workers=1
ddev exec composer validate --no-check-publish
ddev exec composer audit --locked --format=json
ddev drush config:status
ddev drush updatedb:status
ddev drush core:requirements --severity=2
ddev drush cache:rebuild
git diff --check
git status --porcelain=v1
```

Additional read-only Drush probes validated configuration import without importing, parsed YAML/Twig/PHP, inspected active routes and schema coverage, checked permissions with in-memory accounts, and compared entity-ID fingerprints before and after the browser suite.

## 8. Performance / Cacheability

The real issues are per-Task log aggregation/entity loading, stale summaries after log reassignment, and lost Sprint access metadata.

The landing controller otherwise uses pagination, batched entity loading, explicit entity/link access, and cacheable dependencies. Project statistics retain broad node/TimeLog list tags, although those tags do not replace authorization.

## 9. Frontend Review

The tested frontend is functioning well:

- Task modal opening, Escape/Close, focus restoration, and viewport changes pass.
- Media Library attachment selection and rendering pass.
- Board behaviors use `once()` and delegated handlers.
- Status updates restore cards on failure and announce feedback.
- Entity modal responses and the frontend-editing page template avoid application header/navigation markup.

Confirmed frontend defects are the missing Text Paragraph output and success-message contrast.

Responsive rules, shared tokens, reduced-motion handling, and component styling are present. Exact current Figma parity and a complete assistive-technology audit were not independently verified. Sprint functionality remains the explicitly documented stub.

## 10. Release Checklist — current I2 assessment, 2026-10-09

- [x] C1 exported configuration deploys on a clean disposable installation
- [x] Core audit has no advisories; Composer metadata/lock validation passes
- [x] Existing Task status consistency and board placement checked
- [x] Reconciliation and revision preservation tested on disposable data
- [x] Migration import/repeat/changed-source update/rollback tested
- [x] PHPStan and PHPCS rerun successfully
- [x] Cache/config/schema/update/cron checks executed
- [ ] Full current E2E/security suite rerun (historical results remain in section 12)
- [ ] Theme settings configuration schema supplied (L1, LOW)
- [ ] Recovery equivalence assessed and any snapshot-to-session data gap reconciled
- [x] Git working tree and documentation reviewed
- [ ] Ready for release

## 11. Final Verdict — current I2 assessment, 2026-10-09

**HOLD: recovery assurance remains incomplete.**

The historical configuration-import blocker, Core advisory, estimate schema discrepancy, current Task mismatches and migrated-status loss are no longer reproduced by the executed checks. Clean exported-config installation and the tested legacy/migration scenarios pass. This is not a blanket security, accessibility or full E2E certification.

The validation incident requires a separate recovery assessment before release: compare any newer authoritative backup or activity record against the restored 20:51 snapshot, including users, logs, revisions, configuration and other content. The captured Task diagnostics match, but no full starting database dump/fingerprint exists. L1 also remains open and needs a theme settings schema. Draft/Published remain intentionally outside the four board columns; decide whether those supported workflow states meet product expectations.

The original 2026-10-08 verdict was **NOT READY**, based on configuration import, TimeLog authorization and legacy workflow failures. Its detailed findings remain below/above as historical evidence; section 12 retains its independent scoped verdict unchanged.

## 12. Second review — TimeLog access control

Review date: 2026-10-08. Branch: `fix/timelog-access-control`. Base HEAD:
`3d2c83b`. Reviewed the complete tracked diff and all implementation untracked
files. Source code, configuration, tests, Git history, and the index were not
modified during this review. Only this document was updated, as explicitly
requested. No commit or PR was created.

### Overall verdict

**READY TO COMMIT** for C2/H1/H2, excluding this pre-existing review document.
No blocking security, correctness, or scope issue was found in the implementation.
This is a focused commit verdict; the original project's unrelated release
blockers remain outside the task.

### C2 findings

- The exported and active authenticated role no longer has `administer time_log`.
  It has the existing `create time_log`, `view time_log`, `edit time_log`, and
  `delete time_log` permissions. The permission titles describe the scoped model.
- Update hook `10002` revokes the administrative permission and grants only those
  four permissions. It preserves unrelated permissions and is idempotent. The
  local installed update version is `10002`; the existing `10001` hook is unchanged.
- The entity handler denies anonymous users, requires operation permissions,
  and requires ownership for normal-user update/delete. Administrative permission
  bypasses ownership, while referenced content access still applies.
- The owner field's edit access is restricted. `TimeLogForm::buildEntity()` also
  ignores forged owner input for non-administrators. `save()` authorizes the stored
  record before saving the submitted entity and checks submitted Task references.
- Edit/delete routes use `_entity_access`. The administrative collection and
  administrative add route remain restricted. Generic creation uses entity create
  access, and both creation forms check access again before saving. Core bulk-delete
  and delete-action implementations also invoke entity delete access.
- `TimeLogDeleteForm` only overrides cancel/redirect URLs so owners return to their
  Task instead of an administrative collection they cannot access. It does not
  override deletion, validation, or submission, and does not replace or bypass
  Drupal route access or Form API protection.

Read-only verification with an unsaved clone of an existing log produced:

| Account | View | Update | Delete |
| --- | --- | --- | --- |
| Anonymous | Denied | Denied | Denied |
| Owner with scoped permissions | Allowed | Allowed | Allowed |
| Authenticated non-owner | Allowed | Denied | Denied |
| Site administrator | Allowed | Allowed | Allowed |

Non-owner viewing is intentional collaborative read access to logs for accessible
Tasks/Projects. It does not grant mutation rights. No alternate normal application
entry point that permits unauthorized TimeLog writes was found.

### H1 findings

The logging route checks Task view access and the parent Project's view access.
The form repeats those checks before saving. Generic add/edit validation checks
the selected Task and Project, so replacing a submitted entity ID does not bypass
the rule. Entity access applies the same reference checks to existing records.

Project statistics upcast only existing Project nodes and require explicit
TimeLog viewing/administration permission plus Project view access. The controller
and statistics service enforce that policy independently of presentation.
Task queries use access checking and results receive explicit Task access checks.
Individual TimeLogs also receive entity view checks before aggregation.

The block, field formatter, listing, overdue report, and project-summary service
path use the guarded policy. Inaccessible content is filtered or denied; public
Task viewing alone does not expose time statistics to anonymous users.

Access checks return cacheable results. Rendered statistics, summaries, and
listings retain access metadata, user/permission/node-grant contexts, and relevant
entity/list tags. Denied responses retain cache metadata. `isAllowed()` is used
for decisions without replacing the render-facing access result with a boolean.
The report plugins return uncached data arrays; their boolean filtering is not a
lost render-cache dependency in a current application render path.

A read-only unsaved-parent probe confirmed that an otherwise viewable Task is
denied when its parent Project is unpublished to the current user; the result
retained both Task and Project tags and permission context.

### H2 findings

All three debug routes (`drupaljira.timelog_debug.crud`, `.list`, `.sum`) and
their controller are removed. Active route-provider lookups confirm the route
names are absent. Production module/theme code contains no remaining controller,
service, or normal UI references. Former URLs remain only in negative tests;
controller names in this document are historical review references.

No replacement debugging endpoint was introduced. The old aggregate demonstration
was removed with the controller for H2; its calculation was not separately fixed.

### File-by-file scope classification

Categories:

1. Required for C2/H1/H2.
2. Required test/fixture/documentation change.
3. Outside the implementation commit; preserve or revert as appropriate.

Module paths in the first table are relative to
`web/modules/custom/drupaljira_timelog/`, except the explicitly rooted config path.

| File | Category | Purpose |
| --- | --- | --- |
| `config/sync/user.role.authenticated.yml` (repository root) | 1 | Remove administrative access; grant scoped permissions. |
| `drupaljira_timelog.install` | 1 | Deploy the permission change through update `10002`. |
| `drupaljira_timelog.permissions.yml` | 1 | Describe view/statistics and owner mutation permissions. |
| `drupaljira_timelog.routing.yml` | 1 | Entity/reference access, Project bundle validation, debug route removal. |
| `drupaljira_timelog.services.yml` | 1 | Inject the current account into the existing statistics service. |
| `src/Access/TimeLogAccessCheck.php` (new) | 1 | Shared cacheable Task/Project and route policy. |
| `src/Controller/ProjectStatsController.php` | 1 | Guard the statistics entry point. |
| `src/Controller/TimeLogDebugController.php` (deleted) | 1 | Remove production demonstrations for H2. |
| `src/Entity/TimeLog.php` | 1 | Register the owner-compatible delete redirect form. |
| `src/Form/TimeLogForm.php` | 1 | Ownership protection, submitted reference checks, valid owner redirect. |
| `src/Form/TimeLogWriteOffForm.php` | 1 | Recheck referenced content and create access before saving. |
| `src/Form/TimeLogDeleteForm.php` (new) | 1 | Redirect owners without changing core deletion/access logic. |
| `src/Plugin/Block/ProjectStatisticsBlock.php` | 1 | Access-filtered rendering and retained cache metadata; GET-compatible AJAX link. |
| `src/Plugin/Field/FieldFormatter/TimeSummaryFormatter.php` | 1 | Prevent unauthorized time-summary disclosure and propagate metadata. |
| `src/Plugin/ReportGenerator/OverdueTasksReport.php` | 1 | Prevent restricted Task titles/statistics from entering reports. |
| `src/Service/TaskStatService.php` | 1 | Guard direct service use and filter inaccessible entities. |
| `src/TimeLogAccessControlHandler.php` | 1 | Central entity ownership/permission/reference policy. |
| `src/TimeLogListBuilder.php` | 1 | Filter records explicitly and retain allowed/denied access metadata. |

The following paths are relative to the repository root:

| File | Category | Purpose |
| --- | --- | --- |
| `README.md` | 2 | Document the scoped model, update hook, and removed debug functionality. |
| `docs/e2e.md` | 2 | Update persona permissions and describe the new security fixtures/tests. |
| `tests/e2e/drupal/fixtures.php` | 2 | Stronger permission preflight; owned administrator/private-content fixtures. |
| `tests/e2e/fixtures.mjs` | 2 | Provision/authenticate optional fixtures with existing cleanup. |
| `tests/e2e/smoke.spec.mjs` | 2 | Run the unchanged summary assertion as an authorized user; anonymous denial is separately covered. |
| `tests/e2e/timelog.spec.mjs` | 2 | Preserve logging/persistence assertions; assert collection denial and use a separate administrator for listing assertions. |
| `tests/e2e/timelog-access.spec.mjs` (new) | 2 | Focused authorization, forgery, inaccessible-reference, cache-variant, and removed-route regressions. |
| `docs/final-review.md` (pre-existing, untracked) | 3 | This historical/scoped review record is outside the implementation commit. Keep it locally; do not stage it with C2/H1/H2. |

### Architecture and minimality

The two new classes have distinct necessary purposes: a shared cacheable access
policy and owner-compatible deletion redirects. No new service or test framework
was introduced. The existing service gains only the account dependency needed for
authorization. Checks at routes, submitted references, and direct service calls
protect different entry points and do not grant access in conflict with the entity
handler. The repeated checks are acceptable defense at those boundaries.

There are no production hard-coded user IDs or fixture-role checks in the new
authorization policy. The `authenticated` role identifier in the update hook is
Drupal's built-in target role, not a fixture dependency. Temporary administrator
roles and private fixtures are optional, ledger-owned, and cleaned up; private
entity-reference validation remains enabled through a scoped account switch.

The implementation is minimal enough for a focused PR. No unrelated tracked file
or hunk needs restoration. Newlines adjacent to YAML edits do not warrant a revert.
No `git restore` commands are recommended. This untracked review document cannot
be restored with `git restore`; exclude it from the implementation commit rather
than deleting it.

### Validation and limits

This second pass ran the requested `git diff --stat`, complete `git diff`, and
`git status --short`, inspected all untracked files, checked whitespace, and ran
read-only Drupal probes for effective permissions, update version, owner/admin
access metadata, parent Project denial, and removed routes. It did not run cache
rebuilds, updates, configuration import, or browser fixtures. Existing 106 TimeLogs
all reference existing Task nodes; no current orphan-record regression was found.

The preceding implementation validation passed PHPStan, PHPCS/DrupalPractice,
syntax checks, cache rebuild, and **23/23 Playwright tests**, including ownership
forgery, URL/request ID changes, parent access, AJAX refresh, and cross-account
cache checks. Those results are historical validation from the implementation
turn, not a newly rerun test suite in this read-only pass. PHPUnit/kernel tooling
remains unavailable as previously reported.

Unrelated C1/H3/H4/H5/M1–M7/L1 and core-version work were not requested here.
The known H4 canonical form-handler failure remains outside this scoped verdict.
No new security vulnerability or blocking Drupal architecture issue was found.

## 13. I2 deployment and legacy validation — 2026-10-09

### Scope and repository

Inspected branch `feature/i2-deployment-legacy-validation`, HEAD `6cc5976`, and recent merged fixes for configuration, workflow/migration, TimeLog routing/statistics, theme cacheability/contrast and Core 11.4.8. Starting status was only `?? docs/final-review.md`. Read the complete historical report, Composer files, exported Task/workflow/role configuration, module update/post-update hooks, migration YAML/CSV, E2E fixtures/specs and integration scripts. No implementation changes were necessary. Section 12 is preserved verbatim.

### Installation isolation incident and recovery

The first `ddev exec vendor/bin/drush --uri=http://i2-review site:install --existing-config -y` **incorrectly selected `sites/default` / `db`**. Merely creating a site directory and specifying a URI did not enable multisite: `web/core/lib/Drupal/Core/DrupalKernel.php::findSitePath()` returns the default site when `sites/sites.php` is absent. The installer succeeded against the wrong database; that success is **not** disposable deployment evidence.

Recovered with `ddev snapshot restore DrupalJira_20261009205114` (successful). All 21 captured Task IDs, current revision IDs and status pairs matched after restoration; restored counts were 26 nodes and 106 TimeLogs. However, the snapshot predates update 10003 and cron. The restored working site initially reported pending 10003, schema mismatch and stale cron. Tested 10003 on the disposable copy, confirmed an empty entity-definition change list, then successfully reapplied `ddev drush updatedb -y` and cron on the working site. Final working-site checks again returned no pending updates, no configuration differences and no error-level requirements. Cron exited 0 but warned that a temporary public image selected by garbage collection was already missing on disk; cron can change temporary-file metadata. This was recovery of the starting health state, not a legacy data repair.

**Limitation/blocker:** full data preservation is not established. There is no complete pre-incident database snapshot from this session. Task matching does not prove TimeLog values, accounts, configuration/state or all revisions are identical. Next action: reconcile the snapshot interval using an authoritative newer backup/activity record and explicitly review recovery completeness. Do not describe this session as non-destructive.

### Verified disposable deployment

A temporary explicit `$sites['i2-review'] = 'i2-review';` mapping in `web/sites/sites.php` enabled isolation. Before the successful disposable install, Drush status confirmed **`sites/i2-review` / `i2_review_20261009`**. This new database was created with scoped grants in the existing DDEV MariaDB 11.8 container. An initial retry failed because snapshot restoration had removed that database/grants; it was recreated, and the subsequent install returned exit 0.

README documents Composer install/config import, but supplies no complete fresh-site bootstrap procedure and still lists Core 11.4.5. Used Drush's existing-config installer to create the compatible site and apply `config/sync`, rather than guessing a profile/config UUID sequence.

| Executed command | Observed result |
| --- | --- |
| `ddev exec composer validate --no-check-publish` | Exit 0; composer.json valid, no lock inconsistency reported |
| `ddev exec composer audit --locked --format=json` | Exit 0; empty advisories, abandoned and filter arrays |
| `ddev drush status --fields=drupal-version,db-name,site,bootstrap` | Core 11.4.8, db, sites/default, successful bootstrap before incident |
| `ddev drush config:status` | No differences before incident and after restoration |
| `ddev drush updatedb:status` | No pending updates before incident; recovery sequence documented above |
| `ddev drush core:requirements --severity=2` | No findings before incident; recovery sequence documented above |
| `ddev exec vendor/bin/drush --uri=http://i2-review site:install --existing-config -y` | Confirmed disposable attempt: installation complete, exit 0 |
| `ddev exec vendor/bin/drush --uri=http://i2-review config:import -y` | No changes to import, exit 0; initial configuration import occurred during installation |
| Same disposable prefix: `cache:rebuild`, `cron` | Both exit 0; cache rebuild success |
| Same disposable prefix: `config:status`, `updatedb:status`, `core:requirements --severity=2` | No differences, no updates, no error-level findings on clean installation |
| Same disposable prefix: `migrate:status` | CSV migration available, six source rows, initially zero imported |
| `ddev exec vendor/bin/phpstan analyse --no-progress` | No errors, exit 0 |
| `ddev exec vendor/bin/phpcs --report=summary` | No violations, exit 0 |

Successful configuration installation establishes that the nonexistent `number` module dependency no longer blocks deployment. The Task teaser export now depends only on `text` and `user`; custom routes and `time_log` entity/form definitions resolve. Active theme schema probe still returns false for `drupaljira_theme.settings` (L1). Requirements at severity 2 do not establish complete typed-configuration schema coverage.

### Legacy Tasks and mapping policy

Read-only starting inspection found **21/21 Tasks** with matching `field_status`/`moderation_state`, all using backlog, in_progress, review or done, with latest revision equal to current revision. Counts: backlog 6, in_progress 6, review 4, done 5. No current Task was outside a recognized board column. The original 20-Task mismatch counts are historical.

`drupaljira_timelog.post_update.php::drupaljira_timelog_post_update_reconcile_task_workflow()` defines the policy: todo → backlog, working → in_progress, qa → review, completed → done; canonical moderation states except Published take precedence. Legacy Published recovers a recognized field status; Published without a more specific status and intentional Draft remain their own workflow states. Unknown values, unrecoverable values and pending latest revisions stop preflight before entity writes. Repairs create new revisions with original status/publication values in the revision log; historical revisions are not rewritten. No reconciliation hook was executed against the working database.

Draft and Published are allowed field/workflow states but **not board columns**. Their omission remains an explicit functional limitation. Current stored Tasks do not exercise that limitation; it must not be described as support for placing every possible workflow state on the board.

A read-only preservation probe verified all 18 original revision IDs recorded by the prior workflow review still exist and that title, ownership, creation timestamp, body, Project, assignee, attachments and estimate match the repaired entities. Three existing browser probes passed for Projects 1, 5 and 29, checking 20 recorded legacy Task placements. Task 2197's backlog status was inspected, but it was not included in those browser assertions.

After deployment checks, copied `db` into the disposable database using a read-only source dump. Executed the repository workflow integration test with only its database guard changed to `i2_review_20261009` in an ignored copy. Results: canonical/publication consistency and second-run revision idempotency passed; unknown status rejection passed; all four legacy aliases and retained original revisions passed; pending-revision refusal passed. The test's unknown-status assertion does not comprehensively fingerprint every revision, so its printed message alone is not stronger preservation evidence.

An additional guarded disposable probe performed seven transitions on copied pre-existing Tasks 18, 3 and 7 (start, review, approve and reopen as applicable). Matching stored fields and retained original revisions were checked after reload. A disallowed in_progress → done request returned 400 without changing status/revision. These invoke the controller as UID 1; they do **not** test HTTP CSRF routing or persistent regular-user transition permissions. E2E journey permissions remain temporary fixture permissions.

### Migration lifecycle evidence

`web/modules/custom/drupaljira_timelog/migrations/drupaljira_backlog.yml` now sets moderation_state using static_map, derives field_status from it, and sets publication to 1. The presave hook copies the same canonical moderation value, so all six actual imported entities retained their intended statuses after save/reload. Source scope: the shipped six-row backlog.csv, referencing Test Project and Second Project; all four legacy statuses exercised. Migrate CSV, static_map, entity_lookup and entity:node plugins executed successfully; Drush import/rollback/status commands are available.

Tests used a distinct stub migration ID `workflow_review_backlog`, leaving the shipped migration map untouched, on disposable data. Initial import passed with states backlog, in_progress, review, done, backlog, in_progress. A first repeat harness incorrectly reused a source generator and failed with “Cannot rewind a generator that was already run”; fresh migration instances fixed the harness. Repeat retained six destination IDs/maps. A changed temporary CSV updated row 1's title and todo → working state on the same entity ID, with all six destination IDs retained. Added missing-status and unknown-status rows were skipped (map row status 2, null destination), with no destination entities. An initial assertion misread the nonempty skipped-row map as an entity; inspecting its null destination corrected that harness issue.

Rollback completed successfully with fresh instances; all six imported entities and imported mappings were removed. This proves rollback of newly imported records in this test. It does not prove restoration of prior versions of pre-existing destination entities; entity:node rollback should not be treated as an undo-history system. Repairing previously shipped incorrectly mapped Tasks relies on the explicit reconciliation policy, not a migration rerun inferred from YAML.

Executed scripts: `tests/integration/task-workflow.php` via guarded ignored copy `.playwright/i2/task-workflow.php`; supplemental `.playwright/i2/migration-followup.php` and `.playwright/i2/transitions.php`; `tests/integration/timelog-statistics.php` on the clean disposable site; read-only `tests/integration/sprint-access.php` on the working site. TimeLog canonical form rendering, reassignment invalidation, statistics and batched queries passed; Sprint access/cacheability passed 19 cases.

### Historical finding reconciliation

| Finding | Current assessment and evidence |
| --- | --- |
| C1 | Resolved/verified: clean existing-config install and subsequent no-op import; number dependency removed |
| C2/H1/H2 | Fixes present; authenticated admin permission absent, debug routes absent, shared reference access policy present. Section 12 remains the scoped review; full authorization suite not rerun here |
| H3 | Current stored mismatches resolved/verified; disposable reconciliation and existing-Task controller transitions pass. Draft/Published board omission remains a policy limitation |
| H4 | Resolved/verified: default form handler is now registered; canonical form renders in integration regression. Canonical is still a form, not a read-only entity view |
| H5 | Resolved/verified for tested source/lifecycle: both status fields survive actual import/update; malformed values skip |
| M1 | Code fix verified: paragraph template renders content.field_body; full rendering regression not rerun |
| M2/M3 | Resolved in focused integration: old/new Task/Project cache invalidation and batched log queries pass |
| M4 | Resolved/verified: 19 Sprint access/cacheability cases pass |
| M5 | Resolved by removed debug controller/routes; not a separately tested sum fix |
| M6 | Cleared before incident; update 10003 tested on copied populated data, empty change list, then reapplied during recovery |
| M7 | Code fix verified: success color #027a48 on #ecfdf3; complete accessibility audit not run |
| L1 | Still open: active typed-config schema probe false, no theme settings schema file |
| I1 | Resolved/verified: lock/installed Core 11.4.8 and audit with no advisories |
| I2 | Coverage substantially improved through executed disposable deployment/legacy/migration checks; full E2E/security coverage and recovery equivalence not established |

### Reproduction and remaining checks

For a new disposable multisite, create a uniquely named database and restricted grants, a separate settings.php/database/files directory, and an explicit sites.php host mapping. **Before site:install, verify both database and site path with `drush --uri=... status --fields=db-name,site`; abort unless both match the disposable target.** Snapshot restoration resets all databases/grants included in the server snapshot. Do not reuse the failed first-install procedure.

The guarded supplemental scripts remain under ignored `.playwright/i2/`; the temporary sites.php mapping is removed after validation to restore ordinary site selection. To rerun, recreate the mapping, verify status, and use the documented commands above. Review script database guards before executing; they intentionally create/delete disposable fixtures. The disposable database and ignored site files are retained for inspection, not part of deployment configuration.

**Passed:** Composer validation/audit; confirmed disposable clean installation/config import/cache/requirements/update status/cron; PHPStan/PHPCS; 21 Task state diagnostics; 18 original-revision checks; 3 board browser tests; reconciliation idempotency/aliases/preflight; 7 existing-Task controller transitions plus invalid-transition preservation; migration import/repeat/changed-source update/invalid source/rollback; TimeLog integration; 19 Sprint access cases.

**Failed attempts:** wrong-target installation (recovered with incomplete equivalence assurance); first isolated retry after restore lacked database privileges; reused migration generator; skipped-map assertion; one supplemental php:eval failed at shell quoting before PHP execution. These failures are not reported as successful checks.

**Open/failed checks:** theme settings schema absent (LOW); complete incident recovery equivalence unverified (release hold).

**Skipped/not run:** full current E2E/security suite; PHP unit/kernel suites; complete accessibility audit; normal-user HTTP transitions on pre-existing Tasks; migration rollback restoring previous versions of existing destinations; full multilingual/concurrent-write repair scenarios. No production-like destructive legacy repair was run. No commit or push was made.
