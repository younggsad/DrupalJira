# Figma MCP

## MCP client
* Gemini CLI
* Figma MCP extension/server
* Connected via Figma MCP server integration using OAuth authentication with the Figma user account.
* No credentials, API tokens, session data, or private MCP configurations are stored or logged in this repository.

## Figma source

* **File:** `Jira Clone - Community - Copy` (`DqGQuAlbVTjnG3I6VJODSK`)
* **Frame / Node ID:** `24:49337`
* **Frame Name:** `Hero Section`
* **Status:** MCP successfully retrieved metadata (`get_metadata`) and design context (`get_design_context`) including layout hierarchy and asset URLs. (Figma asset URLs are used strictly as design context reference and are not runtime Drupal sources).

## Design → Drupal mapping

| Figma region | Drupal source | Existing | Changes needed | Out of scope |
| --- | --- | --- | --- | --- |
| **Dashboard / header / navigation** | `system.menu.main`, theme blocks (`block.block.drupaljira_theme_admin`) | Standard Drupal main menu and administrative block configuration | Potential CSS styling adjustments if header needs alignment with marketing layout | Marketing landing page navigation links (*Features*, *Pricing*, etc.) |
| **Title / actions** | Page title block (`block.block.drupaljira_theme_page_title`), action blocks (`block.block.drupaljira_theme_local_actions`) | Core page title and action block configurations | Potential custom markup/styling if hero banner is implemented | Hero section marketing tagline and promotional CTAs |
| **Search / filter** | Views contextual filters (`views.view.task_board.yml`) | Contextual filter on project ID (`field_project_target_id`) to scope board tasks by project | Potential addition of Views exposed filters for searching/filtering tasks if required in future UI work | Advanced global full-text search engine (Search API) or live client-side filtering without Views AJAX |
| **Status columns** | View `task_board` (`views.view.task_board.yml`), Twig template `views-view-unformatted--task-board--page-1.html.twig`, and preprocess hook in `drupaljira_theme.theme` | Four columns (`backlog`, `in_progress`, `review`, `done`) populated via preprocess logic sorting task nodes by `field_status` (utilizing Drupal Views AJAX for pagination/refresh) | Potential CSS refinement in `task-board.css` for grid gaps, padding, and column backgrounds | Custom drag-and-drop JavaScript physics libraries or state machines outside native HTML5 drag-and-drop (combined with custom status update endpoint) |
| **Task cards** | Node type `task`, teaser view mode (`core.entity_view_display.node.task.teaser`), `drupaljira_theme_preprocess_node` in `drupaljira_theme.theme`, and `task-board.css` | Task node entities rendered in `teaser` view mode with `.task-card` CSS classes, draggable attributes, AJAX status update endpoint (`drupaljira_board.update_status`), and AJAX modal preview (`drupaljira_board.task_modal`) | Potential template/field adjustments in task teaser display mode to show IDs, tags, and assignee metadata | Hardcoded mock cards or static HTML markup |
| **Project / Task data** | Node entity types (`project`, `task`), fields (`field_status`, `field_assignee`, `field_estimate`, `field_project`) | Fully configured Drupal content types, field storages, and field instances for projects and tasks | None required for core entity schemas | External Jira API synchronization |
| **Typography** | Theme stylesheets (`task-board.css`) | Standard font family declarations in CSS | Potential inclusion of custom web fonts matching design tokens | Inline font styling or dynamic font loaders |
| **Colors** | Theme CSS file (`task-board.css`) | Defined color hex codes in `task-board.css` (`#f8f9fb`, `#e1e4ea`, `#7c5cff`, `#ffffff`, etc.) | Refine CSS hex values or variables to match exact Figma color palette tokens | Runtime dynamic theme generators |
| **Spacing** | CSS grid/flex rules in `task-board.css` | CSS grid with `20px` gap and column/card padding defined in `task-board.css` | Fine-tune grid gaps and padding values in `task-board.css` to match precise Figma spacing specs | Arbitrary inline spacing classes |
| **Borders / radius** | CSS properties in `task-board.css` | `border-radius: 8px` for `.task-card`, `border-radius: 10px` for `.task-board__column`, and border styles in `task-board.css` | Adjust border widths and radii if needed to match design specifications | Complex SVG clip-paths or vector corner shapes |
| **Icons / assets** | Theme stylesheets and markup | Theme styles and markup placeholders (Figma asset URLs are used strictly as design context reference) | Localize or include required SVG icons/assets in the custom theme if displayed in task nodes | Embedding remote temporary Figma asset URLs directly into Drupal code |

## Intentional differences

* **Marketing Landing vs. Project Board:** The Figma reference (`Hero Section`) is a marketing landing page featuring a hero title and multiple product team category cards (*Software Development, Marketing, IT, Design, Operations*), whereas the existing DrupalJira implementation provides a functional project task board (`/project/{node}/board`) focused on managing tasks within specific projects. Therefore, this document provides a design-to-existing-implementation mapping rather than claiming complete visual or functional parity.

* **Data Rendering:** DrupalJira renders real Drupal node entities (`task`) dynamically via Views, Twig templates, and dedicated controllers (`TaskBoardController::updateStatus` for POST status updates and `TaskBoardController::taskModal` for modals) rather than static mock card layouts or hardcoded HTML.

## Best-practices review

### AI recommendations accepted

* **Attach the Task Board library only where it is needed.**
  The global theme library declaration was removed, and `drupaljira_theme/task-board` is attached from the Task Board Views preprocess hook.

* **Use Drupal behaviors and `once()`.**
  Task Board JavaScript is implemented as a Drupal behavior and initialized with `once()` so that it remains safe when Drupal AJAX re-attaches behaviors.

* **Add keyboard support and visible focus.**
  Task cards are focusable with `tabindex="0"` and support `Enter` and `Space`. A `:focus-visible` outline was added for keyboard users.

* **Preserve Drupal render arrays.**
  The Twig template renders `row.content` directly instead of rebuilding entity markup or using unsafe raw output.

* **Preserve cacheability.**
  The board adds the `user` cache context and cacheable dependencies for rendered Task nodes.

* **Keep access and workflow logic outside Twig.**
  Entity access, workflow transition validation, CSRF protection, and status updates remain in Drupal routes, controllers, and access checkers.

### AI recommendations rejected

* **Adding business logic or entity queries to Twig — rejected.**
  Twig remains presentation-only. Task grouping is performed in the theme preprocess layer.

* **Adding `role="button"` to Task cards — rejected.**
  The cards contain rendered Drupal entity content and links. Treating the whole card as a button could create conflicting semantics and interaction behavior. Keyboard interaction is provided without changing the semantic role of the rendered entity.

* **Replacing Drupal entity rendering with custom static card markup — rejected.**
  This would bypass Drupal's render pipeline, field rendering, attributes, access handling, and cacheability.

* **Globally attaching the Task Board library — rejected.**
  The library is specific to the Task Board and should not be loaded on unrelated pages.

## Final Figma comparison

The final implementation was compared with the Figma reference node `24:49337`.

The DrupalJira Task Board follows the main visual direction of the reference through a multi-column board layout, task cards, spacing, borders, colors, and interactive card states.

The following differences are intentional:

* **Functional Drupal board instead of a static design mockup.**
  The implementation renders real Task entities through Views and Drupal render arrays.

* **Different content structure.**
  The Figma reference contains design-specific sections and visual content that are not part of the DrupalJira task-management workflow.

* **Drupal-specific interaction states.**
  Drag-and-drop status changes, workflow validation, AJAX modal rendering, keyboard interaction, and permission checks are implemented in Drupal and are therefore not represented as static Figma elements.

* **Accessibility enhancements.**
  Keyboard focus, `Enter`/`Space` interaction, visible focus indicators, semantic headings, and textual status labels were preserved or added even where the visual reference does not explicitly specify them.

* **Drupal rendering and cacheability.**
  The final implementation keeps Drupal's entity rendering, View configuration, cache metadata, and access control instead of reproducing the design with static markup.

Overall, the implementation prioritizes the Figma visual direction while preserving Drupal 11 theming, rendering, accessibility, cacheability, workflow, and access-control best practices.

## Validation

The final implementation was validated with the following checks:

* `git diff --check` — passed.
* Drupal cache rebuild with `ddev drush cr` — passed.
* PHPCS for the custom Drupal module and theme — passed.
* PHPStan for `drupaljira_board` — passed with no errors.
* Task Board JavaScript behavior and keyboard interaction were manually tested.
* Task Board functionality, status updates, and modal rendering were manually verified after the review changes.
