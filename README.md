# DrupalJira

Drupal 11 issue tracker project running in a local DDEV environment.

## Requirements

* Docker Desktop
* WSL2 with Ubuntu
* DDEV
* Composer 2

## Local Environment

* **Drupal:** 11.4.5
* **PHP:** 8.4
* **Database:** MariaDB 11.8
* **Web server:** nginx-fpm
* **Composer:** 2
* **Node.js:** 24
* **DDEV project type:** `drupal11`
* **Document root:** `web`

## Start the Project

From the project root:

```bash
ddev start
```

Check the running services:

```bash
ddev describe
```

The project should be available at:

**https://drupaljira.ddev.site**

## Stop the Project

To stop the project containers:

```bash
ddev stop
```

To start them again:

```bash
ddev start
```

## Drupal Administration

Admin login page:

**https://drupaljira.ddev.site/user/login**

### Administrative Credentials

| Field    | Value                |
| -------- | -------------------- |
| Username | `admin`              |
| Password | `DrupalJiraDev2026!` |

> These credentials are intended only for the local development environment.

## Environment Verification

### Check DDEV Services

```bash
ddev describe
```

Expected services:

* `web` — `OK`
* `db` — `OK`

### Check Drupal and PHP

```bash
ddev drush status
```

### Verify Installation Profile

```bash
ddev drush status --fields=install-profile
```

Expected result:

```text
Install profile : minimal
```

### Drupal Status Report

Open the Drupal status report:

**https://drupaljira.ddev.site/admin/reports/status**

The status report should not contain environment-related errors.

## Composer

Install project dependencies:

```bash
ddev composer install
```

The project uses Drupal's recommended Composer project structure.

Drupal core is defined in `composer.json` as:

```json
"drupal/core-recommended": "^11.4"
```

The `composer.lock` file is committed to the repository so that dependency versions remain reproducible.

## Git

The following dependencies and generated/local files are excluded from Git:

```text
/vendor/
/web/core
/web/modules/contrib
/web/themes/contrib
/web/sites/*/files
/web/sites/*/settings.local.php
```

## Setup from a Clean Clone

After cloning the repository:

```bash
ddev start
ddev composer install
```

Then verify the environment:

```bash
ddev describe
ddev drush status
```
