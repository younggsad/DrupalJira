# DrupalJira — Project Context

## 1. Project Overview

**Project:** DrupalJira

**Purpose:** Training project for developing and maintaining a Drupal application with a reproducible local development environment, debugging, static analysis, and Git-based quality checks.

## 2. Environment

- Drupal: 11.4+
- Local development: DDEV
- PHP: runs inside DDEV; PHP is intentionally not installed on the WSL host
- Git: runs on the WSL host
- IDE: PhpStorm / VS Code
- Operating environment: WSL 2 + DDEV
- Gemini CLI: installed on Windows and used as an AI development assistant

### Important environment rule

Do not install PHP or Composer on the WSL host just to work on this project.

PHP and Composer commands must be executed through DDEV.

Typical commands:

```bash
ddev start
ddev composer install
ddev composer require ...
ddev exec php ...
ddev exec vendor/bin/phpcs ...
ddev exec vendor/bin/phpstan ...
ddev exec vendor/bin/grumphp ...
```

## 3. Repository Structure

Important project paths:

```text
DrupalJira/
├── .ddev/
├── .idea/
├── .vscode/
├── recipes/
├── web/
│   └── modules/
│       └── custom/
│           └── xdebug_test/
├── composer.json
├── composer.lock
├── grumphp.yml
├── phpcs.xml.dist
├── phpstan.neon.dist
├── README.md
├── PROJECT_CONTEXT.md
└── AI_INSTRUCTIONS.md
```

Custom module:

```text
web/modules/custom/xdebug_test/
├── src/
│   └── Drush/
│       └── Commands/
│           └── XdebugTestCommand.php
└── xdebug_test.info.yml
```

## 4. Completed Work

### Task 1.1 — Development Environment / Xdebug

Completed.

The project uses DDEV for the local Drupal environment.

Xdebug was configured and verified.

A custom Drush command is available for testing/debugging:

```text
xdebug_test
```

The command outputs:

```text
Xdebug + Drush works!
```

VS Code debugging configuration was added under:

```text
.vscode/launch.json
```

README documentation was updated with Xdebug and debugging instructions.

### Task 1.2 — IDE / Debugging Setup

Completed.

PhpStorm was installed and the project was opened there for PHP/Drupal development.

The project remains configured around DDEV, so PHP execution and debugging happen inside the DDEV environment.

### Task 1.3 — Static Analysis and Pre-Commit Checks

Completed.

Implemented:

- PHPCS using Drupal coding standards
- PHPCS using DrupalPractice
- PHPStan
- Drupal integration for PHPStan through `mglaman/phpstan-drupal`
- GrumPHP
- Git `pre-commit` hook
- Git `commit-msg` hook
- DDEV-based execution of GrumPHP hooks
- README documentation for manual checks and bypassing checks

Installed development dependencies include:

```text
drupal/coder 9.0.1
mglaman/phpstan-drupal 2.1.2
phpro/grumphp 2.23.0
phpstan/phpstan 2.2.13
phpstan/phpstan-deprecation-rules 2.0.5
```

### PHPCS

Configuration:

```text
phpcs.xml.dist
```

Standards:

```text
Drupal
DrupalPractice
```

Scope:

```text
web/modules/custom
web/themes/custom
```

Vendor directories are excluded.

Manual check:

```bash
ddev exec vendor/bin/phpcs --standard=phpcs.xml.dist web/modules/custom
```

### PHPStan

Configuration:

```text
phpstan.neon.dist
```

Current level:

```text
level: 5
```

Drupal integration:

```text
vendor/mglaman/phpstan-drupal/extension.neon
```

Scope:

```text
web/modules/custom
```

Manual check:

```bash
ddev exec vendor/bin/phpstan analyse -c phpstan.neon.dist --no-progress
```

Clean result was verified:

```text
[OK] No errors
```

### GrumPHP

Configuration:

```text
grumphp.yml
```

Tasks:

```text
phpcs
phpstan
```

GrumPHP is configured so its Git hooks execute PHP through DDEV:

```yaml
git_hook_variables:
    EXEC_GRUMPHP_COMMAND: 'ddev exec php'
```

Manual pre-commit check:

```bash
ddev exec vendor/bin/grumphp git:pre-commit
```

Git hook initialization:

```bash
ddev exec vendor/bin/grumphp git:init
```

The generated hooks execute GrumPHP through DDEV rather than requiring PHP on the WSL host.

### Verification

The quality checks were deliberately tested with invalid code.

Verified behavior:

- PHPCS catches Drupal coding-standard violations.
- GrumPHP blocks a commit when PHPCS fails.
- PHPStan catches type errors.
- GrumPHP blocks a commit when PHPStan fails.
- After fixing the code, the commit succeeds.
- GrumPHP works against staged Git changes.
- Core/contrib code is not intentionally included in the custom-code checks.

## 5. Important Technical Decisions

### DDEV is the PHP execution boundary

Never assume host PHP is available.

Use:

```bash
ddev exec php ...
```

or:

```bash
ddev composer ...
```

### Do not modify Drupal core or contributed code

Project changes should be limited to:

- custom modules
- custom themes
- project configuration
- project documentation
- Composer configuration
- development tooling

Do not edit Drupal core or contributed packages unless a task explicitly requires it.

### Static analysis scope

PHPCS checks custom modules and custom themes.

PHPStan currently checks custom modules.

The goal is to avoid running project quality checks against the entire Drupal core/contrib codebase.

### Reproducibility

Tool configuration must be committed to Git.

Composer dependencies must be represented by:

```text
composer.json
composer.lock
```

A fresh environment should be able to restore the development tooling with:

```bash
ddev composer install
```

## 6. Git State

Latest known project commits:

```text
eb8729a chore: add static analysis and pre-commit checks
8ab7547 test: verify grumphp passes valid code
999c661 docs: update Xdebug setup for VS Code
```

Task 1.3 was considered completed after the static-analysis and pre-commit implementation.

## 7. Current State

The project currently has:

- DDEV-based Drupal development
- Xdebug debugging
- Custom Drush debugging command
- PHPCS
- Drupal + DrupalPractice standards
- PHPStan level 5
- Drupal PHPStan integration
- GrumPHP
- DDEV-based Git hooks
- README documentation
- AI project-context documentation

## 8. Known Constraints

1. PHP is not installed on the WSL host.
2. Composer should be executed through DDEV.
3. Git is executed from WSL.
4. Gemini CLI is installed on Windows, not WSL.
5. Do not add unnecessary runtime dependencies to the Drupal application just to support development tooling.
6. Do not store API keys, passwords, tokens, or other secrets in project context files.

## 9. AI Workflow

Before starting a task:

1. Read `AI_INSTRUCTIONS.md`.
2. Read this file.
3. Inspect the actual repository state.
4. Inspect the relevant existing implementation before modifying it.
5. Check Git status and current branch.
6. Make the smallest appropriate change.
7. Run relevant tests and quality checks.
8. Update this file when the task is genuinely completed.

After completing a task, update:

- completed task status
- files/configuration changed
- important technical decisions
- verification performed
- known issues
- next task

Do not mark a task as completed merely because code was written. Verification should be performed first.

## 10. Current / Next Task

**Current status:** Task 1.3 is completed.

### Task 1.4 — Configuration Management

Status: Completed.

Drupal Core Configuration Management was configured using `config/sync` outside the public files directory.

Completed:
- Config synchronization directory configured as `config/sync`.
- Configuration changes exported with `ddev drush cex -y`.
- Configuration YAML files committed to Git.
- Configuration import verified on a clean environment with `ddev drush cim -y`.
- Site name configuration was successfully restored from `config/sync`.
- `ddev drush config:status` confirmed no differences between active configuration and sync directory.
- README updated with the configuration export/import workflow.

No third-party configuration management module is used.