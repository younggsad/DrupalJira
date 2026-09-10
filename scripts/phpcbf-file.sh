#!/usr/bin/env bash

set -euo pipefail

file="$1"
relative="${file#"$PWD"/}"

ddev exec vendor/bin/phpcbf "/var/www/html/$relative"
