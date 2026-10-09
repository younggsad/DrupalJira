# Frontend redesign audit

Audit date: 2026-10-07. This is a source-code audit, not a completed redesign or a browser verification report.

## Design access blockerw

The requested source is Figma file `DqGQuAlbVTjnG3I6VJODSK`, main node `24:14931` and draft node `24:49351`. Both `get_design_context` calls returned: “You've reached the Figma MCP tool call limit on the Starter plan.” `whoami` confirmed the connection uses a Starter plan with a View seat. No design context, assets, or screenshot was returned for either node.

Figma's MCP rate-limit resource lists Starter access as up to 20 read calls per month. Restored quota or a connected account with sufficient plan/seat access is needed before design extraction and implementation. Existing `docs/figma-mcp.md` describes a different node (`24:49337`); it cannot establish the requested main/draft differences or exact tokens.

No visual values have been inferred from that older report. Application code, configuration, assets, and tests remain unchanged.

## Existing component inventory

| Area | Implementation to reuse | Findings |
| --- | --- | --- |
| Shell | `templates/page.html.twig`, layout header/main/sidebar CSS | Sticky header, main content, breadcrumbs, help/messages, optional sidebars and footer. Header has hardcoded Jira branding and nonfunctional marketing links/CTA. No application-specific navigation or skip target in this template. |
| Tokens | `css/base/variables.css` | Shared colors, radii, shadows, board gap and layering already exist. No shared font, spacing scale, control height or transition tokens. Current values have not been verified against either requested Figma node. |
| Typography | `base.css`, `typography.css` | Outfit loaded from Google via CSS import; controls repeat font declarations. Heading sizes and many component sizes are local literals. |
| Buttons/forms | `components/buttons.css`, `forms.css` | Shared native controls and Drupal action styling. Broad button selectors affect contributed interfaces. Focus styling covers only part of the input types; date controls, disabled states and field errors need review. Actions do not wrap. |
| Cards | `components/cards.css`, node templates | Project and task templates preserve render arrays, titles and attributes. Generic `.node` styling overlaps board cards. Badges duplicate geometry and all statuses share one visual style. |
| Board | Views `task_board`, Views Twig override, theme preprocess, `task-board.css` | Four status columns render real teaser entities. Project contextual filter and cache metadata exist. Desktop/tablet/mobile grids are present. No explicit column names, counts or translated empty-state markup. |
| Board behavior | `js/task-board.js`, `TaskBoardController` | Native drag/drop, optimistic movement with rollback, token-protected status POST, click/Enter/Space modal opening. Preserve `data-task-id`, `data-status`, headings and title links. |
| Task details | `node--task.html.twig`, entity display configuration | Whole entity content rendered without hardcoded task data. No dedicated detail layout; default card title decoration also affects teasers. |
| Task forms/workflow | `drupaljira_board.module`, configured field widgets | Normal `Save` explicitly preserves moderation state. Separate `Save as …` action performs intentional workflow transitions and updates through AJAX. These are distinct required behaviors, covered by E2E. |
| Dialogs | `components/modal.css`, board behavior/controller | Drupal dialog handles modal lifecycle. Board endpoint renders only the entity, not page chrome. Fetching an HTML fragment needs review for attached asset delivery, behavior detach/removal, repeated opening and focus return. |
| Frontend editing | `page--frontend-editing.html.twig`, integration CSS | Content-only page template is already present. Integration CSS contains many `!important` rules and a duplicated hidden-title selector. Preserve editor functionality while narrowing overrides. |
| Paragraphs | Four paragraph Twig templates and `paragraphs.css` | Text, image, code and callout patterns exist. CSS uses `.paragraph--type--*`, whereas templates explicitly add `.paragraph--text`, `.paragraph--image`, `.paragraph--code`, `.paragraph--callout`; verify generated class presence before assuming styles apply. |
| Project/dashboard | `node--project.html.twig`, ProjectStatisticsBlock | Project entity and statistics are reusable data sources. No separate dashboard template/controller was found in custom frontend code. Statistics are formatted translated strings, not separate metric label/value variables. |
| Statistics | `drupaljira-project-stats.html.twig`, statistics CSS, AJAX controller | Five dynamic metrics, wrapper ID and refresh action exist. Keep cache tags and AJAX replacement hook. Refresh link uses `use-ajax`; verify its library attachment. Grid minimum width can exceed narrow available space. |
| Sprints | SprintController, SprintAccessCheck, sprint CSS | Existing feature is a text-only stub with Scrum access checks. Current `.sprint-page`/`.drupaljira-sprint-stub` selectors have no matching wrapper in the controller. Styling must not imply implemented sprint functionality. |
| Time log | Entity Twig, list builder, TimeLogForm/TimeLogWriteOffForm, widgets/formatter | Drupal renders fields, validation, state-dependent reason field, time summaries and collection table. Preserve labels, validation, events and redirect behavior. No dedicated frontend asset library found. |
| Branding/assets | theme info, global theme settings, site settings | No logo/favicon/icon/font/image asset directory in the custom theme. Default logo/favicon enabled with empty custom paths. Shell uses a text `J` and ignores configured site name; configured site name is `DrupalJira Config Test`. Figma asset availability is unknown. |

## Confirmed maintenance issues and risks to verify

1. Global library references nonexistent `css/pages/project.css` and `css/pages/task.css`.
2. `.task-board__modal-content` appears in both modal and board CSS with conflicting padding.
3. Header repeats `background-color`; frontend-editing duplicates the hidden-title rule; global JavaScript registers an empty behavior.
4. Repeated colors, font families, sizes, padding, badge styles and animation timings sit outside the shared tokens. Broad `transition: all`, hover transforms and permanent `will-change` need consolidation and reduced-motion handling.
5. Generic link hover removes underlines and reduces opacity. Muted text colors need contrast measurements once the actual design palette is available.
6. Board card click handling intercepts nested links, including project/assignee links. Keyboard handlers also bubble from nested links. Preserve title-to-dialog behavior required by tests while making other navigation intentional.
7. `once()` is applied to the board container, then card listeners are added only to cards present at initialization. Dynamically inserted cards inside an already processed container will not receive those handlers.
8. Drag failures only log to the console; rollback appends to the previous column rather than restoring the exact previous position. No keyboard status-changing interaction or live feedback is present in the board itself; existing edit workflow remains available.
9. CSS-generated English empty-state text is not translatable, and whitespace inside the cards container can prevent `:empty` from matching.
10. Modal styling uses extensive `!important`, fixed padding and multiple scroll containers. Board dialog rules can interfere with core/contributed dialogs; media E2E already documents overlapping media-type pointer targets.
11. Responsive tables lack a dedicated scroll wrapper. Long titles/URLs, validation messages, form actions and narrow statistics cards require browser verification. Header sticky positioning also needs verification with Drupal administrative toolbar offsets.
12. Sprint styles are currently unused by the stub render array. The task-body clamp lacks `-webkit-line-clamp` alongside its WebKit box declaration.

These are source findings or explicit browser-verification risks, not claims of measured visual parity or runtime failures.

## Regression contracts

Existing Playwright specs cover project create/edit and Kanban/Scrum defaults, task create/edit/required-project validation, state-preserving normal Save, explicit moderation transitions, board project isolation/drag persistence/modal opening, permissions, media upload/render/library selection, time logging/validation and Scrum-only sprint access.

Keep exact `Save` and transition action names, Title/Project/Assignee/Body/Hours/Minutes labels, board status headings, entity article semantics, task title links, attachment alt text and filenames, dialog role, `data-task-id`, `data-status`, `project-statistics-wrapper` and Drupal AJAX action IDs. Do not weaken tests to accommodate regressions.

## Implementation sequence once Figma reads succeed

1. Read both requested nodes through MCP and drill into visible child components. Record exact tokens, variants, assets and main/draft differences; choose polished components with evidence.
2. Build on the existing token/component files; reconcile broken library paths, duplicates and unused selectors while implementing shell and branding from verified design context.
3. Compose project/dashboard and statistics from existing render arrays, then board/teasers and task details/forms. Add presentation variables only where needed, retaining field access and cacheability.
4. Scope dialog and frontend-editing styles; preserve Drupal dialog behavior, keyboard operation and dynamic asset handling. Apply the same controls/cards/tables to sprints and time logging.
5. Localize actual Figma static assets and configure theme branding; leave entity/user imagery dynamic.
6. Rebuild cache, run complete Playwright, configured PHPCS and PHPStan, JavaScript syntax/library-path checks and `git diff --check`. Check desktop/tablet/mobile in-browser, keyboard/focus/scrolling, console errors and loaded asset geometry.

## Validation status for this audit

No implementation was performed, so post-redesign cache rebuild, Playwright, PHPCS, PHPStan and manual browser checks are pending. Existing validation claims in older documents are historical and are not results from this audit.
