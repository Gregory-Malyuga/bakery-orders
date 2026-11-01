#!/bin/sh
set -eu

# Authorized-keys forced command for the unprivileged bakery-deploy account.
if [ "${SSH_ORIGINAL_COMMAND:-}" != 'bakery-release' ]; then
    echo 'Only bakery-release is allowed' >&2
    exit 1
fi

exec sudo -n /usr/local/sbin/bakery-deploy-production
