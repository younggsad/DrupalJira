# DrupalJira

DrupalJira is a Drupal 11 training project that implements a lightweight issue-tracking system for managing projects, tasks, time tracking, Kanban boards, and Scrum workflows.

The project is built to practice Drupal development using custom modules, Drupal APIs, configuration management, Composer, automated code quality checks, and Git-based development workflows.

## Features

### Projects and Tasks

The project provides two main content types:

* **Project** — groups related tasks and defines the project type.
* **Task** — represents an issue or unit of work associated with a project.

Tasks support:

* Project reference
* Assignee
* Estimate
* Description
* Media attachments
* Workflow status

Projects support two project types:

* **Kanban**
* **Scrum**

The project type is stored in `field_project_type`.

### Task Workflow

Task status is managed using Drupal **Workflows** and **Content Moderation**.

The Task workflow includes:

* Backlog
* In Progress
* Review
* Done

This replaces the previous hardcoded status implementation and allows task status transitions to be managed through Drupal's workflow system.

### Kanban Board

A custom Kanban board is provided for project tasks.

Main functionality:

* Four status columns
* Project-specific task filtering
* Task cards
* Task modal
* Drag-and-drop interactions
* AJAX requests
* Task status updates
* Frontend Editing for task descriptions

Board route:

```text
/project/{project}/board
```

Custom module:

```text
web/modules/custom/drupaljira_board
```

### Scrum Projects and Sprints

Scrum projects provide a dedicated **Sprints** section.

The Sprints route is available only when the project type is `Scrum`.

For Kanban projects:

* The Sprints local task is not displayed.
* Direct access to the Sprints route returns HTTP 403.

Access is implemented using Drupal's Access API and a custom access checker.

The Sprints page currently provides a minimal stub for future sprint functionality.

### Time Tracking

The `drupaljira_timelog` module provides a custom `time_log` Content Entity for recording work time.

A TimeLog contains:

* Task reference
* User reference
* Hours
* Log date
* Notes
* Over-estimate reason
* Created timestamp
* Changed timestamp

The module also provides:

* Time logging forms
* Entity access control
* Entity list building
* Entity queries
* Time summaries
* Project statistics
* Cache invalidation
* Debug routes for Entity API operations

### Project Statistics

Project statistics provide information about logged time and task estimates.

The implementation uses:

* Dependency Injection
* Custom services
* EntityQuery
* AggregateQuery
* Cache tags
* Render arrays

Project statistics are invalidated when related TimeLog entities change.

### Events

TimeLog creation uses a custom Drupal event instead of an entity insert hook.

The implementation includes:

* `TimeLogCreatedEvent`
* `TimeLogCreatedSubscriber`

The event subscriber logs information about newly created TimeLogs to the `drupaljira_timelog` logging channel and handles related cache invalidation.

### Data Migration

The project uses Drupal's Migrate API to import backlog tasks from CSV files.

Migration functionality uses:

* Migrate API
* Migrate Plus
* Migrate Source CSV
* Migrate Tools

The migration supports:

* CSV source data
* Field mapping
* Status mapping
* Migration rollback
* Re-importing migrated tasks

### Media and Documentation

Drupal Media and Media Library are used for task attachments.

Supported media types include:

* Image
* Document

The project also uses Paragraphs for reusable documentation components:

* Text
* Code
* Callout
* Image

The custom theme `drupaljira_theme` provides templates and preprocessing for documentation components.

---

# Requirements

The development environment uses:

* WSL2 / Ubuntu 24.04
* Docker Desktop
* DDEV
* PHP 8.4
* Composer 2
* Node.js 24
* Git

Current project versions:

```text
Drupal:    11.4.5
Drush:     13.7.6
PHP:       8.4
MariaDB:   11.8
Node.js:   24
Composer:  2
DDEV:      1.25.4
```

---

# Local Development

## Start the project

From the project root:

```bash
ddev start
```

Check the environment:

```bash
ddev drush status
```

Open the site:

```text
https://drupaljira.ddev.site
```

## Install PHP dependencies

```bash
ddev composer install
```

For a fresh environment, this installs the dependency versions defined in `composer.lock`.

## Clear Drupal caches

```bash
ddev drush cr
```

## Import configuration

```bash
ddev drush cim
```

## Export configuration

```bash
ddev drush cex
```

Configuration is stored in:

```text
config/sync
```

The configuration directory is defined outside the public web root.

---

# Xdebug

Xdebug is configured for local development and debugging through VS Code.

Main settings:

```text
xdebug.mode=debug,develop
xdebug.start_with_request=yes
xdebug.client_host=host.docker.internal
xdebug.client_port=9003
```

VS Code uses port `9003`.

The container-to-workspace mapping is:

```text
/var/www/html → ${workspaceFolder}
```

Xdebug can also be used while running Drush commands:

```bash
ddev drush <command>
```

---

# Code Quality

## Browser E2E tests

Playwright verifies Drupal through its public UI and HTTP routes. Local DDEV
fixtures use Drupal's Entity API via Drush and preserve existing site data and
permissions. See [E2E installation, execution, fixtures, and troubleshooting](docs/e2e.md)
and the [LLM review](docs/e2e-llm-review.md).

The project uses automated code quality tools:

* Drupal Coder / PHPCS
* PHPStan
* phpstan-drupal
* GrumPHP
* Git pre-commit hooks

## PHP CodeSniffer

Run Drupal coding standards checks:

```bash
ddev exec vendor/bin/phpcs web/modules/custom
```

## PHPStan

Run static analysis:

```bash
ddev exec vendor/bin/phpstan analyse web/modules/custom
```

## GrumPHP

Run the complete pre-commit checks:

```bash
ddev exec vendor/bin/grumphp git:pre-commit --no-interaction
```

These checks help maintain Drupal coding standards, detect PHP errors, and catch common implementation problems before changes are committed.

---

# Configuration Management

Drupal configuration is stored in:

```text
config/sync
```

The directory is configured in `settings.php`:

```php
$settings['config_sync_directory'] = DRUPAL_ROOT . '/../config/sync';
```

Export configuration:

```bash
ddev drush cex
```

Import configuration:

```bash
ddev drush cim
```

Clear caches after configuration changes when necessary:

```bash
ddev drush cr
```

DDEV-generated settings are not tracked by Git.

---

# Custom Modules

Custom functionality is implemented in:

```text
web/modules/custom
```

## drupaljira_board

```text
web/modules/custom/drupaljira_board
```

Provides:

* Kanban board
* Task board interactions
* Task status updates
* Task modal
* AJAX functionality
* Scrum project Sprints route
* Scrum-specific access control

Main Drupal concepts used by the module include:

* Controllers
* Routes
* Local tasks
* Access API
* Services
* Drupal behaviors
* Render arrays
* Twig
* AJAX

## drupaljira_timelog

```text
web/modules/custom/drupaljira_timelog
```

Provides:

* `time_log` Content Entity
* TimeLog forms
* Entity access control
* Entity list building
* Time tracking
* Project statistics
* Custom events
* Event subscribers
* Cache invalidation
* Migration functionality
* Entity API demonstrations

---

# Drupal Technologies

The project uses the following Drupal concepts and APIs:

* Drupal 11
* Composer
* Content Types
* Fields
* Entity API
* Content Entities
* Entity Reference
* Entity Reference Revisions
* Media / Media Library
* Paragraphs
* Views
* Contextual Filters
* Workflows
* Content Moderation
* Access API
* Routing API
* Local Tasks
* Controllers
* Dependency Injection
* Service Container
* Event Dispatcher
* Update Hooks
* Migrate API
* EntityQuery
* AggregateQuery
* Cache API
* Cache Tags
* Render Arrays
* AJAX
* Drupal Behaviors
* Twig
* Frontend Editing
* Native HTML5 Drag and Drop

---

# Project Structure

```text
DrupalJira/
├── config/
│   └── sync/
│
├── web/
│   ├── core/
│   ├── modules/
│   │   ├── contrib/
│   │   └── custom/
│   │       ├── drupaljira_board/
│   │       └── drupaljira_timelog/
│   │
│   ├── themes/
│   │   ├── contrib/
│   │   └── custom/
│   │       └── drupaljira_theme/
│   │
│   └── sites/
│
├── vendor/
├── composer.json
├── composer.lock
├── README.md
└── .ddev/
```

The project uses a relocated Drupal document root:

```text
web/
```

This means the public Drupal application files are separated from project-level files such as Composer configuration and the dependency directory.

---

# Composer

Project dependencies are defined in:

```text
composer.json
```

Exact dependency versions are recorded in:

```text
composer.lock
```

Install dependencies using:

```bash
ddev composer install
```

Add a new package using:

```bash
ddev composer require <package>
```

Development dependencies include:

* Drupal Coder
* PHPStan
* phpstan-drupal
* GrumPHP

The `vendor/` directory is generated by Composer and is not committed to Git.

---

# Git Workflow

Development is performed on feature or fix branches.

Start from the latest `develop` branch:

```bash
git checkout develop
git pull
```

Create a feature branch:

```bash
git checkout -b feature/task-name
```

Example:

```bash
git checkout -b feature/task7-4-sprints-ui
```

Before committing changes:

```bash
git status
git diff
git diff --check
```

Run code quality checks:

```bash
ddev exec vendor/bin/grumphp git:pre-commit --no-interaction
```

Stage and commit:

```bash
git add .
git commit -m "feat: description"
```

Push the branch:

```bash
git push -u origin feature/task-name
```

Changes are merged into protected branches through Pull Requests.

Main branch types:

```text
main
develop
feature/*
fix/*
```

---

# Useful Commands

## DDEV

```bash
ddev start
ddev stop
ddev restart
ddev status
```

## Drupal / Drush

```bash
ddev drush status
ddev drush cr
ddev drush cex
ddev drush cim
ddev drush updb
```

## Composer

```bash
ddev composer install
ddev composer require <package>
ddev composer update
```

## Git

```bash
git status
git diff
git diff --check
git log --oneline
```

---

# Development Notes

Drupal configuration should be changed through Drupal's configuration system and exported to `config/sync`.

Custom functionality should be implemented in:

```text
web/modules/custom
```

Drupal core and contributed modules should not be modified directly.

Generated files and environment-specific DDEV settings should not be committed unless they are intentionally part of the project configuration.

For custom functionality, follow Drupal's APIs and dependency injection patterns instead of using direct database queries where a suitable Drupal API is available.
