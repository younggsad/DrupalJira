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

## Xdebug + PhpStorm

Xdebug is configured for local development and is disabled by default to avoid unnecessary overhead.

### Enable Xdebug

Enable Xdebug when debugging is required:

```bash
ddev xdebug on
```

Check its status:

```bash
ddev xdebug status
```

Expected result:

```text
xdebug enabled
```

### Disable Xdebug

After debugging, disable Xdebug:

```bash
ddev xdebug off
```

Verify:

```bash
ddev xdebug status
```

Expected result:

```text
xdebug disabled
```

Keep Xdebug disabled during normal development when debugging is not required.

### PhpStorm Configuration

Configure a PHP Server in PhpStorm:

| Setting  | Value                  |
| -------- | ---------------------- |
| Name     | `DrupalJira-Xdebug`    |
| Host     | `drupaljira.ddev.site` |
| Port     | `80`                   |
| Debugger | `Xdebug`               |

Enable **Use path mappings** and map the project root:

| Local path       | Remote path     |
| ---------------- | --------------- |
| `<project-root>` | `/var/www/html` |

For the WSL2 environment, the local project path is similar to:

```text
//wsl.localhost/Ubuntu-24.04/home/user/projects/DrupalJira
```

The Xdebug debugger port is:

```text
9003
```

In PhpStorm, enable:

**Run → Start Listening for PHP Debug Connections**

### Web Debugging

1. Enable Xdebug:

   ```bash
   ddev xdebug on
   ```

2. Start listening for PHP debug connections in PhpStorm.

3. Set a breakpoint in Drupal code.

4. Open the corresponding page in the browser.

5. PhpStorm should stop execution at the breakpoint.

The path mapping must resolve container paths such as:

```text
/var/www/html/web/index.php
```

to the local project files.

### Drush Debugging

Drush disables Xdebug by default for CLI commands for performance reasons.

When debugging a Drush command, explicitly enable Xdebug for that invocation:

```bash
ddev drush <command> --xdebug
```

A simple Xdebug smoke test is provided by the `xdebug_test` development module:

```bash
ddev drush xdebug-test --xdebug
```

Set a breakpoint in:

```text
web/modules/custom/xdebug_test/src/Drush/Commands/XdebugTestCommand.php
```

Then run the command. PhpStorm should stop at the breakpoint and allow inspection and modification of variables.

After modifying a variable in PhpStorm, resume execution and verify the changed value in the Drush output.

### Troubleshooting

Check Xdebug status:

```bash
ddev xdebug status
```

Check that PhpStorm is listening on port `9003`.

Verify the connection from the DDEV container:

```bash
ddev exec bash -c 'timeout 2 bash -c "</dev/tcp/host.docker.internal/9003" && echo "PORT OPEN" || echo "PORT CLOSED"'
```

If debugging is no longer required, turn Xdebug off:

```bash
ddev xdebug off
```

## Composer

Install project dependencies:

```bash
ddev composer install
```

The project uses Drupal's recommended Composer project structure.

Drupal core is defined in `composer.json` as:

```json
"drupal/core-recommended: ^11.4"
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
