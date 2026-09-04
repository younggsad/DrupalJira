# AI Instructions — DrupalJira

## 1. First Read the Project Context

At the beginning of every new development session:

1. Read `AI_INSTRUCTIONS.md`.
2. Read `PROJECT_CONTEXT.md`.
3. Inspect the repository.
4. Run `git status`.
5. Check the current branch and recent commits.
6. Read the task/specification relevant to the requested work.

Do not rely only on the context file.

The repository is the source of truth for the current implementation.

## 2. General Development Rules

- Work only on the requested task.
- Do not make unrelated refactors.
- Prefer small, explicit, maintainable changes.
- Preserve existing architecture unless the task requires changing it.
- Inspect existing code before creating new abstractions.
- Reuse existing project conventions.
- Do not invent requirements that are not present in the task/specification.
- If a requirement is ambiguous, identify the ambiguity before making a risky architectural decision.

## 3. Drupal Rules

- Do not modify Drupal core.
- Do not modify contributed modules/themes.
- Put custom functionality under the appropriate custom module/theme directories.
- Follow Drupal coding conventions.
- Follow DrupalPractice rules.
- Prefer Drupal APIs and established Drupal patterns over ad-hoc implementations.

## 4. PHP / DDEV Rules

PHP is intentionally not installed on the WSL host.

Run PHP-related commands through DDEV.

Use:

```bash
ddev exec php ...
```

Composer:

```bash
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

Do not instruct the user to install PHP on WSL unless the project requirements explicitly change.

## 5. Static Analysis

Before considering a task complete, run the relevant checks.

PHPCS:

```bash
ddev exec vendor/bin/phpcs --standard=phpcs.xml.dist web/modules/custom
```

PHPStan:

```bash
ddev exec vendor/bin/phpstan analyse -c phpstan.neon.dist --no-progress
```

GrumPHP:

```bash
ddev exec vendor/bin/grumphp git:pre-commit
```

When appropriate, also run the project's tests/build commands documented in `README.md` and the task specification.

Do not weaken or bypass quality checks just to make a task pass.

## 6. Git Rules

Before making changes:

```bash
git status
git branch --show-current
```

Before committing:

1. Inspect the diff.
2. Confirm only intended files are changed.
3. Run relevant checks.
4. Stage only intended files.
5. Run/allow the Git hooks to verify the staged changes.

Do not use:

```bash
git commit --no-verify
```

unless the user explicitly requests a bypass or there is a documented exceptional reason.

If a bypass is used, clearly state why it was necessary.

## 7. Project Context Maintenance

`PROJECT_CONTEXT.md` is a living project-state document.

Update it after a task is actually completed.

When updating it, keep the information concise and factual.

Record:

- task completed
- implementation summary
- important files
- important configuration
- technical decisions
- verification
- known issues
- next task

Do not record:

- secrets
- API keys
- passwords
- tokens
- personal credentials
- unnecessary personal information

Do not rewrite the entire context file unnecessarily.

Preserve useful historical information and update only what has changed.

## 8. Verification Before Completion

Never report a task as complete solely because the implementation exists.

At minimum:

```text
Inspect → Implement → Test → Static analysis → Review diff → Update context
```

If a check cannot be run, explicitly state which check was not run and why.

## 9. Working With AI Agents

When another AI agent is used on the project:

- Give it `AI_INSTRUCTIONS.md` and `PROJECT_CONTEXT.md`.
- Tell it to inspect the repository before modifying anything.
- Require it to follow the DDEV boundary for PHP/Composer.
- Require tests/static analysis before declaring work complete.
- Require `PROJECT_CONTEXT.md` to be updated after a completed task.
- Never assume the context file is more authoritative than the actual repository.

## 10. Context Update Protocol

When a task is completed, update `PROJECT_CONTEXT.md` in this order:

1. Mark the task completed.
2. Summarize what was implemented.
3. List significant configuration/files.
4. Record verification results.
5. Record any remaining known issues.
6. Set the next task only if it is known from the project's specification.

Do not invent future tasks.

## 11. Definition of Done

A task is considered complete only when:

- The requested implementation exists.
- The implementation follows the project's conventions.
- Relevant tests/checks pass.
- Static analysis passes where applicable.
- The Git diff contains only intended changes.
- Documentation/context is updated when required.
- No secrets were introduced.