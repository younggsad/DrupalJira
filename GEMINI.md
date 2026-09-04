# DrupalJira — Gemini Project Instructions

## 1. Required Context

Before doing any development task:

1. Read `AI_INSTRUCTIONS.md`.
2. Read `PROJECT_CONTEXT.md`.
3. Inspect the repository and current Git state.
4. Read the task specification relevant to the request.

Do not start implementation before understanding the current project state.

---

## 2. Source of Truth

- The repository is the primary source of truth for implementation.
- `PROJECT_CONTEXT.md` is a maintained project summary.
- `AI_INSTRUCTIONS.md` contains development rules.
- Context files do not replace inspecting the actual code.
- Never invent requirements that are not present in the task specification or existing project context.
- If requirements are ambiguous and the decision could affect architecture or behavior, ask for clarification before making a risky change.

---

## 3. Drupal Rules

- Never modify Drupal core.
- Never modify contributed modules or themes unless explicitly required by the task.
- Custom functionality belongs in:
  - `web/modules/custom/`
  - `web/themes/custom/`
- Follow Drupal coding conventions.
- PHPCS must use:
  - `Drupal`
  - `DrupalPractice`
- Prefer Drupal APIs and established Drupal patterns over custom implementations.

---

## 4. PHP and DDEV

PHP is intentionally NOT installed on the WSL host.

All PHP and Composer operations must run through DDEV.

Use:

```bash
ddev exec php ...
ddev composer ...
```

Examples:

```bash
ddev exec vendor/bin/phpcs --standard=phpcs.xml.dist web/modules/custom
```

```bash
ddev exec vendor/bin/phpstan analyse -c phpstan.neon.dist --no-progress
```

```bash
ddev exec vendor/bin/grumphp git:pre-commit
```

Do not instruct the user to install PHP or Composer directly on WSL unless the project requirements explicitly change.

Git commands are executed from the WSL environment.

---

## 5. Development Workflow

For every task:

1. Read `AI_INSTRUCTIONS.md`.
2. Read `PROJECT_CONTEXT.md`.
3. Inspect the repository.
4. Check Git status and current branch.
5. Read the relevant task specification.
6. Identify the smallest correct implementation.
7. Reuse existing project conventions.
8. Implement the requested change.
9. Run relevant tests.
10. Run relevant static analysis.
11. Review the final Git diff.
12. Verify that only intended files changed.
13. Only after successful verification, update `PROJECT_CONTEXT.md`.

Do not mark a task as completed merely because code was written.

---

## 6. Static Analysis and Quality Checks

Before considering a task complete, run the checks relevant to the changed code.

Current project checks include:

### PHPCS

```bash
ddev exec vendor/bin/phpcs --standard=phpcs.xml.dist web/modules/custom
```

### PHPStan

```bash
ddev exec vendor/bin/phpstan analyse -c phpstan.neon.dist --no-progress
```

### GrumPHP

```bash
ddev exec vendor/bin/grumphp git:pre-commit
```

Do not weaken, disable, remove, or bypass quality checks merely to make a task pass.

If a check cannot be executed, explicitly report why.

---

## 7. Git Rules

Before making changes:

```bash
git status
```

Check the current branch:

```bash
git branch --show-current
```

Before committing:

1. Review `git diff`.
2. Review `git diff --cached` after staging.
3. Verify only intended files are staged.
4. Run the relevant checks.
5. Allow Git hooks to execute normally.

Do not use:

```bash
git commit --no-verify
```

unless the user explicitly requests a bypass or there is a documented exceptional reason.

If a bypass is used, clearly explain why.

Never commit secrets, API keys, passwords, tokens, or credentials.

---

## 8. Project Context Maintenance

`PROJECT_CONTEXT.md` is a living project-state document.

After a task is genuinely completed, update it with:

- task completion status
- implementation summary
- important files changed
- configuration changes
- important technical decisions
- verification performed
- known issues
- next task, only when the next task is actually known from the specification

Do not update the context file simply to claim that a task is complete.

Do not invent future tasks.

Keep the context concise and useful for a new AI session.

Never store secrets in `PROJECT_CONTEXT.md`.

---

## 9. Working With AI Agents

When starting a new AI session:

1. Read `GEMINI.md`.
2. Read `AI_INSTRUCTIONS.md`.
3. Read `PROJECT_CONTEXT.md`.
4. Inspect the repository.
5. Check Git status.
6. Continue from the actual repository state.

The goal is that a new AI session can quickly understand:

- what the project is
- how it is structured
- which tasks are completed
- what technical decisions were made
- what tools are available
- what constraints exist
- what task should be worked on next

Repository state always takes priority over outdated context information.

---

## 10. Task Completion Protocol

When a task is completed:

1. Verify implementation.
2. Run tests.
3. Run static analysis where applicable.
4. Review Git diff.
5. Update `PROJECT_CONTEXT.md`.
6. Re-check Git diff.
7. Report the completed work.

The completion report should include:

- what changed
- files changed
- checks executed
- check results
- remaining issues, if any
- whether `PROJECT_CONTEXT.md` was updated

---

## 11. Definition of Done

A task is complete only when:

- the requested functionality is implemented
- existing architecture and conventions are respected
- relevant tests pass
- relevant static analysis passes
- the Git diff contains only intended changes
- documentation is updated when required
- `PROJECT_CONTEXT.md` reflects the actual completed state
- no secrets were added
- no unrelated refactoring was introduced

---

## 12. Important Project Constraints

Current project constraints:

- Drupal project
- Drupal 11.4+
- DDEV development environment
- WSL 2
- PHP runs inside DDEV
- Composer runs through DDEV
- Git runs from WSL
- Custom code only under custom Drupal directories
- PHPCS uses Drupal + DrupalPractice
- PHPStan uses Drupal integration
- GrumPHP runs PHPCS + PHPStan through Git hooks
- Xdebug is configured for development/debugging
- `PROJECT_CONTEXT.md` must remain synchronized with the real project state

## 13. Importent Rules
Never finish a completed development task without updating PROJECT_CONTEXT.md.

Before reporting a task as completed:
1. Verify the implementation.
2. Run required checks.
3. Review git diff.
4. Update PROJECT_CONTEXT.md.
5. Re-read the updated PROJECT_CONTEXT.md.
6. Only then report the task as completed.
