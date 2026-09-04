<img alt="Drupal Logo" src="https://www.drupal.org/files/Wordmark_blue_RGB.png" height="60px">

# DrupalJira

DrupalJira is a Drupal-based training project used to practice Drupal development, local development tooling, debugging, and backend development workflows.

The project uses DDEV to provide a reproducible local Drupal environment.

## Requirements

- Docker
- DDEV
- WSL2
- VS Code
- VS Code **PHP Debug** extension

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

- Call Stack
- Variables
- Watch
- Debug Console
- Evaluate expressions
- Step Over
- Step Into
- Step Out
- Continue

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

## Verify DDEV → VS Code Connectivity

The DDEV container must be able to connect to the debugger:

```bash
ddev exec bash -c 'timeout 2 bash -c "</dev/tcp/host.docker.internal/9003" && echo "PORT OPEN" || echo "PORT CLOSED"'
```

Expected result when VS Code is listening:

```text
PORT OPEN
```

## Disable Xdebug

Xdebug should normally remain disabled when debugging is not required.

Disable it with:

```bash
ddev xdebug off
```

Then verify:

```bash
ddev xdebug status
```

The Drupal site and normal Drush commands should continue to work without debugger connection attempts.

For example:

```bash
ddev drush status
```

## Troubleshooting

### Breakpoint is not triggered

Check that:

1. Xdebug is enabled:

   ```bash
   ddev xdebug status
   ```

2. VS Code is running **Listen for Xdebug**.

3. The PHP Debug extension is installed in the WSL environment.

4. VS Code is listening on port `9003`:

   ```bash
   ss -lntp | grep 9003
   ```

5. DDEV can reach the debugger:

   ```bash
   ddev exec bash -c 'timeout 2 bash -c "</dev/tcp/host.docker.internal/9003" && echo "PORT OPEN" || echo "PORT CLOSED"'
   ```

### Breakpoint is shown as unresolved

Check the path mapping:

```text
/var/www/html → ${workspaceFolder}
```

The local `${workspaceFolder}` must point to the project root:

```text
/home/user/projects/DrupalJira
```

The Drupal document root inside the container is:

```text
/var/www/html/web
```

### Drush debugging does not stop

Make sure the command includes:

```bash
--xdebug
```

For example:

```bash
ddev drush xdebug-test --xdebug
```

Also make sure **Listen for Xdebug** is running before starting the command.
