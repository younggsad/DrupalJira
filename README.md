<img alt="Drupal Logo" src="https://www.drupal.org/files/Wordmark_blue_RGB.png" height="60px">

# DrupalJira

DrupalJira is a Drupal 11 training project that implements a simple issue-tracking system with projects, tasks, a Kanban board, documentation and time tracking.

## Requirements

* WSL2 / Ubuntu 24.04
* Docker Desktop
* DDEV
* PHP 8.4
* Composer 2
* Node.js 24
* Git

## Local Development

Start the project:

```bash
ddev start
```

Open the site:

```text
https://drupaljira.ddev.site
```

Check the environment:

```bash
ddev drush status
```

Install dependencies:

```bash
ddev composer install
```

## Xdebug

Xdebug is configured for development and debugging through VS Code.

Main settings:

```text
xdebug.mode=debug,develop
xdebug.start_with_request=yes
xdebug.client_host=host.docker.internal
xdebug.client_port=9003
```

VS Code uses port `9003` and maps:

```text
/var/www/html → ${workspaceFolder}
```

Xdebug can also be used for Drush commands:

```bash
ddev drush <command>
```

## Code Quality

The project uses:

* Drupal Coder / PHPCS
* PHPStan
* phpstan-drupal
* GrumPHP
* Git pre-commit hook

Run GrumPHP:

```bash
ddev exec vendor/bin/grumphp git:pre-commit --no-interaction
```

The checks include coding standards and static analysis.

## Configuration Management

Drupal configuration is stored outside the public web directory:

```text
config/sync
```

The configuration directory is defined in `settings.php`:

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

Clear caches:

```bash
ddev drush cr
```

DDEV-generated settings are not tracked by Git.

---

# Project Features

## Block 2

### Task 2.1 — Content Model

Two main content types are implemented:

* **Project**
* **Task**

Task fields:

* `field_project` — reference to a Project
* `field_status` — Backlog, In Progress, Review, Done
* `field_assignee` — User reference
* `field_estimate` — decimal estimate
* `body` — task description

The project reference is required and the status has a default value of `Backlog`.

### Task 2.2 — Media

Drupal Media and Media Library are enabled.

Media types:

* Image
* Document

Tasks contain an unlimited `field_attachments` media reference field.

The Media Library widget is used for selecting attachments.

### Task 2.3 — Documentation

The project uses:

* Paragraphs
* Entity Reference Revisions
* Media

A Documentation content type references a Project and contains reusable Paragraph components:

* Text
* Code
* Callout
* Image

Code paragraphs support:

* PHP
* Twig
* YAML
* JavaScript

Callouts support:

* Info
* Warning
* Success

A custom theme `drupaljira_theme` provides paragraph templates and preprocessing.

### Task 2.4 — Kanban Board

A custom Kanban board is implemented for tasks.

Main features:

* View-based task board
* Project contextual filter
* Four status columns
* Task teaser cards
* Full task displayed in a modal
* Frontend Editing for task description
* Native HTML5 drag-and-drop
* AJAX requests for board interactions
* Status is saved through a custom controller

Board route:

```text
/project/{project}/board
```

Custom module:

```text
web/modules/custom/drupaljira_board
```

---

# Block 3

## Task 3.1 — TimeLog Entity

A custom Drupal Content Entity `time_log` is implemented for recording work time.

Main fields:

* `task` — reference to a Task
* `uid` — User reference
* `hours` — decimal value
* `log_date` — date
* `notes` — long text
* `over_estimate_reason` — long text
* `created`
* `changed`

The entity has custom forms, access control, list building and routes.

Custom module:

```text
web/modules/custom/drupaljira_timelog
```

## Task 3.2 — Entity API

A debug controller demonstrates Drupal Entity API operations.

Implemented:

* Create TimeLog
* Load TimeLog
* Update hours
* Delete TimeLog
* Verify deletion
* EntityQuery for TimeLogs of a task
* Sorting by `log_date`
* Aggregate `SUM` query for total hours
* Empty-result handling
* Route entity upcasting for Task nodes

Debug routes:

```text
/admin/drupaljira/timelog-debug/crud/{task}
/admin/drupaljira/timelog-debug/list/{task}
/admin/drupaljira/timelog-debug/sum/{task}
```

The routes require:

```text
access administration pages
```

The implementation uses Drupal Entity API and does not use direct SQL queries.

---

# Custom Modules

## drupaljira_board

Provides the Kanban board functionality:

```text
web/modules/custom/drupaljira_board/
```

Contains the board controller, routing, JavaScript, CSS and Twig template.

## drupaljira_timelog

Provides the custom TimeLog entity and Entity API demonstrations:

```text
web/modules/custom/drupaljira_timelog/
```

Main components include:

```text
src/
├── Controller/
├── Entity/
├── Form/
├── TimeLogAccessControlHandler.php
└── TimeLogListBuilder.php
```

---

# Drupal Technologies

The project uses:

* Drupal 11
* Content Types
* Fields
* Entity Reference
* Custom Content Entity
* Media / Media Library
* Paragraphs
* Entity Reference Revisions
* Views
* Contextual Filters
* Frontend Editing
* AJAX
* Drupal Render Arrays
* EntityQuery
* AggregateQuery
* Custom Controllers
* Custom Routes
* Twig
* Drupal behaviors
* Native HTML5 Drag & Drop

---

# Project Structure

```text
DrupalJira/
├── config/
│   └── sync/
├── web/
│   ├── modules/
│   │   └── custom/
│   │       ├── drupaljira_board/
│   │       └── drupaljira_timelog/
│   ├── themes/
│   │   └── custom/
│   │       └── drupaljira_theme/
│   └── sites/
├── composer.json
├── composer.lock
└── README.md
```

Generated files and DDEV-specific settings are not committed when they are not part of the project configuration.

---

# Git Workflow

Development is performed on feature/fix branches.

Typical workflow:

```bash
git checkout develop
git pull

git checkout -b feature/task-name
```

After completing the task:

```bash
git status
git diff
git diff --check

ddev exec vendor/bin/grumphp git:pre-commit --no-interaction

git add .
git commit -m "feat: description"
git push -u origin feature/task-name
```

Changes are merged into protected branches through Pull Requests.

Main branches:

```text
main
develop
feature/*
fix/*
```

---

# Useful Commands

```bash
ddev start
ddev stop
ddev restart

ddev drush status
ddev drush cr
ddev drush cex
ddev drush cim

ddev composer install
ddev composer require <package>

git status
git diff
git diff --check
```

## Current Environment

```text
Drupal: 11.4.5
Drush: 13.7.6
PHP: 8.4
MariaDB: 11.8
Node.js: 24
Composer: 2
DDEV: 1.25.4
```
