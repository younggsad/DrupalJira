# LLM review — Task 9.1

Review performed by the implementing Codex LLM against the implementation, not
only the architecture plan. This is a self-review, not an independent second
model review. Focus: isolation, secrets, fixture recovery, and flakiness.

| Recommendation | Decision and rationale |
| --- | --- |
| Use independent data per test and replacement worker | Accepted: test/worker UUID namespaces; no serial dependencies or shared mutable Task |
| Avoid authenticating as UID 1 | Accepted: existing Project Manager and regular accounts preserve real approval permissions |
| Preserve existing role configuration | Accepted: preflight detects drift; no configuration writes |
| Validate all fixture state through ordinary transitions | Rejected: existing roles cannot traverse all states and workflow has no self-transition for initial Backlog. Only the moderation transition constraint is skipped for snapshot creation; configured states and other constraints are validated |
| Avoid direct SQL fixture inserts | Accepted: Entity API preserves save/delete hooks, revisions, and cache invalidation |
| Maintain recoverable ownership before saves | Accepted: durable UUID/file ledger and explicit namespaced reset recover partial setup |
| Allow cleanup despite permission/workflow drift | Accepted: cleanup validates target identity rather than requiring the current fixture contract |
| Keep passwords out of process arguments and diagnostics | Accepted: private JSON inputs, generated passwords, field-path-only validation errors; no secrets in manifests |
| Protect storageState permissions | Accepted: worker directories mode 0700 and files mode 0600; teardown removes them |
| Keep tests on Playwright's built-in page/context fixtures | Accepted: persona is a storageState option, preserving runner screenshot/trace capture for authenticated tests |
| Use separate output directories for concurrent invocations | Accepted: random artifact ID, validated explicit override, latest-failure pointer |
| Replace sleeps with observable conditions | Accepted: locators, retrying assertions, navigation waits, and awaited public HTTP responses |
| Account for lazy Media image loading | Accepted: scroll the fixture image into view and poll its natural width, verifying an actual successful image load |
| Match locators to rendered markup | Accepted: exact board headings and article-scoped Media/time-summary checks replace incorrect field-class assumptions; no theme edits |
| Enable retries to conceal transient failures | Rejected: zero retries by default; repeat and parallel checks expose instability |
| Add HTTP fixture endpoints or a development Drupal module | Rejected: Drush is already installed; new application-facing infrastructure is unnecessary |
| Disable HTTPS validation globally | Rejected: Node/system TLS remains unchanged. A local-context exemption is accepted only for the exact guarded DDEV BASE_URL; strict checking remains available via `E2E_IGNORE_HTTPS_ERRORS=false` |
| Capture diagnostics in manually created authentication contexts | Accepted: explicit login tracing, failure screenshot/trace, and report attachments; successful login traces are discarded |
| Require Media for every smoke case | Rejected: optional fixture option plus separate public Media test avoids unrelated setup cost |

Residual limits: the suite targets this local DDEV/site UUID only; interactive
headed/debug/UI modes require a display; traces/reports may contain sensitive
page/session information; abrupt termination requires explicit recovery.

Execution evidence and remaining limitations are recorded in `docs/e2e.md`.
The final suite passed 6/6, parallel repetitions passed 18/18, and all six cases
passed separately in reverse order. Intentional authentication and assertion
failures verified screenshot/trace/report retention. Cleanup preserved the
pre-test entity/revision fingerprints and removed fixture state and uploads.
