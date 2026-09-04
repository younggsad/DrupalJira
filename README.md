<img alt="Drupal Logo" src="https://www.drupal.org/files/Wordmark_blue_RGB.png" height="60px">

# DrupalJira

DrupalJira is a Drupal-based training project used to practice Drupal development, local development tooling, debugging, static analysis, and backend development workflows.

The project uses DDEV to provide a reproducible local Drupal environment.

## Requirements

* Docker
* DDEV
* WSL2
* VS Code
* VS Code **PHP Debug** extension

## Local Development

### Start the project

From the project root:

```bash
ddev start
```

The site is available at:

```text
https://drupaljira.ddev.site
```

To check the current DDEV environment:

```bash
ddev status
```

## Xdebug

Xdebug is configured to be enabled only when needed.

This keeps the normal development environment lightweight and avoids unnecessary debugger connection attempts when debugging is not required.

### Enable Xdebug

```bash
ddev xdebug on
```

Check the current status:

```bash
ddev xdebug status
```

Disable Xdebug when debugging is finished:

```bash
ddev xdebug off
```

The Xdebug debugger uses port `9003`.

> Port `9003` is used for the Xdebug connection between the DDEV environment and VS Code.
>
> Ports `80` and `443` are used for HTTP/HTTPS traffic to the Drupal site.

## VS Code Xdebug Configuration

The project contains a VS Code debugging configuration:

```text
.vscode/
└── launch.json
```

The configuration listens for incoming Xdebug connections on port `9003` and maps the Drupal container filesystem to the local project:

```text
/var/www/html → ${workspaceFolder}
```

The configuration is:

```json
{
  "version": "0.2.0",
  "configurations": [
    {
      "name": "Listen for Xdebug",
      "type": "php",
      "request": "launch",
      "port": 9003,
      "pathMappings": {
        "/var/www/html": "${workspaceFolder}"
      }
    }
  ]
}
```

### Start Web Debugging

1. Open the project in VS Code using **Remote-WSL**.

2. Make sure the **PHP Debug** extension is installed in the WSL environment.

3. Enable Xdebug:

   ```bash
   ddev xdebug on
   ```

4. Open **Run and Debug** in VS Code.

5. Select **Listen for Xdebug**.

6. Start the debugger.

7. Set a breakpoint in PHP code.

8. Open the Drupal site in a browser.

For example, a breakpoint can be placed in:

```text
web/index.php
```

When the Drupal request reaches the breakpoint, VS Code pauses execution and provides access to:

* Call Stack
* Variables
* Watch
* Debug Console
* Evaluate expressions
* Step Over
* Step Into
* Step Out
* Continue

Variables can also be modified while execution is paused.

## Drush Debugging

Xdebug can also be used to debug PHP code executed through Drush.

Pass the `--xdebug` option to the Drush command:

```bash
ddev drush <command> --xdebug
```

For example:

```bash
ddev drush status --xdebug
```

When the command reaches a breakpoint, VS Code pauses execution in the same way as for a web request.

## Xdebug Test Module

The project contains a temporary custom module used to verify CLI debugging:

```text
web/modules/custom/xdebug_test/
├── xdebug_test.info.yml
└── src/
    └── Drush/
        └── Commands/
            └── XdebugTestCommand.php
```

The module provides the following Drush command:

```bash
ddev drush xdebug-test --xdebug
```

The command can be used to verify that:

1. Drush starts a PHP process with Xdebug enabled.
2. Xdebug connects to VS Code.
3. VS Code stops execution at a breakpoint.
4. Variables can be inspected and modified.
5. Execution can be resumed from the debugger.

Expected output:

```text
Xdebug + Drush works!
```

## Static Analysis

The project uses the following tools for static code quality checks:

* PHP_CodeSniffer with Drupal and DrupalPractice standards from `drupal/coder`
* PHPStan with Drupal integration from `mglaman/phpstan-drupal`
* GrumPHP to run quality checks before commits

All PHP-based checks must be executed inside the DDEV environment.

### PHPCS

PHP_CodeSniffer checks custom Drupal modules and themes using the Drupal and DrupalPractice coding standards.

Run the check manually:

```bash
ddev exec vendor/bin/phpcs --standard=phpcs.xml.dist web/modules/custom
```

The PHPCS configuration is stored in:

```text
phpcs.xml.dist
```

The configuration limits analysis to project custom code:

```text
web/modules/custom
web/themes/custom
```

Drupal core and contributed modules/themes are not included in the project coding-standard checks.

### PHPStan

PHPStan performs static analysis of the custom Drupal PHP code.

The project uses PHPStan level `5` together with the Drupal extension.

Run the check manually:

```bash
ddev exec vendor/bin/phpstan analyse -c phpstan.neon.dist --no-progress
```

The PHPStan configuration is stored in:

```text
phpstan.neon.dist
```

The analysis is limited to:

```text
web/modules/custom
```

### GrumPHP

GrumPHP combines PHPCS and PHPStan into a Git pre-commit quality gate.

Run the pre-commit checks manually against staged changes:

```bash
ddev exec vendor/bin/grumphp git:pre-commit --no-interaction
```

A successful run should report:

```text
Running task 1/2: phpcs... ✔
Running task 2/2: phpstan... ✔
```

If one of the checks fails, the commit is blocked.

### Git Hooks

GrumPHP installs Git hooks for the project.

The generated hooks execute PHP through DDEV, so PHP does not need to be installed on the WSL host.

The configured command is:

```text
ddev exec php
```

If the Git hooks need to be regenerated, run:

```bash
ddev exec vendor/bin/grumphp git:init
```

The hooks are stored in:

```text
.git/hooks/
```

Git hooks are local Git metadata and are not committed to the repository.

### Manual Quality Checks

To run the individual checks manually:

```bash
ddev exec vendor/bin/phpcs --standard=phpcs.xml.dist web/modules/custom
```

```bash
ddev exec vendor/bin/phpstan analyse -c phpstan.neon.dist --no-progress
```

To run the GrumPHP pre-commit check:

```bash
ddev exec vendor/bin/grumphp git:pre-commit --no-interaction
```

### Bypassing the Pre-Commit Checks

In exceptional cases, Git's verification hooks can be bypassed with:

```bash
git commit --no-verify
```

This should only be used when there is a justified reason to bypass the local quality gate.

Using `--no-verify` means that PHPCS and PHPStan will not be used to block the commit locally. The change should still be validated manually, and CI checks may reject code that does not meet the project's quality requirements.

### Reproducibility

The static-analysis tools and their versions are defined in:

```text
composer.json
composer.lock
```

After installing the project dependencies with:

```bash
ddev composer install
```

the required PHPCS, PHPStan, GrumPHP, and Drupal coding-standard dependencies are installed from the locked dependency versions.

If the Git hooks need to be initialized or regenerated after installation:

```bash
ddev exec vendor/bin/grumphp git:init
```

## Verify Xdebug Configuration

Check the Xdebug status:

```bash
ddev xdebug status
```

Check the Xdebug configuration inside the DDEV container:

```bash
ddev exec php -i | grep -E 'xdebug.mode|xdebug.start_with_request|xdebug.client_host|xdebug.client_port|xdebug.discover_client_host'
```

Expected values include:

```text
xdebug.mode => debug,develop
xdebug.start_with_request => yes
xdebug.client_host => host.docker.internal
xdebug.client_port => 9003
xdebug.discover_client_host => On
```

## Verify VS Code Debugger Port

When **Listen for Xdebug** is running, VS Code should listen on port `9003` inside WSL.

Check the listener:

```bash
ss -lntp | grep 9003
```

A working configuration should show a listener similar to:

```text
LISTEN ... *:9003 ...
```

The port can also be tested locally:

```bash
nc -zv 127.0.0.1 9003
```

Expected result:

```text
Connection to 127.0.0.1 9003 port [tcp/*] succeeded!
```

## Configuration Management

Drupal configuration is managed using Drupal Core Configuration Management.

The configuration synchronization directory is:

```text
config/sync
```

It is located outside the public `web/sites/*/files` directory and is committed to Git.

### Export configuration

After making configuration changes through the Drupal UI, export the active configuration to the synchronization directory:

```bash
ddev drush cex -y
```

Review the changes:

```bash
git status
git diff -- config/sync
```

Commit the updated configuration:

```bash
git add config/sync
git commit -m "chore: update Drupal configuration"
git push
```

Do not manually edit configuration YAML files unless there is a specific development requirement to do so. Configuration changes should normally be made through Drupal and then exported with `drush cex`.

### Import configuration

After pulling configuration changes from Git:

```bash
git pull
```

Import the configuration into the local Drupal installation:

```bash
ddev drush cim -y
```

Verify that the database and configuration synchronization directory are identical:

```bash
ddev drush config:status
```

The expected result is:

```text
[notice] No differences between DB and sync directory.
```

### Configuration workflow

The normal workflow is:

```text
Drupal UI
    ↓
Configuration change
    ↓
ddev drush cex -y
    ↓
config/sync/*.yml
    ↓
git commit
    ↓
git push
    ↓
git pull (another developer)
    ↓
ddev drush cim -y
    ↓
Updated Drupal configuration
```

This makes Drupal configuration reproducible between development environments without manually editing configuration files.
