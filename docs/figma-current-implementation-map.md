# Current Figma implementation map

Audit date: 2026-10-07. Source: https://www.figma.com/design/awCTE8bnIYw3Ew03ZiJMX6/Jira-Clone--Community---Copy-

## Direct MCP findings

`get_metadata` enumerated all three document pages and their subtrees:

- `24:14931`, Cover: one marketing cover, `24:49336`, containing Hero Section `24:49337`, marketing header, title, CTA, and five marketing cards. This is not an application dashboard.
- `2:6005`, Design System: spacing/grid documentation, typography `2:3014`, icon library `2:3195`, color foundations `1:1313`, and Grid System `24:49670`.
- `24:14929`, Components: logos, buttons, avatars, inputs, dropdowns, navbar, sidebar and active sidebar items, project template cards, attachments, table variants, summary cards and six summary widgets, Quickstart, and Kanban Card variants.

No assembled application page frames, task-detail/editing modals, sprint screens, or mobile application screens appear in these metadata subtrees. Component variants represent states, not evidence of competing current page versions. No explicitly named favicon was found; logo availability does not establish which favicon is intended.

High-fidelity `get_design_context` reads confirmed:

- `2:3014`: actual font properties use Outfit, despite the documentation sample text saying “Inter”; sizes 32, 24, 20, 18, 16, 14 and 12 with multiple weights.
- `10:1105`: “Kanban Card” is an empty **column header**, with status, count, menu and Create action, not a populated task entity card. It uses a #f5f5f5 surface, 12px padding, 8px radius, 20px vertical gap and actual SVG assets. Its returned render was inspected.
- `14:7207`: Priority breakdown requires Highest/High/Medium/Low classifications and counts. Its sample bars and numeric labels must not be copied as application data.

These findings concern this file only. The pre-existing untracked `frontend-redesign-audit.md` concerns another file and remains untouched.

## Figma → Drupal mapping

| Design area | Existing route / presentation | Current implementation and required change |
| --- | --- | --- |
| Navbar `9:1835`, sidebar `9:2004` / `16:13492`, sidebar item `17:13554` / `17:13556` | `page.html.twig`, header/main/sidebar CSS, theme regions | Partial shell exists. Replace placeholder marketing links with access-aware existing navigation; extract exact component contexts/assets first. File does not specify their composition on application pages. |
| Marketing cover `24:49337` | No corresponding marketing route; configured front is `/user/login` | Do not replace login or create a marketing route solely from the cover. |
| Summary cards `14:6573`, status overview `14:7206` | Project `/node/{node}`, project template, ProjectStatisticsBlock / TaskStatService | Real estimate, logged time, remaining time, completed/total and over-estimate metrics exist. Current presentation is a list. Summary styling is reusable, but no project/dashboard page frame specifies layout. Status distribution would require an access/cache-aware aggregation extension. |
| Kanban variants `10:1105`, `10:1136`, `10:1165` | `/project/{project}/board`, Views task_board, board Twig/preprocess/CSS | Four real workflow columns exist. Adapt column-header variants to real statuses/counts, preserving Views project isolation. No populated task-card design is supplied. |
| Inputs `4:1401`, dropdowns `9:589`, buttons `2:6121` | `/node/add/project`, `/node/add/task`, `/node/{node}/edit`, Drupal entity forms | Functional widgets and submissions exist; apply extracted component states to native widgets without replacing render arrays or labels. No complete task-edit screen is supplied. |
| Attachments `16:12125` | Task node fields and media formatters | Real attachments exist. Reuse formatter output and permissions; do not copy component sample documents. |
| Task details | `/node/{node}`, `/task/{node}/modal`, task template | Functional entity rendering and dialog fetch exist; no matching assembled detail/modal frame in this file. Preserve all fields and actions. |
| Table variants `14:10425` | Time-log collection `/admin/content/time-log`, native Drupal tables | Apply verified table primitive styling where appropriate; retain row operations and access. Design examples do not establish new list routes. |
| Project templates `8:509` | Project type field, create/edit forms | Kanban/Scrum types already work. Do not add the pictured Web template as an unsupported backend project type. |
| Sprint views | `/project/{node}/sprints`, SprintController / SprintAccessCheck | Scrum access exists, but controller returns a stub. No sprint entity/lifecycle is implemented in the inspected custom code. Cannot present operational sprint UI. |
| Priority breakdown `14:7207` | No configured task priority field or aggregation contract | Backend capability missing. Do not render the sample chart or invent priority values. |
| Other dashboard widgets `14:7211`, `14:7208`, `14:7210`, `14:7209` | No dedicated dashboard route/controller found | Activity, work types, workload and related-project contracts need explicit backend mapping and further component reads. Assignees alone do not establish workload semantics. |
| Mobile, loading, errors, empty states | Existing responsive CSS, Drupal messages/forms, board fetch behavior | No explicit mobile application or application error/loading frames found. Responsive adaptation is possible after page composition is established; preserve native validation and add translated real-state feedback. |

## Protected contracts and source risks

Preserve entity render arrays/field access, route permissions, CSRF header tokens, moderation validation, normal Save versus Save as transition, project contextual filter, cache metadata, real time-log formatting and statistics refresh wrapper. Preserve article/title hooks and `data-task-id` / `data-status` consumed by board behavior and regression tests.

Source inspection confirms global libraries reference missing `css/pages/project.css` and `css/pages/task.css`; board handlers initialize only cards present at first attach, intercept nested links, and report failures only to console. Modal endpoint returns rendered HTML without an attachment response processor. These require focused verification/fixes during implementation, not a backend rewrite.

## Implemented composition

The user's follow-up superseded the initial implementation gate. Missing screens and data no longer block unrelated frontend work. Figma defines component presentation; the existing Drupal routes and fields define application composition.

Implemented shared tokens, controls, surfaces, access-aware header/project navigation, native project/task layouts, real statistics cards, task board headers/counts/empty states, existing forms, task dialog presentation, scrollable tables and a Sprint stub shell. The project navigation uses the real current project rather than a fabricated recent-project list. Available create links use existing entity forms and access checks. The board Create action is restricted to Backlog because normal task creation preserves the workflow default; no unsupported arbitrary-state creation is implied.

The task dialog retains the HTML endpoint and now uses a DOM element with Drupal's dialog lifecycle, behavior attachment/detachment and focus return. Board event delegation supports newly inserted cards, permits nested field links to navigate, announces loading/errors and restores the original position after an unsuccessful real status request. Counts and empty states update from the actual rendered cards.

Statistics retain existing translated aggregate strings and their real all-time meaning; no “last 7 days” label was borrowed from the Figma sample. Their existing AJAX refresh remains operational. The statistics plugin gained route cache contexts and project/node/time-log invalidation tags; aggregation, access contracts and services were not rewritten.

## Individual limitations and integration boundaries

| Feature | Figma definition | Drupal capability / implemented boundary |
| --- | --- | --- |
| Priority breakdown | `14:7207`, chart by priority | No task priority field or aggregator. `components/priority-breakdown.html.twig` accepts a real, access-checked/cacheable chart render array and otherwise displays a translated unavailable state. It is not mounted on live pages until a provider exists. No sample bars/counts/API were created. |
| Sprint operations | No assembled sprint screen | Existing Scrum-only route still returns its original stub message inside a styled shell. No sprint entity, lifecycle, planning controls or sample sprints were added. |
| Dashboard / activity / types / workload / related projects | Summary component inventory | No dedicated dashboard route or these data contracts in the audited custom implementation. Real existing project statistics use the summary surface. No routes, relationships, activity feed or productivity statistics were invented. |
| Teams, plans, notifications, commercial trial, search | Navbar sample controls | No matching application contracts found; unavailable controls and sample trial/account values are omitted. Existing account and entity-create routes remain available. |
| Favicon | No identified favicon node | Existing Drupal favicon configuration remains intact. A logo is not evidence of an intended favicon. No unrelated favicon was downloaded or substituted. |
| Remaining component variants / mobile frames | Component inventory, no assembled mobile screens | MCP reached the Starter/View read limit during extraction, confirmed by `whoami`. Unread variants were not guessed. Layout breakpoints adapt the existing Drupal information architecture; they are not claimed to be explicit Figma mobile frames. |
| Future modal field assets | Existing HTML fragment endpoint | Current detail styles are globally available and existing media/modals pass regression checks. The endpoint still has no general attachment-response processor for future formatter-specific libraries; this backend contract was preserved. |

## Assets

All downloaded assets are original SVGs from this exact Figma file. No temporary Figma URLs remain in source.

| Local asset | Figma source / slot | Verified geometry |
| --- | --- | --- |
| `assets/figma/jira-logo.svg` | Navbar `9:1835`, logo component `2:6045` | Unmodified 128 × 128 SVG scaled inside its 32 × 32 navbar slot. |
| `assets/figma/check.svg` | Summary Card `14:6572`, icon `14:6590` | 29 × 29 icon inside the 55 × 55 completion-circle slot. |
| `assets/figma/plus.svg` | Kanban `10:1105`, Create icon `I10:1093;2:6151` | 16 × 16 in the existing task-form link. |

No Figma sample entity, user, chart or statistical value is used as application data. Existing Outfit loading is reused.

## Files

New theme files:

- `templates/components/header.html.twig`
- `templates/components/project-navigation.html.twig`
- `templates/components/metric-card.html.twig`
- `templates/components/data-panel.html.twig`
- `templates/components/priority-breakdown.html.twig`
- `templates/drupaljira-project-stats.html.twig`
- `css/components/data-panel.css`
- `css/pages/project.css`
- `css/pages/task.css`
- The three SVG assets listed above.

Modified theme files:

- `drupaljira_theme.theme`, `drupaljira_theme.libraries.yml`
- `templates/page.html.twig`, board Views Twig override
- `js/global.js`, `js/task-board.js`
- Base CSS: `base.css`, `typography.css`, `variables.css`
- Layout CSS: `header.css`, `main.css`, `sidebar.css`
- Component CSS: `buttons.css`, `cards.css`, `forms.css`, `modal.css`, `paragraphs.css`, `tables.css`
- Page CSS: `statistics.css`, `sprints.css`; `task-board.css`

Other modified source: `ProjectStatisticsBlock.php` (cache metadata only). This audit document was created in the initial audit and updated to record implementation. The pre-existing `docs/frontend-redesign-audit.md` remains untouched. No tests, routes, workflows, entity definitions or configuration were changed.

## Validation results

- Drupal cache rebuild: passed through DDEV.
- PHPCS: complete configured Drupal/DrupalPractice checks, including PHP/module/theme/inc/install extensions, passed.
- PHPStan: configured level 5, passed.
- PHP syntax: modified theme and statistics block passed.
- Twig: every theme template compiled successfully, including the future priority integration boundary.
- JavaScript: both theme files passed `node --check`.
- Library paths: every declared CSS/JS file exists.
- `git diff --check`: passed.
- Existing Playwright: **14/14 passed** on the final run; no test changes or weakened assertions.
- Existing real-data browser review: nine routes × 1440/768/375px = **27 successful combinations**, no document overflow, asset-load failures or console errors. Routes covered login/account, project/task creation, project canonical/board, task canonical/edit/log-time and time-log collection.
- Additional real-data interactions: mobile keyboard dialog opening, Escape close, focus return/body-scroll release, genuine anonymous status denial (403) with rollback, and real AJAX statistics refresh passed. No mocked API responses were used.
- Original first-run failures were corrected: duplicate sidebar heading; transient Chromium `ERR_NETWORK_CHANGED` disappeared on rerun. A mobile table width issue found in review was fixed at the content container. No tests were altered.

Local browser review artifacts are under `.playwright/frontend-review/` (ignored verification scripts/screenshots/results). Existing E2E tests used their established isolated real-entity fixture lifecycle and cleanup; no fixture data or hardcoded test values were added to application presentation.

## Follow-up refinement

The subsequent frontend refinement added the authorized real-data `/projects` landing page, replaced the login front-page setting, polished forms/dialogs/account/media presentation, and preserved role-specific menus. This supersedes the earlier limitation that no application landing controller existed. See [frontend-polish-report.md](frontend-polish-report.md) for the current file inventory, browser findings, access/cache approach, remaining boundaries, and final 17/17 regression result. No additional Figma MCP calls were made.
